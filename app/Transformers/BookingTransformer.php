<?php

namespace App\Transformers;

use App\Models\Booking;

class BookingTransformer
{
    public static function toBookingResponse(Booking $booking): array
    {
        return [
            'bookingId'        => (string) $booking->id,
            'showtimeId'       => (string) $booking->showtime_id,
            'movieTitle'       => $booking->showtime->movie->title ?? null,
            'showtimeStartTime'=> optional($booking->showtime->start_time)->toIso8601String(),
            'cinemaName'       => $booking->showtime->room->cinema->name ?? null,
            'roomName'         => self::formatRoomName($booking),
            'seats'            => $booking->bookingSeats->map(function ($bs) {
                return [
                    'rowLabel'   => $bs->showtimeSeat->seat->row_label ?? null,
                    'seatNumber' => $bs->showtimeSeat->seat->seat_number ?? null,
                    'seatType'   => $bs->showtimeSeat->seat->seat_type ?? null,
                ];
            })->values()->all(),
            'totalPrice'       => $booking->total_price,
            'discountReason'   => $booking->discount_reason,
            'discountValue'    => $booking->discount_value,
            'finalPrice'       => $booking->final_price,
            'status'           => $booking->status->value,
            'bookedAt'         => optional($booking->booked_at)->toIso8601String(),
            'qrCode'           => $booking->qr_code,
            'qrPayload'        => $booking->qr_payload,
            'paymentExpiresAt' => optional($booking->payment_expires_at)->toIso8601String(),
        ];
    }

    protected static function formatRoomName(Booking $booking): ?string
    {
        $room = $booking->showtime->room ?? null;
        if (!$room) return null;
        return 'Room ' . $room->room_number . ' (' . $room->room_type . ')';
    }
}
