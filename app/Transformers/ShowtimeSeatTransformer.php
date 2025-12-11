<?php

namespace App\Transformers;

use App\Models\ShowtimeSeat;
use App\Enums\SeatStatus;

class ShowtimeSeatTransformer
{
    public static function toDataResponse(ShowtimeSeat $stSeat): array
    {
        $seat = $stSeat->seat;

        $status = $stSeat->status;
        if ($status instanceof SeatStatus) {
            $status = $status->value;
        } else {
            $status = (string) $status;
        }

        return [
            'showtimeSeatId'   => (string) $stSeat->showtime_seat_id,
            'showtimeId'       => (string) $stSeat->showtime_id,
            'seatId'           => $seat ? (string) $seat->seat_id : null,
            'rowLabel'         => $seat?->row_label,
            'seatNumber'       => $seat?->seat_number,
            'seatType'         => $seat?->seat_type,
            'status'           => $status,
            'price'            => (float) ($stSeat->final_price ?? $stSeat->base_price ?? 0),
            'priceBreakdown'   => $stSeat->price_breakdown,
        ];
    }
}
