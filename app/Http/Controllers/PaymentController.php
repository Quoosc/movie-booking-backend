<?php

namespace App\Http\Controllers;

use App\Services\{CheckoutLifecycleService, MomoService, PayPalService};
use App\Repositories\PaymentRepository;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        protected CheckoutLifecycleService $lifecycleService,
        protected MomoService $momoService,
        protected PayPalService $paypalService,
        protected PaymentRepository $paymentRepo
    ) {}

    /**
     * POST /api/payments/order
     * Initiate payment (after booking confirmed)
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        \Log::info('[payments/order] request', $request->all());

        $data = $request->validate([
            'bookingId' => 'required|uuid|exists:bookings,booking_id',
            'paymentMethod' => 'required|in:MOMO,PAYPAL',
            'amount' => 'required|numeric|min:0',
        ]);

        $method = PaymentMethod::from($data['paymentMethod']);
        $paymentRequest = new \App\DTO\Payments\InitiatePaymentRequest(
            bookingId: $data['bookingId'],
            amount: (float) $data['amount']
        );

        $response = $method === PaymentMethod::MOMO
            ? $this->momoService->createOrder($paymentRequest)
            : $this->paypalService->createOrder($paymentRequest);

        return response()->json([
            'paymentId' => $response->paymentId,
            'orderId' => $response->paypalOrderId ?? $response->momoOrderId,
            'txnRef' => $response->paypalOrderId ?? $response->momoOrderId,
            'paymentUrl' => $response->approvalUrl,
            'message' => 'Payment initiated',
        ]);
    }

    /**
     * POST /api/payments/order/capture
     * Capture payment (confirm + award loyalty points)
     */
    public function capturePayment(Request $request): JsonResponse
    {
        \Log::info('[payments/order/capture] request', $request->all());

        $data = $request->validate([
            'transactionId' => 'required|string',
            'paymentMethod' => 'required|in:MOMO,PAYPAL',
        ]);

        $method = PaymentMethod::from($data['paymentMethod']);

        if ($method === PaymentMethod::PAYPAL) {
            $response = $this->paypalService->captureOrder($data['transactionId']);

            return response()->json($response->toArray());
        }

        // Momo auto-captures via IPN
        return response()->json([
            'message' => 'Momo payments are captured automatically via IPN',
        ], 400);
    }

    /**
     * GET /api/payments/search
     * Search user's payments (JWT required)
     */
    public function searchPayments(Request $request): JsonResponse
    {
        $user = $request->user();

        $filters = $request->validate([
            'bookingId' => 'nullable|uuid',
            'userId' => 'nullable|uuid',
            'status' => 'nullable|string',
            'method' => 'nullable|string',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $normalizedStatus = null;
        if (!empty($filters['status'])) {
            $rawStatus = strtoupper(trim($filters['status']));
            $statusMap = [
                'SUCCESS' => 'COMPLETED',
                'REFUND_FAILED' => 'FAILED',
                'COMPLETED' => 'COMPLETED',
                'PENDING' => 'PENDING',
                'FAILED' => 'FAILED',
                'REFUND_PENDING' => 'REFUND_PENDING',
                'REFUNDED' => 'REFUNDED',
                'CANCELLED' => 'CANCELLED',
            ];
            $normalizedStatus = $statusMap[$rawStatus] ?? '__INVALID__';
        }

        $normalizedMethod = null;
        if (!empty($filters['method'])) {
            $rawMethod = strtoupper(trim($filters['method']));
            $methodMap = [
                'PAYPAL' => 'PAYPAL',
                'MOMO' => 'MOMO',
            ];
            $normalizedMethod = $methodMap[$rawMethod] ?? '__INVALID__';
        }

        if ($normalizedStatus === '__INVALID__' || $normalizedMethod === '__INVALID__') {
            return response()->json([
                'code' => 200,
                'data' => [],
            ]);
        }

        if ($normalizedStatus) {
            $filters['status'] = $normalizedStatus;
        } else {
            unset($filters['status']);
        }

        if ($normalizedMethod) {
            $filters['method'] = $normalizedMethod;
        } else {
            unset($filters['method']);
        }

        if ($user->hasRole('ADMIN')) {
            $payments = $this->paymentRepo->searchPayments($filters);
        } else {
            unset($filters['userId']);
            $payments = $this->paymentRepo->searchPaymentsByUser($user->user_id, $filters);
        }

        return response()->json([
            'code' => 200,
            'data' => $payments->map(fn($p) => [
                'paymentId' => $p->payment_id,
                'bookingId' => $p->booking_id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => \App\Transformers\PaymentTransformer::mapStatusForResponse($p->status->value ?? $p->status),
                'method' => $p->method->value,
                'createdAt' => $p->created_at->toIso8601String(),
                'completedAt' => $p->completed_at?->toIso8601String(),
            ])->values()->toArray(),
        ]);
    }

    /**
     * GET /api/payments/momo/ipn
     * Momo IPN callback (GET method)
     */
    public function handleMomoIpnGet(Request $request): JsonResponse
    {
        $response = $this->momoService->processIpn($request->query());

        return response()->json([
            'resultCode' => $response->resultCode,
            'message' => $response->message,
        ]);
    }

    /**
     * POST /api/payments/momo/ipn
     * Momo IPN callback (POST method)
     */
    public function handleMomoIpnPost(Request $request): JsonResponse
    {
        $response = $this->momoService->processIpn($request->all());

        return response()->json([
            'resultCode' => $response->resultCode,
            'message' => $response->message,
        ]);
    }
}
