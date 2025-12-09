<?php

namespace App\Services;

use App\Models\PriceBase;
use App\Models\ShowtimeSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PriceBaseService
{
    private function findPriceBaseById(string $id): PriceBase
    {
        $priceBase = PriceBase::find($id);
        if (!$priceBase) {
            abort(404, "PriceBase not found with id {$id}");
        }
        return $priceBase;
    }

    /**
     * POST /price-base
     */
    public function addPriceBase(array $data): PriceBase
    {
        if (PriceBase::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Price base with this name already exists.'],
            ]);
        }

        $priceBase = new PriceBase();
        $priceBase->name       = $data['name'];
        $priceBase->base_price = $data['basePrice'];
        $priceBase->is_active  = $data['isActive'] ?? true;
        $priceBase->save();

        return $priceBase;
    }

    /**
     * PUT /price-base/{id}
     */
    public function updatePriceBase(string $id, array $data): PriceBase
    {
        $priceBase = $this->findPriceBaseById($id);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $newName = $data['name'];

            $exists = PriceBase::whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
                ->where('id', '!=', $priceBase->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'name' => ['Price base with this name already exists.'],
                ]);
            }

            $priceBase->name = $newName;
        }

        if (array_key_exists('isActive', $data) && $data['isActive'] !== null) {
            $priceBase->is_active = $data['isActive'];
        }

        $priceBase->save();

        return $priceBase;
    }

    /**
     * DELETE /price-base/{id}
     * Soft delete nếu đã được reference trong price_breakdown.
     */
    public function deletePriceBase(string $id): void
    {
        $priceBase = $this->findPriceBaseById($id);

        $basePriceStr = (string) $priceBase->base_price;

        $referenced = ShowtimeSeat::where('price_breakdown', 'like', '%"basePrice":' . $basePriceStr . '%')
            ->exists();

        if ($referenced) {
            Log::info(
                'Soft deleting price base {id} ({price} VND) - referenced in showtime seat breakdowns',
                ['id' => $id, 'price' => $basePriceStr]
            );
            $priceBase->is_active = false;
            $priceBase->save();
        } else {
            Log::info(
                'Hard deleting price base {id} ({price} VND) - not referenced',
                ['id' => $id, 'price' => $basePriceStr]
            );
            $priceBase->delete();
        }
    }

    public function getPriceBase(string $id): PriceBase
    {
        return $this->findPriceBaseById($id);
    }

    public function getAllPriceBases(): Collection
    {
        return PriceBase::orderBy('created_at', 'desc')->get();
    }

    public function getActiveBasePrice(): PriceBase
    {
        $priceBase = PriceBase::where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if (!$priceBase) {
            throw new \RuntimeException(
                'No active base price configured. Please create at least one active price base.'
            );
        }

        return $priceBase;
    }
}
