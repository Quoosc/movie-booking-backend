<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'ticketTypeId'  => $this->id,
            'code'          => $this->code,
            'label'         => $this->label,
            'modifierType'  => $this->modifier_type,
            'modifierValue' => $this->modifier_value,
            'price'         => $this->price ?? null,
            'active'        => (bool) $this->active,
            'sortOrder'     => $this->sort_order,
        ];
    }
}
