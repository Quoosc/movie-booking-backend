<?php

namespace App\Http\Controllers;

use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/payments/{paymentId}/refund
    public function refund(string $paymentId, Request $request)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->refundService->refundPayment($paymentId, $data['reason'] ?? null);

        return $this->respond($result, 'Refund processed');
    }
}
