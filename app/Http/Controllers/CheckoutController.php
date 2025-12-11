<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/checkout
    public function confirmAndInitiate(Request $request)
    {
        $data = $request->validate([
            'lockKey'       => 'required|string',
            'customerName'  => 'required|string|max:255',
            'customerEmail' => 'required|email|max:255',
            'customerPhone' => 'required|string|max:30',
            'paymentMethod' => 'required|string', // MOMO / PAYPAL
            'promotionCode' => 'nullable|string',
            'snacks'        => 'array',
            'snacks.*.snackId'  => 'required_with:snacks|string|exists:snacks,snack_id',
            'snacks.*.quantity' => 'required_with:snacks|integer|min:1',
        ]);

        $user = Auth::user();

        $result = $this->checkoutService->confirmBookingAndInitiatePayment($data, $user);

        return $this->respond($result, 'Checkout initiated', Response::HTTP_CREATED);
    }
}
