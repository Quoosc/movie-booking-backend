<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

// class RoomResource extends JsonResource
// {
//     public function toArray($request)
//     {
//         return [
//             'roomId'     => $this->room_id,
//             'cinemaId'   => $this->cinema_id,
//             'cinemaName' => optional($this->cinema)->name,
//             'roomNumber' => $this->room_number,
//             'roomType'   => $this->room_type,
//             'isActive'   => (bool) $this->is_active,
//             'createdAt'  => $this->created_at,
//             'updatedAt'  => $this->updated_at,
//         ];
//     }
// }
// app/Http/Resources/RoomResource.php
class RoomResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'roomId'    => $this->room_id,
            'cinemaId'  => $this->cinema_id,
            'roomType'  => $this->room_type,
            'roomNumber'=> $this->room_number,
        ];
    }
}
