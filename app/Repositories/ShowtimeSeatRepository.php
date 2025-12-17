<?php

namespace App\Repositories;

use App\Models\ShowtimeSeat;
use Illuminate\Support\Collection;

class ShowtimeSeatRepository
{
    public function findByIdsAndShowtime(array $seatIds, string $showtimeId): Collection
    {
        return ShowtimeSeat::query()
            ->with('seat')
            ->where('showtime_id', $showtimeId)
            ->whereIn('showtime_seat_id', $seatIds)
            ->lockForUpdate() // rất nên có khi lock seat trong transaction
            ->get();
    }

    public function findByShowtimeId(string $showtimeId): Collection
    {
        return ShowtimeSeat::query()
            ->with('seat')
            ->where('showtime_id', $showtimeId)
            ->orderBy('seat_id')
            ->get();
    }

    public function updateStatusBatch(array $seatIds, string $status): int
    {
        if (empty($seatIds)) return 0;

        return ShowtimeSeat::query()
            ->whereIn('showtime_seat_id', $seatIds)
            ->update([
                'seat_status' => $status,
                'updated_at'  => now(),
            ]);
    }
}
