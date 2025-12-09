<?php

// app/Http/Resources/MembershipTierResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MembershipTierResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'membershipTierId' => $this->membership_tier_id,
            'name'             => $this->name,
            'minPoints'        => $this->min_points,
            'discountType'     => $this->discount_type,
            'discountValue'    => $this->discount_value,
            'description'      => $this->description,
            'isActive'         => $this->is_active,
            'createdAt'        => $this->created_at,
            'updatedAt'        => $this->updated_at,
        ];
    }
}
