<?php

namespace App\Modules\Payment\Controllers\Gateways;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Payment\Services\MomoService;
use Illuminate\Http\Request;

class MomoController extends BaseController
{
    public function __construct(
        protected MomoService $momoService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/payments/momo/create-order
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'bookingId' => 'required|string|exists:bookings,booking_id',
        ]);

        $paymentResponse = $this->momoService->createOrder($data['bookingId']);

        // $paymentResponse là array (PaymentResponse::toArray())
        return $this->respond($paymentResponse, 'Momo order created');
    }

    // POST /api/payments/momo/ipn
    public function ipn(Request $request)
    {
        $response = $this->momoService->processIpn($request->all());

        return response()->json([
            'resultCode' => $response->resultCode,
            'message' => $response->message,
        ]);
    }

    // GET /api/payments/momo/verify?orderId=...
    public function verifyPayment(Request $request)
    {
        $orderId = $request->query('orderId');
        $result  = $this->momoService->verifyPayment($orderId);

        return $this->respond($result, 'Momo payment verified');
    }
}
