<?php

namespace App\Repositories;

use App\Models\ShowtimeSeat;
use App\Enums\SeatStatus;
use Illuminate\Support\Collection;

class ShowtimeSeatRepository
{
    public function findByIdsAndShowtime(array $seatIds, string $showtimeId): Collection
    {
        return ShowtimeSeat::with('seat')
            ->whereIn('showtime_seat_id', $seatIds)
            ->where('showtime_id', $showtimeId)
            ->get();
    }

    public function findByShowtimeId(string $showtimeId): Collection
    {
        return ShowtimeSeat::with('seat')
            ->where('showtime_id', $showtimeId)
            ->orderBy('seat_id')
            ->get();
    }

    public function updateStatusBatch(array $seatIds, string $status): int
    {
        return ShowtimeSeat::whereIn('showtime_seat_id', $seatIds)
            ->update(['status' => $status]);
    }
}
