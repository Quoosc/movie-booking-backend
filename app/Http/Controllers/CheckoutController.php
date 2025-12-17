<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use App\Helpers\SessionHelper;
use App\Http\Requests\CheckoutPaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected SessionHelper $sessionHelper
    ) {}

    /**
     * POST /api/checkout
     */
    public function confirmAndInitiate(CheckoutPaymentRequest $request): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($request);

        $result = $this->checkoutService->confirmBookingAndInitiatePayment(
            $request->validated(),
            $sessionContext
        );

        return response()->json($result, 201);
    }
}
