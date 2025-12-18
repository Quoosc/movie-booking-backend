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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PayPalService
{
    public function __construct(
        protected Payment $paymentModel,
        protected ExchangeRateService $exchangeRateService,
    ) {}

    protected function checkoutLifecycleService(): CheckoutLifecycleService
    {
        return app(CheckoutLifecycleService::class);
    }

    protected function baseUrl(): string
    {
        $mode = config('payment.paypal.mode', 'sandbox');
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    protected function clientId(): string
    {
        return (string) config('payment.paypal.client_id');
    }

    protected function clientSecret(): string
    {
        return (string) config('payment.paypal.secret');
    }

    protected function returnUrl(): string
    {
        return (string) config('payment.paypal.return_url');
    }

    protected function cancelUrl(): string
    {
        return (string) config('payment.paypal.cancel_url');
    }

    protected function paypalCurrency(): string
    {
        return config('payment.paypal.currency', config('currency.paypal_currency', 'USD'));
    }

    protected function baseCurrency(): string
    {
        return config('currency.base_currency', 'VND');
    }

    protected function authenticate(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->ok()) {
            Log::error('PayPal auth failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new CustomException('Failed to authenticate with PayPal', Response::HTTP_BAD_GATEWAY);
        }

        return $response->json('access_token');
    }

    public function createOrder(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        return DB::transaction(function () use ($request) {
            /** @var Booking $booking */
            $booking = app(BookingService::class)->getBookingById($request->bookingId);

            if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                throw new CustomException('Booking must be pending payment before PayPal initiation', Response::HTTP_BAD_REQUEST);
            }

            if ((float) $request->amount !== (float) $booking->final_price) {
                throw new CustomException('Payment amount does not match booking total', Response::HTTP_BAD_REQUEST);
            }

            $conversion = $this->exchangeRateService->convert(
                (float) $booking->final_price,
                $this->baseCurrency(),
                $this->paypalCurrency()
            );

            // Reuse or create pending payment
            /** @var Payment|null $existing */
            $existing = $this->paymentModel->newQuery()
                ->where('booking_id', $booking->booking_id)
                ->where('method', PaymentMethod::PAYPAL)
                ->where('status', PaymentStatus::PENDING)
                ->first();

            $payment = $existing ?: new Payment();
            $payment->booking_id = $booking->booking_id;
            $payment->user_id = $booking->user_id;
            $payment->method = PaymentMethod::PAYPAL;
            $payment->status = PaymentStatus::PENDING;
            $payment->amount = $booking->final_price;
            $payment->currency = $this->baseCurrency();
            $payment->gateway_amount = $conversion->targetAmount;
            $payment->gateway_currency = $conversion->targetCurrency;
            $payment->exchange_rate = $conversion->rate;
            $payment->created_at = now();
            $payment->save();

            $accessToken = $this->authenticate();

            $orderPayload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $booking->booking_id,
                    'amount' => [
                        'currency_code' => $conversion->targetCurrency,
                        'value' => number_format($conversion->targetAmount, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $this->returnUrl(),
                    'cancel_url' => $this->cancelUrl(),
                ],
            ];

            \Log::info('[paypal] createOrder payload', [
                'bookingId' => $booking->booking_id,
                'orderPayload' => $orderPayload,
            ]);

            $res = Http::withToken($accessToken)
                ->post($this->baseUrl() . '/v2/checkout/orders', $orderPayload);

            if (!$res->successful()) {
                Log::error('PayPal create order failed', ['status' => $res->status(), 'body' => $res->body()]);
                throw new CustomException('Failed to create PayPal order', Response::HTTP_BAD_GATEWAY);
            }

            $json = $res->json();
            $paypalOrderId = $json['id'] ?? null;
            $approvalUrl = collect($json['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

            if (!$paypalOrderId || !$approvalUrl) {
                throw new CustomException('Invalid response from PayPal', Response::HTTP_BAD_GATEWAY);
            }

            $payment->order_id = $paypalOrderId;
            $payment->txn_ref = $paypalOrderId;
            $payment->transaction_id = $paypalOrderId;
            $payment->payment_url = $approvalUrl;
            $payment->gateway_response = $json;
            $payment->save();

            return new InitiatePaymentResponse(
                paymentId: $payment->payment_id,
                paypalOrderId: $paypalOrderId,
                momoOrderId: null,
                approvalUrl: $approvalUrl,
            );
        });
    }

    public function captureOrder(string $orderId): PaymentResponse
    {
        return DB::transaction(function () use ($orderId) {
            $cleanOrderId = trim($orderId);
            if (str_contains($cleanOrderId, 'token=')) {
                // In case FE accidentally passes full query, extract token param
                parse_str(parse_url($cleanOrderId, PHP_URL_QUERY) ?? $cleanOrderId, $parsed);
                $cleanOrderId = $parsed['token'] ?? $cleanOrderId;
            }

            /** @var Payment|null $payment */
            $payment = $this->paymentModel->newQuery()
                ->where('order_id', $cleanOrderId)
                ->first();

            if (!$payment) {
                throw new ResourceNotFoundException('Payment not found with orderId ' . $cleanOrderId);
            }

            $booking = $payment->booking;
            if (!$booking) {
                throw new ResourceNotFoundException('Payment has no booking');
            }

            // If already processed, return current state (idempotent)
            if ($payment->status !== PaymentStatus::PENDING) {
                $resp = \App\Transformers\PaymentTransformer::toPaymentResponse($payment);
                return new PaymentResponse(
                    paymentId: $resp['paymentId'],
                    bookingId: $resp['bookingId'],
                    bookingStatus: $resp['bookingStatus'],
                    paymentStatus: $resp['status'] ?? null,
                    qrPayload: $resp['qrPayload'] ?? null,
                );
            }

            if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                throw new CustomException('Booking is not pending payment', Response::HTTP_CONFLICT);
            }

            $accessToken = $this->authenticate();

            $captureUrl = $this->baseUrl() . "/v2/checkout/orders/{$cleanOrderId}/capture";
            \Log::info('[paypal] capture start', [
                'orderId' => $cleanOrderId,
                'paymentId' => $payment->payment_id,
                'bookingId' => $booking->booking_id,
                'captureUrl' => $captureUrl,
            ]);

            $res = Http::withToken($accessToken)
                ->post($captureUrl);

            if (!$res->successful()) {
                Log::error('PayPal capture failed', ['status' => $res->status(), 'body' => $res->body()]);
                $updated = $this->checkoutLifecycleService()
                    ->handleFailedPayment($payment, 'PayPal capture failed: ' . $res->body());
                $resp = \App\Transformers\PaymentTransformer::toPaymentResponse($updated);
                return new PaymentResponse(
                    paymentId: $resp['paymentId'],
                    bookingId: $resp['bookingId'],
                    bookingStatus: $resp['bookingStatus'],
                    paymentStatus: $resp['status'] ?? null,
                    qrPayload: $resp['qrPayload'] ?? null,
                );
            }

            $json = $res->json();
            $captures = $json['purchase_units'][0]['payments']['captures'][0] ?? null;
            $captureId = $captures['id'] ?? null;
            $capturedAmount = $captures['amount']['value'] ?? null;
            $capturedCurrency = $captures['amount']['currency_code'] ?? null;

            Log::info('[paypal] capture response', [
                'orderId' => $cleanOrderId,
                'paymentId' => $payment->payment_id,
                'captureId' => $captureId,
                'capturedAmount' => $capturedAmount,
                'capturedCurrency' => $capturedCurrency,
                'expectedAmount' => $payment->gateway_amount,
                'expectedCurrency' => $payment->gateway_currency,
            ]);

            // Validate amount/currency
            $expectedAmount = $payment->gateway_amount;
            $expectedCurrency = strtoupper((string) $payment->gateway_currency);
            $amountMismatch = false;
            if ($capturedAmount === null || $capturedCurrency === null) {
                $amountMismatch = true;
            } else {
                $amountMismatch = abs((float) $capturedAmount - (float) $expectedAmount) > 0.01
                    || strtoupper($capturedCurrency) !== $expectedCurrency;
            }

            if ($amountMismatch) {
                $updated = $this->checkoutLifecycleService()
                    ->handleFailedPayment($payment, 'PayPal amount or currency mismatch');
                $resp = \App\Transformers\PaymentTransformer::toPaymentResponse($updated);
                return new PaymentResponse(
                    paymentId: $resp['paymentId'],
                    bookingId: $resp['bookingId'],
                    bookingStatus: $resp['bookingStatus'],
                    paymentStatus: $resp['status'] ?? null,
                    qrPayload: $resp['qrPayload'] ?? null,
                );
            }

            $payment->status = PaymentStatus::COMPLETED;
            $payment->paid_at = now();
            $payment->txn_ref = $captureId;
            $payment->gateway_response = $json;
            $payment->save();

            $this->checkoutLifecycleService()
                ->handleSuccessfulPayment($payment, (float) $payment->gateway_amount, $captureId);

            $resp = \App\Transformers\PaymentTransformer::toPaymentResponse($payment);

            return new PaymentResponse(
                paymentId: $resp['paymentId'],
                bookingId: $resp['bookingId'],
                bookingStatus: $resp['bookingStatus'],
                paymentStatus: $resp['status'] ?? null,
                qrPayload: $resp['qrPayload'] ?? null,
            );
        });
    }

    public function refundPayment(Payment $payment, float $amount, ?string $reason): string
    {
        $accessToken = $this->authenticate();
        $captureId = $payment->txn_ref ?: $payment->order_id;

        if (!$captureId) {
            throw new CustomException('Missing PayPal capture id for refund', Response::HTTP_BAD_REQUEST);
        }

        $payload = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $payment->gateway_currency ?: $this->paypalCurrency(),
            ],
        ];

        if ($reason) {
            $payload['note_to_payer'] = $reason;
        }

        $res = Http::withToken($accessToken)
            ->post($this->baseUrl() . "/v2/payments/captures/{$captureId}/refund", $payload);

        if (!$res->successful()) {
            Log::error('PayPal refund failed', ['status' => $res->status(), 'body' => $res->body()]);
            throw new CustomException('PayPal refund failed', Response::HTTP_BAD_GATEWAY);
        }

        $json = $res->json();
        return $json['id'] ?? $captureId;
    }
}
