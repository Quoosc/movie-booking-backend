<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use App\Helpers\SessionHelper;
use App\Http\Requests\LockSeatsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SeatLockController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected SessionHelper $sessionHelper
    ) {}

    /**
     * POST /api/seat-locks
     */
    public function lockSeats(LockSeatsRequest $request, Request $httpRequest): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($httpRequest);
        
        $result = $this->bookingService->lockSeats($request->validated(), $sessionContext);

        return response()->json([
            'code' => 201,
            'message' => 'Seats locked successfully',
            'data' => $result,
        ], 201)->header('X-Lock-Owner-Type', $sessionContext->getLockOwnerType()->value);
    }

    /**
     * DELETE /api/seat-locks/showtime/{showtimeId}
     */
    public function releaseSeats(string $showtimeId, Request $httpRequest): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContext($httpRequest);
        
        $this->bookingService->releaseSeats(
            $sessionContext->getLockOwnerId(),
            $showtimeId
        );

        return response()->json([
            'code' => 200,
            'message' => 'Seat locks released',
        ]);
    }

    /**
     * GET /api/seat-locks/availability/showtime/{showtimeId}
     */
    public function checkAvailability(string $showtimeId, Request $httpRequest): JsonResponse
    {
        $sessionContext = $this->sessionHelper->extractSessionContextOptional($httpRequest);
        
        $result = $this->bookingService->checkAvailability($showtimeId, $sessionContext);

        return response()->json([
            'code' => 200,
            'message' => 'Seat availability retrieved',
            'data' => $result,
        ]);
    }
}

