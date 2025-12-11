<?php

namespace App\Repositories;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingRepository
{
    public function findById(string $id): ?Booking
    {
        return Booking::with([
            'bookingSeats.showtimeSeat.seat',
            'bookingPromotions',
            'bookingSnacks.snack',
            'showtime.movie',
            'showtime.room.cinema'
        ])->find($id);
    }

    public function findByUserId(string $userId, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return Booking::with([
            'bookingSeats',
            'showtime.movie',
            'showtime.cinema'
        ])
            ->where('user_id', $userId)
            ->orderBy('booked_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByIdAndUserId(string $bookingId, string $userId): ?Booking
    {
        return Booking::with([
            'bookingSeats.showtimeSeat.seat',
            'bookingPromotions',
            'bookingSnacks.snack',
            'showtime.movie',
            'showtime.room.cinema'
        ])
            ->where('booking_id', $bookingId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function save(Booking $booking): Booking
    {
        $booking->save();
        return $booking;
    }

    public function delete(Booking $booking): void
    {
        $booking->delete();
    }
}
