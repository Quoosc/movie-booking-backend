<?php
// app/Services/CheckoutLifecycleService.php
namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ShowtimeSeat;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\SeatStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutLifecycleService
{
    public function __construct(
        protected Booking     $bookingModel,
        protected Payment     $paymentModel,
        protected ShowtimeSeat $showtimeSeatModel,
        protected UserService $userService,
        // dùng Lazy như Java: tránh vòng lặp dependency giữa RefundService & CheckoutLifecycleService
        protected RefundService $refundService,
    ) {}

    /**
     * Payment gateway báo thành công (PayPal/Momo).
     * - Kiểm tra booking status.
     * - Nếu booking EXPIRED thì xử lý late payment.
     * - Nếu ok thì CONFIRMED, BOOKED seat, cộng điểm, set QR.
     */
    public function handleSuccessfulPayment(Payment $payment, ?float $gatewayAmount, ?string $gatewayTxnId): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayAmount, $gatewayTxnId) {
            /** @var Booking $booking */
            $booking = $payment->booking;

            if (
                $payment->status === PaymentStatus::SUCCESS
                && $booking->status === BookingStatus::CONFIRMED
            ) {
                Log::debug("Payment {$payment->id} already processed successfully");
                return $payment;
            }

            if ($booking->status === BookingStatus::EXPIRED) {
                Log::warning("Payment {$payment->id} arrived after booking {$booking->id} expired. Handling late payment.");
                return $this->handleLatePayment($payment, $gatewayAmount, $gatewayTxnId);
            }

            $expectedGatewayAmount = $this->resolveGatewayAmount($payment);
            if (
                $gatewayAmount !== null && $expectedGatewayAmount !== null
                && (float) $expectedGatewayAmount !== (float) $gatewayAmount
            ) {

                $currency = $this->resolveGatewayCurrency($payment);
                Log::error("Gateway amount mismatch for payment {$payment->id}. Expected {$expectedGatewayAmount} {$currency}, got {$gatewayAmount} {$currency}");
                return $this->handleFailedPayment($payment, 'Gateway amount mismatch');
            }

            $payment->status = PaymentStatus::SUCCESS;
            $payment->completed_at = Carbon::now();
            if ($gatewayTxnId) {
                $payment->transaction_id = $gatewayTxnId;
            }
            $payment->save();

            $bookingChanged = false;

            if ($booking->status !== BookingStatus::CONFIRMED) {
                $booking->status = BookingStatus::CONFIRMED;
                $booking->qr_payload = $this->generateQrPayload($booking);
                $bookingChanged = true;
            }

            if (!$booking->loyalty_points_awarded) {
                $this->userService->addLoyaltyPoints($booking->user_id, (float) $booking->final_price);
                $booking->loyalty_points_awarded = true;
                $bookingChanged = true;
            }

            if ($bookingChanged) {
                $booking->save();
            }

            return $payment;
        });
    }

    public function handleFailedPayment(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $booking = $payment->booking;

            if ($payment->status === PaymentStatus::SUCCESS) {
                Log::warning("Attempted to mark payment {$payment->id} as failed after success");
                return $payment;
            }

            $payment->status = PaymentStatus::FAILED;
            $payment->error_message = $reason;
            $payment->save();

            if ($booking->status === BookingStatus::PENDING_PAYMENT) {
                $booking->status = BookingStatus::CANCELLED;
                $booking->qr_payload = null;
                $booking->qr_code = null;
                $booking->save();

                $this->releaseSeats($booking);
            }

            return $payment;
        });
    }

    public function handlePaymentTimeout(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                return;
            }

            Log::info("Expiring booking {$booking->id} due to payment timeout ({$booking->payment_expires_at})");
            $booking->status = BookingStatus::EXPIRED;
            $booking->qr_payload = null;
            $booking->qr_code = null;
            $booking->save();

            $this->releaseSeats($booking);
        });
    }

    /**
     * Late payment: booking EXPIRED nhưng gateway vừa báo success.
     * - Nếu seat vẫn AVAILABLE => BOOKED lại & CONFIRMED + cộng điểm.
     * - Nếu seat đã bị người khác book => FAILED + tự động refund.
     */
    public function handleLatePayment(Payment $payment, ?float $gatewayAmount, ?string $gatewayTxnId): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayAmount, $gatewayTxnId) {
            $booking = $payment->booking;

            $expectedGatewayAmount = $this->resolveGatewayAmount($payment);
            if (
                $gatewayAmount !== null && $expectedGatewayAmount !== null
                && (float) $expectedGatewayAmount !== (float) $gatewayAmount
            ) {

                $currency = $this->resolveGatewayCurrency($payment);
                Log::error("Gateway amount mismatch for late payment. Booking {$booking->id}, Expected {$expectedGatewayAmount} {$currency}, got {$gatewayAmount} {$currency}");
                return $this->handleFailedPayment($payment, 'Gateway amount mismatch - payment received after expiry');
            }

            $seatIds = $booking->bookingSeats()->pluck('showtime_seat_id')->all();
            $seats   = $this->showtimeSeatModel->newQuery()->whereIn('id', $seatIds)->get();

            $allAvailable = $seats->every(fn($seat) => $seat->status === SeatStatus::AVAILABLE);

            if ($allAvailable) {
                Log::info("Re-acquiring seats for late payment. Booking {$booking->id}");

                $this->showtimeSeatModel->newQuery()
                    ->whereIn('showtime_seat_id', $seatIds)
                    ->update(['status' => SeatStatus::BOOKED]);

                $booking->status = BookingStatus::CONFIRMED;
                $booking->qr_payload = $this->generateQrPayload($booking);
                $booking->payment_expires_at = null;

                if (!$booking->loyalty_points_awarded) {
                    $this->userService->addLoyaltyPoints($booking->user_id, (float) $booking->final_price);
                    $booking->loyalty_points_awarded = true;
                }

                $booking->save();

                $payment->status = PaymentStatus::SUCCESS;
                $payment->completed_at = Carbon::now();
                if ($gatewayTxnId) {
                    $payment->transaction_id = $gatewayTxnId;
                }
                $payment->save();

                Log::info("Successfully processed late payment for booking {$booking->id}");
                return $payment;
            }

            // seats đã bị người khác book –> Failed & auto-refund
            Log::warning("Cannot re-acquire seats for late payment. Booking {$booking->id}, seats already taken");

            $payment->status = PaymentStatus::FAILED;
            $payment->error_message =
                "Payment received after booking expired and seats were re-booked by another user. Refund will be processed automatically.";
            $payment->save();

            try {
                Log::info("Triggering automatic refund for late payment {$payment->id}");
                $this->refundService->processAutomaticRefund($payment, 'Seats no longer available - booking expired');
            } catch (\Throwable $e) {
                Log::error("Automatic refund failed for payment {$payment->id}. Manual intervention required.", [
                    'exception' => $e,
                ]);
                $payment->error_message .= ' Automatic refund failed - please contact support.';
                $payment->save();
            }

            return $payment;
        });
    }

    protected function releaseSeats(Booking $booking): void
    {
        $seatIds = $booking->bookingSeats()->pluck('showtime_seat_id')->all();

        if (empty($seatIds)) {
            return;
        }

        $this->showtimeSeatModel->newQuery()
            ->whereIn('showtime_seat_id', $seatIds)
            ->update(['status' => SeatStatus::AVAILABLE]);
    }

    protected function generateQrPayload(Booking $booking): string
    {
        $raw = $booking->id . ':' . $booking->user_id . ':' . microtime(true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function handleRefundSuccess(Payment $payment, Refund $refund, string $gatewayTxnId): void
    {
        DB::transaction(function () use ($payment, $refund, $gatewayTxnId) {
            $booking = $payment->booking;

            $this->releaseSeats($booking);

            if ($booking->loyalty_points_awarded) {
                $this->userService->revokeLoyaltyPoints($booking->user_id, (float) $booking->final_price);
                $booking->loyalty_points_awarded = false;
            }

            $booking->status = BookingStatus::REFUNDED;
            $booking->refunded = true;
            $booking->refunded_at = Carbon::now();
            $booking->refund_reason = $refund->reason;
            $booking->qr_payload = null;
            $booking->qr_code = null;
            $booking->save();

            $payment->status = PaymentStatus::REFUNDED;
            $payment->save();

            $refund->refund_gateway_txn_id = $gatewayTxnId;
            $refund->refunded_at = Carbon::now();
            $refund->save();
        });
    }

    public function handleRefundFailure(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $booking = $payment->booking;

            $payment->status = PaymentStatus::REFUND_FAILED;
            $payment->error_message = $reason;
            $payment->save();

            if ($booking->status === BookingStatus::REFUND_PENDING) {
                $booking->status = BookingStatus::CONFIRMED;
                $booking->save();
            }
        });
    }

    protected function resolveGatewayAmount(Payment $payment): ?float
    {
        if ($payment->gateway_amount !== null) {
            return (float) $payment->gateway_amount;
        }

        if ($payment->currency && strtoupper($payment->currency) === strtoupper(config('currency.base_currency', 'VND'))) {
            return (float) $payment->amount;
        }

        return null;
    }

    protected function resolveGatewayCurrency(Payment $payment): string
    {
        if ($payment->gateway_currency) {
            return $payment->gateway_currency;
        }

        if ($payment->currency) {
            return $payment->currency;
        }

        return config('currency.base_currency', 'VND');
    }
}
