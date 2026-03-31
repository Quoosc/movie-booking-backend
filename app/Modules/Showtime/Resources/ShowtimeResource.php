<?php

namespace App\Modules\Showtime\Resources;

// app/Http/Resources/ShowtimeResource.php
use App\Modules\Cinema\Resources\RoomResource;
use App\Modules\Movie\Resources\MovieResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowtimeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'showtimeId' => $this->showtime_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'movie' => new MovieResource($this->whenLoaded('movie')),
            'format' => $this->format,
            // Trả ISO string giống Spring
            'startTime' => optional($this->start_time)->toISOString(),
        ];
    }
}
