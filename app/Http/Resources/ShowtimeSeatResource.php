<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShowtimeSeatResource extends JsonResource
{
    public function toArray($request)
    {
        $seat = $this->whenLoaded('seat');

        return [
            'showtimeSeatId' => $this->showtime_seat_id,
            'showtimeId'     => $this->showtime_id,
            'seatId'         => $this->seat_id,
            'rowLabel'       => $seat?->row_label,
            'seatNumber'     => $seat?->seat_number,
            'seatType'       => $seat?->seat_type,
            'status'         => $this->status?->value ?? null,
            'basePrice'      => $this->base_price,
            'finalPrice'     => $this->final_price,
        ];
    }
}
