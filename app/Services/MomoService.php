<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\DTO\Payments\InitiatePaymentRequest;
use App\DTO\Payments\InitiatePaymentResponse;
use App\DTO\Payments\IpnResponse;
use App\Exceptions\CustomException;
use App\Exceptions\ResourceNotFoundException;
use App\Support\SecurityUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Transformers\PaymentTransformer;


class MomoService
{
    public function __construct(
        protected Payment                $paymentModel,
        protected BookingService         $bookingService,
        protected CheckoutLifecycleService $checkoutLifecycleService,
    ) {}

    protected function partnerCode(): string
    {
        return config('momo.partner_code');
    }
    protected function accessKey(): string
    {
        return config('momo.access_key');
    }
    protected function secretKey(): string
    {
        return config('momo.secret_key');
    }
    protected function apiEndpoint(): string
    {
        return rtrim(config('momo.endpoint'), '/');
    }
    protected function returnUrl(): string
    {
        return config('momo.return_url');
    }
    protected function ipnUrl(): string
    {
        return config('momo.ipn_url');
    }
    protected function baseCurrency(): string
    {
        return config('currency.base_currency', 'VND');
    }

    public function createOrder(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        return DB::transaction(function () use ($request) {
            /** @var Booking $booking */
            $booking = $this->bookingService->getBookingById($request->bookingId);

            if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
                throw new CustomException(
                    'Booking must be pending payment before Momo initiation',
                    Response::HTTP_BAD_REQUEST
                );
            }

            if ((float) $request->amount !== (float) $booking->final_price) {
                throw new CustomException(
                    'Payment amount does not match booking total',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // PENDING payment reuse / create
            /** @var Payment|null $existing */
            $existing = $this->paymentModel->newQuery()
                ->where('booking_id', $booking->id)
                ->where('method', PaymentMethod::MOMO)
                ->where('status', PaymentStatus::PENDING)
                ->first();

            $payment = $existing ?: new Payment();
            $payment->method = PaymentMethod::MOMO;
            $payment->status = PaymentStatus::PENDING;
            $payment->currency = $this->baseCurrency();
            $payment->gateway_currency = $this->baseCurrency();
            $payment->gateway_amount = $request->amount;
            $payment->exchange_rate = 1;
            $payment->amount = $request->amount;
            $payment->booking_id = $booking->id;
            $payment->save();

            $orderId = (string) $payment->id;
            $requestId = $orderId;
            $orderInfo = 'Booking ' . $booking->id;
            $amountStr = (string) (int) $request->amount; // VND nguyên
            $requestType = 'captureWallet';
            $extraData = '';

            $rawSignature = 'accessKey=' . $this->accessKey()
                . '&amount=' . $amountStr
                . '&extraData=' . $extraData
                . '&ipnUrl=' . $this->ipnUrl()
                . '&orderId=' . $orderId
                . '&orderInfo=' . $orderInfo
                . '&partnerCode=' . $this->partnerCode()
                . '&redirectUrl=' . $this->returnUrl()
                . '&requestId=' . $requestId
                . '&requestType=' . $requestType;

            $signature = SecurityUtils::hmacSha256Sign($this->secretKey(), $rawSignature);

            $body = [
                'partnerCode' => $this->partnerCode(),
                'accessKey'   => $this->accessKey(),
                'requestId'   => $requestId,
                'amount'      => $amountStr,
                'orderId'     => $orderId,
                'orderInfo'   => $orderInfo,
                'redirectUrl' => $this->returnUrl(),
                'ipnUrl'      => $this->ipnUrl(),
                'requestType' => $requestType,
                'extraData'   => $extraData,
                'lang'        => 'en',
                'signature'   => $signature,
            ];

            try {
                $res = Http::asJson()->post($this->apiEndpoint() . '/create', $body);
                $json = $res->json();

                if (!$json) {
                    throw new CustomException('No response from Momo gateway', Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                if (($json['resultCode'] ?? -1) !== 0) {
                    $message = $json['message'] ?? 'Unknown error';
                    throw new CustomException("Momo payment creation failed: {$message}", Response::HTTP_BAD_REQUEST);
                }

                $payUrl = $json['payUrl'] ?? null;
                $deeplink = $json['deeplink'] ?? null;
                $qrCodeUrl = $json['qrCodeUrl'] ?? null;

                $payment->transaction_id = $orderId;
                $payment->save();

                Log::info("Momo payment created successfully for booking {$booking->id}");

                return new InitiatePaymentResponse(
                    paymentId: $payment->id,
                    paypalOrderId: null,
                    momoOrderId: $orderId,
                    approvalUrl: $payUrl,
                );
            } catch (\Throwable $e) {
                Log::error('Error creating Momo payment', ['exception' => $e]);
                throw new CustomException(
                    'Failed to create Momo payment: ' . $e->getMessage(),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    $e
                );
            }
        });
    }

    public function processIpn(array $allParams): IpnResponse
    {
        $receivedSignature = $allParams['signature'] ?? null;
        if (!$receivedSignature) {
            Log::warning('Momo IPN: Missing signature');
            return IpnResponse::invalidChecksum();
        }

        $rawSignature = 'accessKey=' . ($allParams['accessKey'] ?? '')
            . '&amount=' . ($allParams['amount'] ?? '')
            . '&extraData=' . ($allParams['extraData'] ?? '')
            . '&message=' . ($allParams['message'] ?? '')
            . '&orderId=' . ($allParams['orderId'] ?? '')
            . '&orderInfo=' . ($allParams['orderInfo'] ?? '')
            . '&orderType=' . ($allParams['orderType'] ?? '')
            . '&partnerCode=' . ($allParams['partnerCode'] ?? '')
            . '&payType=' . ($allParams['payType'] ?? '')
            . '&requestId=' . ($allParams['requestId'] ?? '')
            . '&responseTime=' . ($allParams['responseTime'] ?? '')
            . '&resultCode=' . ($allParams['resultCode'] ?? '')
            . '&transId=' . ($allParams['transId'] ?? '');

        $calculatedSignature = SecurityUtils::hmacSha256Sign($this->secretKey(), $rawSignature);
        if (!hash_equals(strtolower($calculatedSignature), strtolower($receivedSignature))) {
            Log::warning('Momo IPN: Invalid signature', [
                'calculated' => $calculatedSignature,
                'received' => $receivedSignature
            ]);
            return IpnResponse::invalidChecksum();
        }

        $orderId = $allParams['orderId'] ?? null;
        $amountStr = $allParams['amount'] ?? null;
        $resultCode = $allParams['resultCode'] ?? null;
        $transId = $allParams['transId'] ?? null;

        if (!$orderId || !$amountStr) {
            Log::warning('Momo IPN: Missing orderId or amount', $allParams);
            return IpnResponse::orderNotFound();
        }

        /** @var Payment|null $payment */
        $payment = $this->paymentModel->newQuery()
            ->where('transaction_id', $orderId)
            ->where('method', PaymentMethod::MOMO)
            ->first();

        if (!$payment) {
            Log::warning("Momo IPN: Payment not found for orderId: {$orderId}");
            return IpnResponse::orderNotFound();
        }

        $expected = (int) $payment->amount;
        if ((string) $expected !== $amountStr) {
            Log::error("Momo IPN: Amount mismatch for payment {$payment->id}", [
                'expected' => $expected,
                'received' => $amountStr
            ]);
            $this->checkoutLifecycleService->handleFailedPayment($payment, 'Momo amount mismatch');
            return IpnResponse::amountInvalid();
        }

        if ($payment->status === PaymentStatus::SUCCESS) {
            Log::info("Momo IPN: Payment {$payment->id} already confirmed");
            return IpnResponse::orderAlreadyConfirmed();
        }

        $success = ((string) $resultCode) === '0';

        if ($success) {
            if (!$transId) {
                Log::warning("Momo IPN: Missing transId for successful payment {$payment->id}");
                $transId = $orderId; // Fallback to orderId
            }

            Log::info("Momo IPN: Processing successful payment {$payment->id}, transId: {$transId}");
            $this->checkoutLifecycleService->handleSuccessfulPayment($payment, (float) $payment->amount, $transId);
            return IpnResponse::ok();
        }

        $message = $allParams['message'] ?? 'Payment failed';
        Log::warning("Momo IPN: Payment failed for {$payment->id}", [
            'resultCode' => $resultCode,
            'message' => $message
        ]);
        $this->checkoutLifecycleService->handleFailedPayment($payment, "Momo error: {$message}");
        return IpnResponse::paymentFailed();
    }

    public function verifyPayment(string $transactionId): array
    {
        /** @var Payment|null $payment */
        $payment = $this->paymentModel->newQuery()
            ->where('transaction_id', $transactionId)
            ->where('method', PaymentMethod::MOMO)
            ->first();

        if (!$payment) {
            throw new ResourceNotFoundException('Payment not found');
        }

        return PaymentTransformer::toPaymentResponse($payment);
    }

    public function refundPayment(Payment $payment, float $amount, ?string $reason): string
    {
        try {
            if ($payment->method !== PaymentMethod::MOMO) {
                throw new CustomException('Payment method is not Momo', Response::HTTP_BAD_REQUEST);
            }

            if ($payment->status !== PaymentStatus::SUCCESS) {
                throw new CustomException('Only successful payments can be refunded', Response::HTTP_BAD_REQUEST);
            }

            $orderId = $payment->transaction_id;

            // transId should be stored in gateway_transaction_id or similar field
            // If not available, we need to query Momo API first
            $transId = $payment->gateway_transaction_id ?? $payment->transaction_id;

            if (!$orderId || !$transId) {
                throw new CustomException('No Momo transaction ID found for this payment', Response::HTTP_BAD_REQUEST);
            }

            Log::info("Initiating Momo refund for payment {$payment->id} (orderId: {$orderId}, transId: {$transId}), amount: {$amount}");

            $requestId = (string) Str::uuid();
            $refundAmount = (string) (int) $amount;
            $description = $reason ?: 'Booking refund';

            $rawSignature = 'accessKey=' . $this->accessKey()
                . '&amount=' . $refundAmount
                . '&description=' . $description
                . '&orderId=' . $orderId
                . '&partnerCode=' . $this->partnerCode()
                . '&requestId=' . $requestId
                . '&transId=' . $transId;

            $signature = SecurityUtils::hmacSha256Sign($this->secretKey(), $rawSignature);

            $body = [
                'partnerCode' => $this->partnerCode(),
                'orderId'     => $orderId,
                'requestId'   => $requestId,
                'amount'      => $refundAmount,
                'transId'     => $transId,
                'lang'        => 'en',
                'description' => $description,
                'signature'   => $signature,
            ];

            $res  = Http::asJson()->post($this->apiEndpoint() . '/refund', $body);
            $json = $res->json();

            if (!$json) {
                throw new CustomException('No response from Momo refund API', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (($json['resultCode'] ?? -1) !== 0) {
                $message = $json['message'] ?? 'Unknown error';
                Log::error("Momo refund failed for payment {$payment->id}", [
                    'resultCode' => $json['resultCode'] ?? -1,
                    'message' => $message,
                    'response' => $json
                ]);
                throw new CustomException("Momo refund failed: {$message}", Response::HTTP_BAD_REQUEST);
            }

            $refundTransId = $json['transId'] ?? $requestId;
            Log::info("Momo refund completed: refundTransId={$refundTransId}");

            return $refundTransId;
        } catch (\Throwable $e) {
            Log::error("Momo refund failed for payment {$payment->id}", ['exception' => $e]);
            throw new CustomException(
                'Failed to process Momo refund: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
