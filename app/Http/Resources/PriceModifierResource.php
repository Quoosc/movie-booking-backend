<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceModifierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'priceModifierId'=> $this->id,
            'name'           => $this->name,
            'conditionType'  => $this->condition_type,
            'conditionValue' => $this->condition_value,
            'modifierType'   => $this->modifier_type,
            'modifierValue'  => $this->modifier_value,
            'isActive'       => $this->is_active,
            'createdAt'      => $this->created_at,
            'updatedAt'      => $this->updated_at,
        ];
    }
}
