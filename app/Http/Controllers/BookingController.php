<?php

namespace App\Http\Controllers;

use App\Services\{BookingService, CheckoutService};
use App\Helpers\SessionHelper;
use App\Http\Requests\{PricePreviewRequest, ConfirmBookingRequest, UpdateQrCodeRequest};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected CheckoutService $checkoutService,
        protected SessionHelper $sessionHelper
    ) {}

    /**
     * POST /api/bookings/price-preview
     */
    public function pricePreview(PricePreviewRequest $request): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($request);

        $result = $this->bookingService->calculatePricePreview(
            $request->validated(),
            $sessionContext
        );

        return response()->json($result);
    }

    /**
     * POST /api/bookings/confirm
     */
    public function confirmBooking(ConfirmBookingRequest $request): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($request);

        $result = $this->checkoutService->confirmBooking(
            $request->validated(),
            $sessionContext
        );

        return response()->json($result, 200);
    }

    /**
     * GET /api/bookings/my-bookings
     * Middleware: auth:api
     */
    public function getUserBookings(): JsonResponse
    {
        $user = request()->user();

        $result = $this->bookingService->getUserBookings($user->user_id);

        return response()->json($result);
    }

    /**
     * GET /api/bookings/{bookingId}
     * Middleware: auth:api
     */
    public function getBookingById(string $bookingId): JsonResponse
    {
        $user = request()->user();

        $result = $this->bookingService->getBookingByIdForUser($bookingId, $user->user_id);

        return response()->json($result);
    }

    /**
     * PATCH /api/bookings/{bookingId}/qr
     * Middleware: auth:api
     */
    public function updateQrCode(string $bookingId, UpdateQrCodeRequest $request): JsonResponse
    {
        $user = request()->user();

        $result = $this->bookingService->updateQrCode(
            $bookingId,
            $user->user_id,
            $request->validated()['qrCodeUrl']
        );

        return response()->json($result);
    }
}
