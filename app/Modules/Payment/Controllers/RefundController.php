<?php

namespace App\Modules\Payment\Controllers;

use App\Core\Http\Controllers\BaseController;

use App\Modules\Payment\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends BaseController
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
