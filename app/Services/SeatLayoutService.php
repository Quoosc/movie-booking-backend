<?php

namespace App\Services;

use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class SeatLayoutService
{
    public function __construct(
        protected Showtime $showtimeModel,
        protected ShowtimeSeat $showtimeSeatModel,
    ) {}

    /**
     * Trả về danh sách SeatLayoutResponse[] cho 1 showtime.
     *
     * Mỗi phần tử:
     *  - seatId  (UUID của seat)
     *  - row     (row_label)
     *  - number  (seat_number)
     *  - type    (seat_type: NORMAL / VIP / COUPLE)
     *  - status  (AVAILABLE / LOCKED / BOOKED)
     */
    public function getSeatLayout(string $showtimeId): array
    {
        // đảm bảo showtime tồn tại (giống Spring: 404 nếu không có)
        /** @var Showtime $showtime */
        $showtime = $this->showtimeModel
            ->newQuery()
            ->with(['room', 'room.cinema'])
            ->findOrFail($showtimeId);

        /** @var Collection<int, ShowtimeSeat> $showtimeSeats */
        $showtimeSeats = $this->showtimeSeatModel
            ->newQuery()
            ->with(['seat', 'seatLockSeats.seatLock'])
            ->where('showtime_id', $showtimeId)
            ->get();

        $now = Carbon::now();
        $result = [];

        foreach ($showtimeSeats as $stSeat) {
            $seat = $stSeat->seat;
            if (!$seat) {
                continue;
            }

            // status trong showtime_seats (cast enum SeatStatus hoặc string)
            $statusEnum = $stSeat->status;
            $status = is_object($statusEnum) ? $statusEnum->value : (string) $statusEnum;

            // Nếu có lock còn hạn thì override thành LOCKED
            $hasActiveLock = $stSeat->seatLockSeats->contains(function ($lockSeat) use ($now) {
                $lock = $lockSeat->seatLock;
                return $lock
                    && $lock->expires_at
                    && $lock->expires_at->greaterThan($now)
                    && (!$lock->released_at);
            });

            if ($hasActiveLock && $status === 'AVAILABLE') {
                $status = 'LOCKED';
            }

            $result[] = [
                'seatId' => (string) $seat->seat_id,
                'row'    => $seat->row_label,
                'number' => (int) $seat->seat_number,
                'type'   => $seat->seat_type,   // NORMAL / VIP / COUPLE
                'status' => $status,            // AVAILABLE / LOCKED / BOOKED
            ];
        }

        return $result;
    }
}
