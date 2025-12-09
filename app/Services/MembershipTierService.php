<?php

// app/Services/MembershipTierService.php
namespace App\Services;

use App\Models\MembershipTier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MembershipTierService
{
    // ===== PRIVATE HELPER =====
    private function findMembershipTierById(string $tierId): MembershipTier
    {
        $tier = MembershipTier::find($tierId);

        if (!$tier) {
            throw new RuntimeException("MembershipTier not found with id: {$tierId}");
        }

        return $tier;
    }

    // ====== INIT DEFAULT TIERS (OPTIONAL) ======
    // Nếu muốn chạy giống @PostConstruct của Spring thì
    // có thể gọi hàm này trong AppServiceProvider::boot()
    public function initializeDefaultTiersIfEmpty(): void
    {
        if (MembershipTier::count() > 0) {
            return;
        }

        DB::transaction(function () {
            // Bronze
            MembershipTier::create([
                'name'           => 'Bronze',
                'min_points'     => 0,
                'discount_type'  => 'PERCENTAGE',
                'discount_value' => 0,
                'description'    => 'Default tier for new members',
                'is_active'      => true,
            ]);

            // Silver
            MembershipTier::create([
                'name'           => 'Silver',
                'min_points'     => 500,
                'discount_type'  => 'PERCENTAGE',
                'discount_value' => 5.0,
                'description'    => 'Silver tier with 5% discount',
                'is_active'      => true,
            ]);

            // Gold
            MembershipTier::create([
                'name'           => 'Gold',
                'min_points'     => 1000,
                'discount_type'  => 'PERCENTAGE',
                'discount_value' => 10.0,
                'description'    => 'Gold tier with 10% discount',
                'is_active'      => true,
            ]);

            // Platinum
            MembershipTier::create([
                'name'           => 'Platinum',
                'min_points'     => 2000,
                'discount_type'  => 'PERCENTAGE',
                'discount_value' => 15.0,
                'description'    => 'Platinum tier with 15% discount',
                'is_active'      => true,
            ]);
        });
    }

    // ===== CREATE =====
    public function addMembershipTier(array $data): MembershipTier
    {
        // unique name (case-insensitive)
        $exists = MembershipTier::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'Membership tier with this name already exists: ' . $data['name']
            );
        }

        // validate discount type/value
        if (!empty($data['discountType']) && isset($data['discountValue'])) {
            $type  = strtoupper($data['discountType']);
            $value = (float) $data['discountValue'];

            if ($type === 'PERCENTAGE' && $value > 100) {
                throw new RuntimeException('Percentage discount cannot exceed 100%');
            }
        }

        $tier = new MembershipTier();
        $tier->name           = $data['name'];
        $tier->min_points     = $data['minPoints'];
        $tier->discount_type  = strtoupper($data['discountType']);
        $tier->discount_value = $data['discountValue'];
        $tier->description    = $data['description'] ?? null;
        $tier->is_active      = $data['isActive'] ?? true;

        $tier->save();

        return $tier->refresh();
    }

    // ===== UPDATE =====
    public function updateMembershipTier(string $tierId, array $data): MembershipTier
    {
        $tier = $this->findMembershipTierById($tierId);

        // name
        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $newName = $data['name'];

            $exists = MembershipTier::whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
                ->where('membership_tier_id', '!=', $tierId)
                ->exists();

            if ($exists) {
                throw new RuntimeException(
                    'Membership tier with this name already exists: ' . $newName
                );
            }

            $tier->name = $newName;
        }

        // minPoints
        if (array_key_exists('minPoints', $data) && $data['minPoints'] !== null) {
            $tier->min_points = (int) $data['minPoints'];
        }

        // discountType
        if (array_key_exists('discountType', $data) && $data['discountType'] !== null) {
            $newType = strtoupper($data['discountType']);
            $tier->discount_type = $newType;

            if ($newType === 'PERCENTAGE'
                && $tier->discount_value !== null
                && (float) $tier->discount_value > 100
            ) {
                throw new RuntimeException('Percentage discount cannot exceed 100%');
            }
        }

        // discountValue
        if (array_key_exists('discountValue', $data) && $data['discountValue'] !== null) {
            $value = (float) $data['discountValue'];

            if ($tier->discount_type === 'PERCENTAGE' && $value > 100) {
                throw new RuntimeException('Percentage discount cannot exceed 100%');
            }

            $tier->discount_value = $value;
        }

        // description
        if (array_key_exists('description', $data)) {
            $tier->description = $data['description'];
        }

        // isActive
        if (array_key_exists('isActive', $data)) {
            $tier->is_active = (bool) $data['isActive'];
        }

        $tier->save();

        return $tier->refresh();
    }

    // ===== DEACTIVATE =====
    public function deactivateMembershipTier(string $tierId): void
    {
        $tier = $this->findMembershipTierById($tierId);
        $tier->is_active = false;
        $tier->save();
    }

    // ===== DELETE =====
    public function deleteMembershipTier(string $tierId): void
    {
        $tier = $this->findMembershipTierById($tierId);

        // check đang được user dùng
        $hasUsers = $tier->users()->exists();
        if ($hasUsers) {
            throw new RuntimeException(
                'Cannot delete membership tier that is assigned to users'
            );
        }

        $tier->delete();
    }

    // ===== GET ONE =====
    public function getMembershipTier(string $tierId): MembershipTier
    {
        return $this->findMembershipTierById($tierId);
    }

    public function getMembershipTierByName(string $name): MembershipTier
    {
        $tier = MembershipTier::where('name', $name)->first();

        if (!$tier) {
            throw new RuntimeException("MembershipTier not found with name: {$name}");
        }

        return $tier;
    }

    // ===== LIST =====
    public function getAllMembershipTiers()
    {
        return MembershipTier::orderBy('min_points')->get();
    }

    public function getActiveMembershipTiers()
    {
        return MembershipTier::where('is_active', true)
            ->orderBy('min_points')
            ->get();
    }

    // ===== LOGIC TÍNH TIER THEO LOYALTY (dùng cho users/loyalty) =====
    public function getAppropriateTier(int $loyaltyPoints): MembershipTier
    {
        $tier = MembershipTier::where('is_active', true)
            ->where('min_points', '<=', $loyaltyPoints)
            ->orderByDesc('min_points')
            ->first();

        if ($tier) {
            return $tier;
        }

        return $this->getDefaultTier();
    }

    public function getDefaultTier(): MembershipTier
    {
        $tier = MembershipTier::where('is_active', true)
            ->orderBy('min_points')
            ->first();

        if (!$tier) {
            throw new RuntimeException(
                'No default membership tier configured. Please create at least one tier.'
            );
        }

        return $tier;
    }

    /**
     * Nếu sau này muốn tính discount ở Service thay vì PriceCalculationService
     */
    public function calculateMembershipDiscountAmount(?MembershipTier $tier, float $amount): float
    {
        if (!$tier || !$tier->is_active) {
            return 0.0;
        }

        if (!$tier->discount_type || $tier->discount_value === null) {
            return 0.0;
        }

        if ((float) $tier->discount_value <= 0) {
            return 0.0;
        }

        $type  = strtoupper($tier->discount_type);
        $value = (float) $tier->discount_value;

        return match ($type) {
            'PERCENTAGE'  => round($amount * $value / 100.0, 2),
            'FIXED_AMOUNT'=> min($value, $amount),
            default       => 0.0,
        };
    }
}
