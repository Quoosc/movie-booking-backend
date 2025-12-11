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

        $sessionContext = $user 
            ? SessionContext::forUser($user->user_id)
            : ($sessionId ? SessionContext::forGuest($sessionId) : null);

        if (!$sessionContext) {
            return response()->json([
                'code' => 401,
                'message' => 'Authentication required: provide JWT token or X-Session-Id header',
            ], 401);
        }

        $dto = new \App\DTO\Bookings\LockSeatsRequest(
            showtimeId: $data['showtimeId'],
            seats: $data['seats']
        );

        $result = $this->bookingService->lockSeats($dto, $sessionContext);

        return response()->json([
            'code'    => 200,
            'message' => 'OK',
            'data'    => $result,
        ]);
    }

    // GET /api/seat-locks/availability/showtime/{showtimeId}
    public function checkAvailability(string $showtimeId, Request $request)
    {
        $user = $request->user();
        $sessionId = $request->header('X-Session-Id');

        $sessionContext = null;
        if ($user) {
            $sessionContext = SessionContext::forUser($user->user_id);
        } elseif ($sessionId) {
            $sessionContext = SessionContext::forGuest($sessionId);
        }

        $result = $this->bookingService->checkAvailability($showtimeId, $sessionContext);

        return $this->respond($result);
    }

    // DELETE /api/seat-locks/showtime/{showtimeId}
    public function releaseSeats(string $showtimeId)
    {
        $this->bookingService->releaseSeats($showtimeId);

        return $this->respond(null, 'Seat locks released');
    }
}
