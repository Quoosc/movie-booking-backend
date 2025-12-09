<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'promotionId'   => $this->promotion_id,
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => $this->description,
            'discountType'  => $this->discount_type,      // PERCENTAGE / FIXED_AMOUNT
            'discountValue' => $this->discount_value,
            'startDate'     => $this->start_date,
            'endDate'       => $this->end_date,
            'usageLimit'    => $this->usage_limit,
            'perUserLimit'  => $this->per_user_limit,
            'isActive'      => $this->is_active,
            'createdAt'     => $this->created_at,
            'updatedAt'     => $this->updated_at,
        ];
    }
}
