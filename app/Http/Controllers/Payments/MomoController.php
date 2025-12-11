<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\MomoService;
use Illuminate\Http\Request;

class MomoController extends Controller
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
        $this->momoService->handleIpn($request->all());
        // Momo thường không cần body JSON đặc biệt
        return response()->json(['result' => 0, 'message' => 'OK']);
    }

    // GET /api/payments/momo/verify?orderId=...
    public function verifyPayment(Request $request)
    {
        $orderId = $request->query('orderId');
        $result  = $this->momoService->verifyPayment($orderId);

        return $this->respond($result, 'Momo payment verified');
    }
}
