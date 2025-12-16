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
            'minPoints'        => (int) $this->min_points,        // Ensure integer type
            'discountType'     => $this->discount_type,
            'discountValue'    => (float) $this->discount_value,  // Ensure float type (number in JSON)
            'description'      => $this->description,
            'isActive'         => (bool) $this->is_active,        // Ensure boolean type
            'createdAt'        => $this->created_at,
            'updatedAt'        => $this->updated_at,
        ];
    }
}
