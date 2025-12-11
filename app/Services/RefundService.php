<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CustomException;
use App\Exceptions\ResourceNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RefundService
{
    public function __construct(
        protected Payment                $paymentModel,
        protected Booking                $bookingModel,
        protected Refund                 $refundModel,
        protected PayPalService          $payPalService,
        protected MomoService            $momoService,
    ) {}

    protected function checkoutLifecycleService(): CheckoutLifecycleService
    {
        return app(CheckoutLifecycleService::class);
    }

    public function processRefund(string $paymentId, ?string $reason): Payment
    {
        /** @var Payment|null $payment */
        $payment = $this->paymentModel->newQuery()->find($paymentId);
        if (!$payment) {
            throw new ResourceNotFoundException('Payment not found');
        }

        $booking = $payment->booking;
        $this->validateRefundEligibility($payment, $booking);

        return $this->executeRefund($payment, $booking, $reason);
    }

    public function processAutomaticRefund(Payment $payment, string $reason): Payment
    {
        $booking = $payment->booking;

        Log::info("Processing automatic refund for payment {$payment->id} due to: {$reason}");

        if (!in_array($payment->status, [PaymentStatus::FAILED, PaymentStatus::COMPLETED], true)) {
            throw new CustomException(
                "Cannot auto-refund payment with status: {$payment->status->value}",
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->executeRefund($payment, $booking, $reason);
    }

    protected function executeRefund(Payment $payment, Booking $booking, ?string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $booking, $reason) {
            $originalPaymentStatus = $payment->status;
            $originalBookingStatus = $booking->status;

            $payment->status = PaymentStatus::REFUND_PENDING;
            $payment->save();

            $booking->status = BookingStatus::CANCELLED;
            $booking->save();

            /** @var Refund $refund */
            $refund = $this->refundModel->newQuery()->create([
                'payment_id'        => $payment->id,
                'amount'            => $booking->final_price,
                'refund_method'     => $payment->method->value,
                'reason'            => $reason,
            ]);

            try {
                $amountFloat = (float) $refund->amount;
                $gatewayTxnId = match ($payment->method) {
                    \App\Enums\PaymentMethod::PAYPAL => $this->payPalService->refundPayment($payment, $amountFloat, $reason),
                    \App\Enums\PaymentMethod::MOMO   => $this->momoService->refundPayment($payment, $amountFloat, $reason),
                };

                $this->checkoutLifecycleService()->handleRefundSuccess($payment, $refund, $gatewayTxnId);

                Log::info("Refund successful for payment {$payment->id}, gateway txn: {$gatewayTxnId}");
            } catch (\Throwable $ex) {
                Log::error("Refund failed for payment {$payment->id}", ['exception' => $ex]);

                $payment->status = $originalPaymentStatus;
                $payment->save();

                $booking->status = $originalBookingStatus;
                $booking->save();

                $this->checkoutLifecycleService()->handleRefundFailure($payment, $ex->getMessage());

                throw new CustomException('Refund failed: ' . $ex->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $ex);
            }

            return $payment;
        });
    }

    protected function validateRefundEligibility(Payment $payment, Booking $booking): void
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            throw new CustomException('Only confirmed bookings can be refunded', Response::HTTP_BAD_REQUEST);
        }
        if ($payment->status !== PaymentStatus::COMPLETED) {
            throw new CustomException('Only successful payments can be refunded', Response::HTTP_BAD_REQUEST);
        }
        if ($booking->refunded) {
            throw new CustomException('Booking already refunded', Response::HTTP_BAD_REQUEST);
        }
        if (!$payment->method) {
            throw new CustomException('Payment method unavailable for refund', Response::HTTP_BAD_REQUEST);
        }
    }
}
