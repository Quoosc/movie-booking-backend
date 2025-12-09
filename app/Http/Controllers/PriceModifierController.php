<?php

namespace App\Http\Controllers;

use App\Http\Resources\PriceModifierResource;
use App\Services\PriceModifierService;
use Illuminate\Http\Request;

class PriceModifierController extends Controller
{
    public function __construct(private PriceModifierService $priceModifierService)
    {
    }

    // POST /api/price-modifiers
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'conditionType'  => 'required|string',  // DAY_TYPE, TIME_RANGE, ...
            'conditionValue' => 'required|string',
            'modifierType'   => 'required|string', // PERCENTAGE / FIXED_AMOUNT
            'modifierValue'  => 'required|numeric',
            'isActive'       => 'nullable|boolean',
        ]);

        $modifier = $this->priceModifierService->addPriceModifier($data);

        return (new PriceModifierResource($modifier))
            ->response()
            ->setStatusCode(201);
    }

    // PUT /api/price-modifiers/{id}
    public function update(string $id, Request $request)
    {
        $data = $request->validate([
            'name'      => 'nullable|string|max:255',
            'isActive' => 'nullable|boolean',
        ]);

        $modifier = $this->priceModifierService->updatePriceModifier($id, $data);

        return new PriceModifierResource($modifier); // 200 OK
    }

    // DELETE /api/price-modifiers/{id}
    public function destroy(string $id)
    {
        $this->priceModifierService->deletePriceModifier($id);

        // giống Spring: 200, body rỗng
        return response()->json(null, 200);
    }

    // GET /api/price-modifiers/{id}
    public function show(string $id)
    {
        $modifier = $this->priceModifierService->getPriceModifier($id);

        return new PriceModifierResource($modifier);
    }

    // GET /api/price-modifiers
    public function index()
    {
        $items = $this->priceModifierService->getAllPriceModifiers();

        return PriceModifierResource::collection($items);
    }

    // GET /api/price-modifiers/active
    public function getActive()
    {
        $items = $this->priceModifierService->getActivePriceModifiers();

        return PriceModifierResource::collection($items);
    }

    // GET /api/price-modifiers/by-condition?conditionType=...
    public function getByCondition(Request $request)
    {
        $conditionType = $request->query('conditionType');

        if (!$conditionType) {
            return response()->json([
                'message' => 'conditionType query param is required',
            ], 400);
        }

        // giống logic Spring: ConditionType.valueOf(conditionType.toUpperCase())
        $items = $this->priceModifierService->getPriceModifiersByConditionType($conditionType);

        return PriceModifierResource::collection($items);
    }
}
