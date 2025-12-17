<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypePublicResource extends JsonResource
{
    public function toArray($request)
    {
        // Use calculated_price if available (set by TicketTypeService), otherwise fall back to model's price
        $price = $this->calculated_price ?? $this->price;

        return [
            'ticketTypeId' => (string) $this->id,
            'code'         => $this->code,
            'label'        => $this->label,
            'price'        => $price !== null ? (float) $price : null,
        ];
    }
}
