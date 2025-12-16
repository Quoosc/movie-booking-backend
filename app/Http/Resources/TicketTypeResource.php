<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'ticketTypeId'  => (string) $this->id,
            'code'          => $this->code,
            'label'         => $this->label,
            'modifierType'  => $this->modifier_type,
            'modifierValue' => $this->modifier_value !== null ? (float) $this->modifier_value : null,
            'price'         => $this->price !== null ? (float) $this->price : null,
            'active'        => (bool) $this->active,
            'sortOrder'     => (int) ($this->sort_order ?? 0),
        ];
    }
}
