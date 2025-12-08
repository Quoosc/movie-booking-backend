<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\MembershipTier;

class MembershipTiersSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'name'          => 'SILVER',
                'min_points'    => 0,
                'discount_type' => null,
                'discount_value'=> null,
                'description'   => 'Thành viên mới',
                'is_active'     => true,
            ],
            [
                'name'          => 'GOLD',
                'min_points'    => 1000,
                'discount_type' => 'PERCENT',
                'discount_value'=> 5,
                'description'   => 'Thành viên vàng',
                'is_active'     => true,
            ],
            [
                'name'          => 'PLATINUM',
                'min_points'    => 3000,
                'discount_type' => 'PERCENT',
                'discount_value'=> 10,
                'description'   => 'Thành viên bạch kim',
                'is_active'     => true,
            ],
        ];

        foreach ($tiers as $tier) {
            MembershipTier::updateOrCreate(
                ['name' => $tier['name']],
                array_merge($tier, [
                    'tier_id' => Str::uuid()->toString(),
                ])
            );
        }
    }
}
