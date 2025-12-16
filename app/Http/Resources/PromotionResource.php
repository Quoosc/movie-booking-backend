<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'promotionId'   => (string) $this->promotion_id,
            'code'          => (string) $this->code,
            'name'          => (string) $this->name,
            'description'   => $this->description,

            'discountType'  => $this->discount_type, // PERCENTAGE / FIXED_AMOUNT
            'discountValue' => $this->discount_value !== null ? (float) $this->discount_value : null,

            'startDate'     => $this->start_date ? $this->start_date->toISOString() : null,
            'endDate'       => $this->end_date ? $this->end_date->toISOString() : null,

            'usageLimit'    => $this->usage_limit !== null ? (int) $this->usage_limit : null,
            'perUserLimit'  => $this->per_user_limit !== null ? (int) $this->per_user_limit : null,

            'isActive'      => (bool) $this->is_active,

            'createdAt'     => $this->created_at ? $this->created_at->toISOString() : null,
            'updatedAt'     => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
