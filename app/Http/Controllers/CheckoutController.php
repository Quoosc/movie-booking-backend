<?php

namespace App\Http\Controllers;

use App\Services\CheckoutLifecycleService;
use App\DTO\SessionContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutLifecycleService $checkoutService
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
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'lockId'        => 'required|string',
            'promotionCode' => 'nullable|string',
            'snackCombos'   => 'array',
            'snackCombos.*.snackId'  => 'required_with:snackCombos|string|exists:snacks,snack_id',
            'snackCombos.*.quantity' => 'required_with:snackCombos|integer|min:1',
            'paymentMethod' => 'required|string|in:MOMO,PAYPAL',
            'guestInfo'              => 'array',
            'guestInfo.email'        => 'required_with:guestInfo|email|max:255',
            'guestInfo.username'     => 'required_with:guestInfo|string|max:255',
            'guestInfo.phoneNumber'  => 'required_with:guestInfo|string|max:30',
        ]);

        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $sessionContext = $user 
            ? SessionContext::forUser($user->user_id)
            : ($sessionId ? SessionContext::forGuest($sessionId) : null);

        if (!$sessionContext) {
            return response()->json([
                'code' => 401,
                'message' => 'Authentication required: provide JWT token or X-Session-Id header',
            ], 401);
        }

        $result = $this->checkoutService->checkout($data, $sessionContext);

        return $this->respond($result, 'Booking confirmed and payment initiated', Response::HTTP_CREATED);
    }
}
