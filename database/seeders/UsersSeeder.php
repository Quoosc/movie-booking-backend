<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\MembershipTier;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy tier SILVER làm default
        $silverTier = MembershipTier::where('name', 'SILVER')->first();

        User::updateOrCreate(
            ['email' => 'admin@cinesverse.local'],
            [
                'user_id'            => Str::uuid()->toString(),
                'username'           => 'QuocAdmin',
                'phoneNumber'        => '0900000000',
                'password'           => Hash::make('123456'), // đổi sau
                'provider'           => 'local',
                'role'               => 'ADMIN',
                'avatar_url'         => null,
                'avatar_cloudinary_id' => null,
                'loyalty_points'     => 0,
                'membership_tier_id' => $silverTier?->membership_tier_id,
            ]
        );
    }
}
