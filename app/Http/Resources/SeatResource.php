<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'seatId'     => $this->seat_id,
            'roomId'     => $this->room_id,
            'roomNumber' => optional($this->room)->room_number,
            'cinemaName' => optional(optional($this->room)->cinema)->name,
            'seatNumber' => $this->seat_number,
            'rowLabel'   => $this->row_label,
            'seatType'   => $this->seat_type,
        ];
    }
}
