<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\PayPalService;
use Illuminate\Http\Request;

class PayPalController extends Controller
{
    public function __construct(
        protected PayPalService $payPalService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/payments/paypal/create-order
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'bookingId' => 'required|string|exists:bookings,booking_id',
        ]);

        $paymentResponse = $this->payPalService->createOrder($data['bookingId']);

        return $this->respond($paymentResponse, 'PayPal order created');
    }

    // POST /api/payments/paypal/capture-order
    public function captureOrder(Request $request)
    {
        $data = $request->validate([
            'paymentId'   => 'required|string|exists:payments,payment_id',
            'paypalToken' => 'required|string',
        ]);

        $result = $this->payPalService->captureOrder($data['paymentId'], $data['paypalToken']);

        return $this->respond($result, 'PayPal order captured');
    }
}
