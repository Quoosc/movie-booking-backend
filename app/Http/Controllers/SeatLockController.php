<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use Illuminate\Http\Request;
use App\DTO\SessionContext;


class SeatLockController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // POST /api/seat-locks
    public function lockSeats(Request $request)
    {
        $data = $request->validate([
            'showtimeId'             => 'required|string|exists:showtimes,showtime_id',
            'seats'                  => 'required|array|min:1',
            'seats.*.showtimeSeatId' => 'required|string|exists:showtime_seats,showtime_seat_id',
            'seats.*.ticketTypeId'   => 'nullable|string|exists:ticket_types,id',
        ]);

        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $sessionContext = new SessionContext(
            userId: $user?->user_id,
            sessionId: $sessionId
        );

        $result = $this->bookingService->lockSeats($data, $sessionContext);

        return response()->json([
            'code'    => 200,
            'message' => 'OK',
            'data'    => $result,
        ]);
    }

    // GET /api/seat-locks/availability/showtime/{showtimeId}
    public function checkAvailability(string $showtimeId)
    {
        $result = $this->bookingService->checkAvailability($showtimeId);

        return $this->respond($result);
    }

    // DELETE /api/seat-locks/showtime/{showtimeId}
    public function releaseSeats(string $showtimeId)
    {
        $this->bookingService->releaseSeats($showtimeId);

        return $this->respond(null, 'Seat locks released');
    }
}
