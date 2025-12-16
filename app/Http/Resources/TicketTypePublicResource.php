<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypePublicResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'ticketTypeId' => (string) $this->id,
            'code'         => $this->code,
            'label'        => $this->label,
            'price'        => $this->price !== null ? (float) $this->price : null,
        ];
    }
}
