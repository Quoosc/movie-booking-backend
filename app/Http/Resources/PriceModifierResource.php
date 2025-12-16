<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceModifierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'priceModifierId'=> (string) $this->id,
            'name'           => $this->name,
            'conditionType'  => $this->condition_type,
            'conditionValue' => $this->condition_value,
            'modifierType'   => $this->modifier_type,
            'modifierValue'  => $this->modifier_value !== null ? (float) $this->modifier_value : null,
            'isActive'       => (bool) $this->is_active,
            'createdAt'      => $this->created_at?->toISOString(),
            'updatedAt'      => $this->updated_at?->toISOString(),
        ];
    }
}
