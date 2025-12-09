<?php

namespace App\Http\Controllers;

use App\Http\Resources\PriceBaseResource;
use App\Services\PriceBaseService;
use Illuminate\Http\Request;

class PriceBaseController extends Controller
{
    public function __construct(private PriceBaseService $priceBaseService) {}

    // POST /api/price-base
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'basePrice' => 'required|numeric',
            'isActive'  => 'nullable|boolean',
        ]);

        $priceBase = $this->priceBaseService->addPriceBase($data);

        return (new PriceBaseResource($priceBase))
            ->response()
            ->setStatusCode(201); // y chang Spring: HttpStatus.CREATED
    }

    // PUT /api/price-base/{id}
    public function update(string $id, Request $request)
    {
        $data = $request->validate([
            'name'      => 'nullable|string|max:255',
            'isActive' => 'nullable|boolean',
        ]);

        $priceBase = $this->priceBaseService->updatePriceBase($id, $data);

        return new PriceBaseResource($priceBase); // 200 OK
    }

    // DELETE /api/price-base/{id}
    public function destroy(string $id)
    {
        $this->priceBaseService->deletePriceBase($id);

        // Spring trả 200 + body rỗng
        return response()->json(null, 200);
    }

    // GET /api/price-base/{id}
    public function show(string $id)
    {
        $priceBase = $this->priceBaseService->getPriceBase($id);

        return new PriceBaseResource($priceBase);
    }

    // GET /api/price-base
    public function index()
    {
        $items = $this->priceBaseService->getAllPriceBases();

        return PriceBaseResource::collection($items);
    }

    // GET /api/price-base/active
    public function getActive()
    {
        $priceBase = $this->priceBaseService->getActiveBasePrice();

        return new PriceBaseResource($priceBase);
    }
}
