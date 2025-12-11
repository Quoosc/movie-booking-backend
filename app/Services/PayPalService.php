<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\DTO\Payments\InitiatePaymentRequest;
use App\DTO\Payments\InitiatePaymentResponse;
use App\DTO\Payments\PaymentResponse;
use App\Exceptions\CustomException;
use App\Exceptions\ResourceNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PayPalService
{
    public function __construct(
        protected Payment                 $paymentModel,
        protected ExchangeRateService     $exchangeRateService,
    ) {}

    protected function checkoutLifecycleService(): CheckoutLifecycleService
    {
        return app(CheckoutLifecycleService::class);
    }

    protected function returnUrl(): string
    {
        return config('paypal.return_url');
    }
    protected function cancelUrl(): string
    {
        return config('paypal.cancel_url');
    }
    protected function baseCurrency(): string
    {
        return config('currency.base_currency', 'VND');
    }
    protected function paypalCurrency(): string
    {
        return config('currency.paypal_currency', 'USD');
    }

    public function createOrder(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        return DB::transaction(function () use ($request) {
            /** @var Booking $booking */
            $booking = app(BookingService::class)->getBookingById($request->bookingId);

            if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                throw new CustomException(
                    'Booking must be pending payment before PayPal initiation',
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ((float) $request->amount !== (float) $booking->final_price) {
                throw new CustomException(
                    'Payment amount does not match booking total',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $conversion = $this->exchangeRateService->convert(
                (float) $booking->final_price,
                $this->baseCurrency(),
                $this->paypalCurrency()
            );

            $paypalAmount = $conversion->targetAmount;

            /** @var Payment|null $existing */
            $existing = $this->paymentModel->newQuery()
                ->where('booking_id', $booking->booking_id)
                ->where('method', PaymentMethod::PAYPAL)
                ->where('status', PaymentStatus::PENDING)
                ->first();

            $payment = $existing ?: new Payment();
            $payment->amount          = $conversion->sourceAmount;
            $payment->currency        = $conversion->sourceCurrency;
            $payment->gateway_amount  = $paypalAmount;
            $payment->gateway_currency = $conversion->targetCurrency;
            $payment->exchange_rate   = $conversion->rate;
            $payment->status          = PaymentStatus::PENDING;
            $payment->method          = PaymentMethod::PAYPAL;
            $payment->booking_id      = $booking->booking_id;
            $payment->user_id         = $booking->user_id;
            $payment->created_at      = now();
            $payment->save();

            // TODO: Gọi PayPal SDK PHP để tạo Order
            //      - Set intent CAPTURE
            //      - Reference id = booking.id
            //      - amount = paypalAmount (2 decimal), currency = paypalCurrency()
            //
            // Giả sử sau khi gọi SDK bạn nhận được:
            //      $paypalOrderId
            //      $approvalUrl
            //
            // Ở đây mình sẽ fake 2 giá trị đó cho đúng flow, bạn thay bằng dữ liệu từ SDK:

            $paypalOrderId = 'PAYPAL_ORDER_' . $payment->id; // TODO: thay = real order id
            $approvalUrl   = 'https://www.paypal.com/checkoutnow?token=' . $paypalOrderId; // TODO

            $payment->transaction_id = $paypalOrderId;
            $payment->save();

            return new InitiatePaymentResponse(
                paymentId: $payment->id,
                paypalOrderId: $paypalOrderId,
                momoOrderId: null,
                approvalUrl: $approvalUrl,
            );
        });
    }

    public function captureOrder(string $orderId): PaymentResponse
    {
        return DB::transaction(function () use ($orderId) {
            /** @var Payment|null $payment */
            $payment = $this->paymentModel->newQuery()
                ->where('transaction_id', $orderId)
                ->first();

            if (!$payment) {
                throw new ResourceNotFoundException('Payment not found with transactionId ' . $orderId);
            }

            if ($payment->status !== PaymentStatus::PENDING) {
                throw new CustomException('Payment has already been processed', Response::HTTP_CONFLICT);
            }

            // TODO: Gọi SDK PayPal để capture order:
            //  - nếu status COMPLETED => thành công
            //  - lấy gatewayAmount thực tế, transaction capture id
            //
            // Giả sử kết quả:
            //      $status = 'COMPLETED' | 'FAILED'
            //      $captureId = '...'
            //      $capturedAmount = float|null

            // === BẮT ĐẦU MOCK để giữ đúng flow ===
            $status         = 'COMPLETED';             // TODO: lấy từ PayPal SDK
            $captureId      = 'CAPTURE_' . $orderId;   // TODO
            $capturedAmount = (float) $payment->gateway_amount;
            // === KẾT THÚC MOCK ===

            if (strtoupper($status) === 'COMPLETED') {
                if ($capturedAmount !== null) {
                    $payment->gateway_amount   = $capturedAmount;
                    $payment->gateway_currency = $this->paypalCurrency();
                    $payment->save();
                }

                $updated = $this->checkoutLifecycleService()
                    ->handleSuccessfulPayment($payment, $capturedAmount, $captureId);
            } else {
                $updated = $this->checkoutLifecycleService()
                    ->handleFailedPayment($payment, 'PayPal capture status: ' . $status);
            }

            return \App\Transformers\PaymentTransformer::toPaymentResponse($updated);
        });
    }

    public function refundPayment(Payment $payment, float $amount, ?string $reason): string
    {
        // TODO: Tích hợp PayPal Refund API thật (PayPal PHP SDK).
        // Tạm thời mock lại cho đúng flow:
        Log::info("Mock PayPal refund for payment {$payment->id}, amount {$amount}");
        return 'MOCK_REFUND_' . $payment->id;
    }
}
