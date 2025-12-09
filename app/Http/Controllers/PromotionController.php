<?php

namespace App\Http\Controllers;

use App\Http\Resources\PromotionResource;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotionService) {}

    // POST /api/promotions  (Admin only)
    public function store(Request $request)
    {
        $data = $request->validate([
            'code'          => 'required|string|regex:/^[A-Z0-9_-]+$/',
            'name'          => 'required|string',
            'description'   => 'nullable|string',
            'discountType'  => 'required|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountValue' => 'required|numeric|min:0.01',
            'startDate'     => 'required|date',
            'endDate'       => 'required|date',
            'usageLimit'    => 'nullable|integer|min:1',
            'perUserLimit'  => 'nullable|integer|min:1',
            'isActive'      => 'nullable|boolean',
        ]);

        $promotion = $this->promotionService->addPromotion($data);

        return (new PromotionResource($promotion))
            ->response()
            ->setStatusCode(201);
    }

    // PUT /api/promotions/{promotionId}  (Admin only)
    public function update(string $promotionId, Request $request)
    {
        $data = $request->validate([
            'code'          => 'nullable|string|regex:/^[A-Z0-9_-]+$/',
            'name'          => 'nullable|string',
            'description'   => 'nullable|string',
            'discountType'  => 'nullable|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountValue' => 'nullable|numeric|min:0.01',
            'startDate'     => 'nullable|date',
            'endDate'       => 'nullable|date',
            'usageLimit'    => 'nullable|integer|min:1',
            'perUserLimit'  => 'nullable|integer|min:1',
            'isActive'      => 'nullable|boolean',
        ]);

        $promotion = $this->promotionService->updatePromotion($promotionId, $data);

        return new PromotionResource($promotion);
    }

    // PATCH /api/promotions/{promotionId}/deactivate (Admin only)
    public function deactivate(string $promotionId)
    {
        $this->promotionService->deactivatePromotion($promotionId);

        return response()->json(null, 204);
    }

    // DELETE /api/promotions/{promotionId} (Admin only)
    public function destroy(string $promotionId)
    {
        $this->promotionService->deletePromotion($promotionId);

        return response()->json(null, 204);
    }

    // GET /api/promotions/{promotionId}
    public function show(string $promotionId)
    {
        $promotion = $this->promotionService->getPromotion($promotionId);

        return new PromotionResource($promotion);
    }

    // GET /api/promotions/code/{code}
    public function showByCode(string $code)
    {
        $promotion = $this->promotionService->getPromotionByCode($code);

        return new PromotionResource($promotion);
    }

    // GET /api/promotions?filter=active|valid
    public function index(Request $request)
    {
        $filter = $request->query('filter');

        if (strtolower($filter) === 'active') {
            $items = $this->promotionService->getActivePromotions();
        } elseif (strtolower($filter) === 'valid') {
            $items = $this->promotionService->getValidPromotions();
        } else {
            $items = $this->promotionService->getAllPromotions();
        }

        return PromotionResource::collection($items);
    }

    // GET /api/promotions/active
    public function getActive()
    {
        $items = $this->promotionService->getActivePromotions();

        return PromotionResource::collection($items);
    }

    // GET /api/promotions/valid
    public function getValid()
    {
        $items = $this->promotionService->getValidPromotions();

        return PromotionResource::collection($items);
    }
}
