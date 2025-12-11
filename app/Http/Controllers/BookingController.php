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
        try {
            \Illuminate\Support\Facades\Log::info('=== PRICE PREVIEW START ===', $request->all());

            $sessionContext = $this->sessionHelper->extractSessionContext($request);
            \Illuminate\Support\Facades\Log::info('Session context extracted', ['context' => $sessionContext]);

            $result = $this->bookingService->calculatePricePreview(
                $request->validated(),
                $sessionContext
            );

            \Illuminate\Support\Facades\Log::info('=== PRICE PREVIEW SUCCESS ===', ['result' => $result]);

            return response()->json([
                'code' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('=== PRICE PREVIEW ERROR ===', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ], 500);
        }
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
    public function getUserBookings(): JsonResponse
    {
        $user = request()->user();

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
    public function getBookingById(string $bookingId): JsonResponse
    {
        $user = request()->user();

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
    public function updateQrCode(string $bookingId, UpdateQrCodeRequest $request): JsonResponse
    {
        $user = request()->user();

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
