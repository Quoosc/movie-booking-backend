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
    public function pricePreview(PricePreviewRequest $request, Request $httpRequest): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($httpRequest);
        
        $result = $this->bookingService->calculatePricePreview(
            $request->validated(),
            $sessionContext
        );

        return response()->json([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * POST /api/bookings/confirm
     */
    public function confirmBooking(ConfirmBookingRequest $request, Request $httpRequest): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($httpRequest);
        
        $result = $this->checkoutService->confirmBooking(
            $request->validated(),
            $sessionContext
        );

        return response()->json([
            'code' => 201,
            'message' => 'Booking confirmed',
            'data' => $result,
        ], 201);
    }

    /**
     * GET /api/bookings/my-bookings
     * Middleware: auth:api
     */
    public function getUserBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->bookingService->getUserBookings($user->user_id);

        return response()->json([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * GET /api/bookings/{bookingId}
     * Middleware: auth:api
     */
    public function getBookingById(string $bookingId, Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->bookingService->getBookingByIdForUser($bookingId, $user->user_id);

        return response()->json([
            'code' => 200,
            'data' => $result,
        ]);
    }

    /**
     * PATCH /api/bookings/{bookingId}/qr
     * Middleware: auth:api
     */
    public function updateQrCode(string $bookingId, UpdateQrCodeRequest $request, Request $httpRequest): JsonResponse
    {
        $user = $httpRequest->user();
        
        $result = $this->bookingService->updateQrCode(
            $bookingId,
            $user->user_id,
            $request->validated()['qrCodeUrl']
        );

        return response()->json([
            'code' => 200,
            'message' => 'QR code updated',
            'data' => $result,
        ]);
    }
}

