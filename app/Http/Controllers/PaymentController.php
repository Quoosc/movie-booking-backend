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
        $data = $request->validate([
            'bookingId' => 'required|uuid|exists:bookings,booking_id',
            'paymentMethod' => 'required|in:MOMO,PAYPAL',
        ]);

        $method = PaymentMethod::from($data['paymentMethod']);

        if ($method === PaymentMethod::MOMO) {
            $response = $this->momoService->createOrder($data['bookingId']);
        } else {
            $response = $this->paypalService->createOrder($data['bookingId']);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Payment initiated',
            'data' => $response,
        ]);
    }

    /**
     * POST /api/payments/order/capture
     * Capture payment (confirm + award loyalty points)
     */
    public function capturePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orderId' => 'required|string',
            'paymentMethod' => 'required|in:MOMO,PAYPAL',
        ]);

        $method = PaymentMethod::from($data['paymentMethod']);

        if ($method === PaymentMethod::PAYPAL) {
            $response = $this->paypalService->captureOrder($data['orderId']);

            return response()->json([
                'code' => 200,
                'message' => 'Payment captured',
                'data' => $response,
            ]);
        }

        // Momo auto-captures via IPN
        return response()->json([
            'code' => 400,
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
            'status' => 'nullable|in:PENDING,COMPLETED,FAILED,REFUND_PENDING,REFUNDED,CANCELLED',
            'method' => 'nullable|in:MOMO,PAYPAL',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
        ]);

        $payments = $this->paymentRepo->searchPaymentsByUser($user->user_id, $filters);

        return response()->json([
            'code' => 200,
            'data' => $payments->map(fn($p) => [
                'paymentId' => $p->payment_id,
                'bookingId' => $p->booking_id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status->value,
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
