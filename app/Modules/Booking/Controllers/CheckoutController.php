<?php

namespace App\Modules\Booking\Controllers;

use App\Core\Http\Controllers\BaseController;

use App\Modules\Booking\Services\CheckoutService;
use App\Core\Helpers\SessionHelper;
use App\Modules\Booking\Requests\CheckoutPaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends BaseController
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
