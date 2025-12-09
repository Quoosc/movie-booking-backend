<?php

namespace App\Services;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PromotionService
{
    private function findPromotionById(string $promotionId): Promotion
    {
        $promotion = Promotion::find($promotionId);

        if (!$promotion) {
            throw new RuntimeException("Promotion not found with id: {$promotionId}");
        }

        return $promotion;
    }

    // ========== CREATE ==========
    public function addPromotion(array $data): Promotion
    {
        // unique code (case-insensitive)
        $code = strtoupper($data['code']);

        $exists = Promotion::whereRaw('UPPER(code) = ?', [$code])->exists();
        if ($exists) {
            throw new RuntimeException("Promotion code already exists: {$data['code']}");
        }

        // dates
        $start = Carbon::parse($data['startDate']);
        $end   = Carbon::parse($data['endDate']);

        if ($end->lt($start)) {
            throw new RuntimeException('End date must be after start date');
        }

        // discount type + value
        $discountType  = strtoupper($data['discountType']); // PERCENTAGE / FIXED_AMOUNT
        $discountValue = (float) $data['discountValue'];

        if ($discountType === 'PERCENTAGE' && $discountValue > 100) {
            throw new RuntimeException('Percentage discount cannot exceed 100%');
        }

        // perUserLimit <= usageLimit (nếu cả 2 có)
        $usageLimit    = $data['usageLimit']    ?? null;
        $perUserLimit  = $data['perUserLimit']  ?? null;

        if ($usageLimit !== null && $perUserLimit !== null && $perUserLimit > $usageLimit) {
            throw new RuntimeException('Per user limit cannot exceed total usage limit');
        }

        $promotion = new Promotion();
        $promotion->code           = $data['code'];
        $promotion->name           = $data['name'];
        $promotion->description    = $data['description'] ?? null;
        $promotion->discount_type  = $discountType;
        $promotion->discount_value = $discountValue;
        $promotion->start_date     = $start;
        $promotion->end_date       = $end;
        $promotion->usage_limit    = $usageLimit;
        $promotion->per_user_limit = $perUserLimit;
        $promotion->is_active      = $data['isActive'] ?? true;

        $promotion->save();

        return $promotion->refresh();
    }

    // ========== UPDATE ==========
    public function updatePromotion(string $promotionId, array $data): Promotion
    {
        $promotion = $this->findPromotionById($promotionId);

        if (!empty($data['code'])) {
            $newCode = $data['code'];
            $exists  = Promotion::whereRaw('UPPER(code) = ?', [strtoupper($newCode)])
                ->where('promotion_id', '!=', $promotionId)
                ->exists();

            if ($exists) {
                throw new RuntimeException("Promotion code already exists: {$newCode}");
            }
            $promotion->code = $newCode;
        }

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $promotion->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $promotion->description = $data['description'];
        }

        if (array_key_exists('discountType', $data) && $data['discountType'] !== null) {
            $newType = strtoupper($data['discountType']);
            $promotion->discount_type = $newType;

            if ($newType === 'PERCENTAGE' && $promotion->discount_value > 100) {
                throw new RuntimeException('Percentage discount cannot exceed 100%');
            }
        }

        if (array_key_exists('discountValue', $data) && $data['discountValue'] !== null) {
            $value = (float) $data['discountValue'];

            if ($promotion->discount_type === 'PERCENTAGE' && $value > 100) {
                throw new RuntimeException('Percentage discount cannot exceed 100%');
            }

            $promotion->discount_value = $value;
        }

        if (!empty($data['startDate'])) {
            $promotion->start_date = Carbon::parse($data['startDate']);
        }

        if (!empty($data['endDate'])) {
            $promotion->end_date = Carbon::parse($data['endDate']);
        }

        // validate date range
        if ($promotion->end_date->lt($promotion->start_date)) {
            throw new RuntimeException('End date must be after start date');
        }

        if (array_key_exists('usageLimit', $data)) {
            $promotion->usage_limit = $data['usageLimit'];
        }

        if (array_key_exists('perUserLimit', $data)) {
            $promotion->per_user_limit = $data['perUserLimit'];
        }

        if (
            $promotion->usage_limit !== null
            && $promotion->per_user_limit !== null
            && $promotion->per_user_limit > $promotion->usage_limit
        ) {
            throw new RuntimeException('Per user limit cannot exceed total usage limit');
        }

        if (array_key_exists('isActive', $data)) {
            $promotion->is_active = (bool) $data['isActive'];
        }

        $promotion->save();

        return $promotion->refresh();
    }

    // ========== DEACTIVATE ==========
    public function deactivatePromotion(string $promotionId): void
    {
        $promotion = $this->findPromotionById($promotionId);
        $promotion->is_active = false;
        $promotion->save();
    }

    // ========== DELETE ==========
    public function deletePromotion(string $promotionId): void
    {
        $promotion = $this->findPromotionById($promotionId);

        // nếu bảng booking_promotions đã có, check usage ở đây
        $usedCount = DB::table('booking_promotions')
            ->where('promotion_id', $promotion->promotion_id)
            ->count();

        if ($usedCount > 0) {
            throw new RuntimeException('Cannot delete promotion that has been used in bookings');
        }

        $promotion->delete();
    }

    // ========== GET ONE ==========
    public function getPromotion(string $promotionId): Promotion
    {
        return $this->findPromotionById($promotionId);
    }

    public function getPromotionByCode(string $code): Promotion
    {
        $promotion = Promotion::where('code', $code)->first();

        if (!$promotion) {
            throw new RuntimeException("Promotion not found with code: {$code}");
        }

        return $promotion;
    }

    // ========== LIST ==========
    public function getAllPromotions()
    {
        return Promotion::orderByDesc('created_at')->get();
    }

    public function getActivePromotions()
    {
        return Promotion::where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getValidPromotions()
    {
        $now = Carbon::now();

        return Promotion::where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderByDesc('created_at')
            ->get();
    }
}
