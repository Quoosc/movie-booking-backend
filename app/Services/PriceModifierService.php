<?php

namespace App\Services;

use App\Models\PriceModifier;
use App\Models\ShowtimeSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PriceModifierService
{
    private function findPriceModifierById(string $id): PriceModifier
    {
        $modifier = PriceModifier::find($id);
        if (!$modifier) {
            abort(404, "PriceModifier not found with id {$id}");
        }
        return $modifier;
    }

    /**
     * POST /price-modifiers
     */
    public function addPriceModifier(array $data): PriceModifier
    {
        // conditionType & modifierType là string, FE phải gửi đúng (DAY_TYPE, TIME_RANGE,...)
        $conditionType = strtoupper($data['conditionType']);
        $modifierType  = strtoupper($data['modifierType']);

        if (!in_array($conditionType, ['DAY_TYPE', 'TIME_RANGE', 'FORMAT', 'ROOM_TYPE', 'SEAT_TYPE', 'TICKET_TYPE'], true)) {
            throw ValidationException::withMessages([
                'conditionType' => ['Invalid condition type'],
            ]);
        }

        if (!in_array($modifierType, ['PERCENTAGE', 'FIXED_AMOUNT'], true)) {
            throw ValidationException::withMessages([
                'modifierType' => ['Invalid modifier type'],
            ]);
        }

        $modifier = new PriceModifier();
        $modifier->name            = $data['name'];
        $modifier->condition_type  = $conditionType;
        $modifier->condition_value = $data['conditionValue'];
        $modifier->modifier_type   = $modifierType;
        $modifier->modifier_value  = $data['modifierValue'];
        $modifier->is_active       = $data['isActive'] ?? true;

        $modifier->save();

        return $modifier;
    }

    /**
     * PUT /price-modifiers/{id}
     * Cho phép đổi name + isActive (đúng như Java).
     */
    public function updatePriceModifier(string $id, array $data): PriceModifier
    {
        $modifier = $this->findPriceModifierById($id);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $modifier->name = $data['name'];
        }

        if (array_key_exists('isActive', $data) && $data['isActive'] !== null) {
            $modifier->is_active = $data['isActive'];
        }

        $modifier->save();

        return $modifier;
    }

    /**
     * DELETE /price-modifiers/{id}
     * Soft delete nếu đang được reference trong price_breakdown.
     */
    public function deletePriceModifier(string $id): void
    {
        $modifier = $this->findPriceModifierById($id);

        $referenced = ShowtimeSeat::where('price_breakdown', 'like', '%"name":"' . $modifier->name . '"%')
            ->exists();

        if ($referenced) {
            Log::info(
                'Soft deleting price modifier {id} - referenced in showtime seat breakdowns',
                ['id' => $id]
            );
            $modifier->is_active = false;
            $modifier->save();
        } else {
            Log::info(
                'Hard deleting price modifier {id} - not referenced',
                ['id' => $id]
            );
            $modifier->delete();
        }
    }

    public function getPriceModifier(string $id): PriceModifier
    {
        return $this->findPriceModifierById($id);
    }

    public function getAllPriceModifiers(): Collection
    {
        return PriceModifier::orderBy('created_at', 'desc')->get();
    }

    public function getActivePriceModifiers(): Collection
    {
        return PriceModifier::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPriceModifiersByConditionType(string $conditionType): Collection
    {
        return PriceModifier::where('condition_type', strtoupper($conditionType))
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
