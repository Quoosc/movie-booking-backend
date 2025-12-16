<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceBaseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'priceBaseId' => $this->id,
            'name'        => $this->name,
            'basePrice'   => (float) $this->base_price,
            'isActive'    => (bool) $this->is_active,
            'createdAt'   => $this->created_at,
            'updatedAt'   => $this->updated_at,
        ];
    }
}
