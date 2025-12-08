<?php

namespace App\Http\Controllers;

use App\Models\MembershipTier;

class MembershipTierController extends Controller
{
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // GET /api/membership-tiers/active
    public function getActiveTiers()
    {
        $tiers = MembershipTier::where('is_active', true)
            ->orderBy('min_points')
            ->get()
            ->map(function (MembershipTier $tier) {
                return [
                    'membershipTierId' => $tier->tier_id,
                    'name'             => $tier->name,
                    'minPoints'        => $tier->min_points,
                    'discountType'     => $tier->discount_type,
                    'discountValue'    => $tier->discount_value,
                    'description'      => $tier->description,
                    'isActive'         => $tier->is_active,
                    'createdAt'        => $tier->created_at,
                    'updatedAt'        => $tier->updated_at,
                ];
            });

        return $this->respond($tiers);
    }
}
