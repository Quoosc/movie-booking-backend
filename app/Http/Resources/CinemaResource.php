<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CinemaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'cinemaId'    => $this->cinema_id,
            'name'        => $this->name,
            'address'     => $this->address,
            'hotline'     => $this->hotline,
            'status'      => $this->status,
            'isActive'    => (bool) $this->is_active,
            'createdAt'   => $this->created_at,
            'updatedAt'   => $this->updated_at,
        ];
    }
}
