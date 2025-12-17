<?php

namespace App\Repositories;

use App\Models\SeatLock;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SeatLockRepository
{
    public function create(array $data): SeatLock
    {
        return SeatLock::create($data);
    }

    public function findById(string $id): ?SeatLock
    {
        return SeatLock::with([
                'seatLockSeats.showtimeSeat.seat',
                'seatLockSeats.ticketType',
                'showtime',
            ])
            ->find($id);
    }

    public function findByLockOwnerIdAndShowtimeId(string $lockOwnerId, string $showtimeId): ?SeatLock
    {
        return SeatLock::where('lock_owner_id', $lockOwnerId)
            ->where('showtime_id', $showtimeId)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function findAllActiveLocksForOwner(string $lockOwnerId): Collection
    {
        return SeatLock::where('lock_owner_id', $lockOwnerId)
            ->where('expires_at', '>', Carbon::now())
            ->get();
    }

    public function findActiveByShowtime(string $showtimeId): Collection
    {
        return SeatLock::where('showtime_id', $showtimeId)
            ->where('expires_at', '>', Carbon::now())
            ->with('seatLockSeats')
            ->get();
    }

    public function save(SeatLock $seatLock): SeatLock
    {
        $seatLock->save();
        return $seatLock;
    }

    public function delete(SeatLock $seatLock): void
    {
        $seatLock->delete();
    }

    public function findExpiredLocks(): Collection
    {
        return SeatLock::where('expires_at', '<=', Carbon::now())
            ->with('seatLockSeats')
            ->get();
    }
}
