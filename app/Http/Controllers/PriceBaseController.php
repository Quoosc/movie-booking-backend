<?php

namespace App\Http\Controllers;

use App\Http\Resources\PriceBaseResource;
use App\Services\PriceBaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PriceBaseController extends Controller
{
    public function __construct(private PriceBaseService $priceBaseService) {}

    // ======= COMMON RESPONSE =======
    protected function respond($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function ensureAdmin()
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'ADMIN') {
            return $this->respond(null, 'Admin access required', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    // ========== PUBLIC: GET /api/price-base ==========
    public function index()
    {
        $items = $this->priceBaseService->getAllPriceBases();

        return $this->respond(PriceBaseResource::collection($items));
    }

    // ========== PUBLIC: GET /api/price-base/{id} ==========
    public function show(string $id)
    {
        $priceBase = $this->priceBaseService->getPriceBase($id);

        return $this->respond(new PriceBaseResource($priceBase));
    }

    // ========== PUBLIC: GET /api/price-base/active ==========
    public function getActive()
    {
        $priceBase = $this->priceBaseService->getActiveBasePrice();

        return $this->respond(new PriceBaseResource($priceBase));
    }

    // ========== ADMIN: POST /api/price-base ==========
    public function store(Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'basePrice' => 'required|numeric|min:0.01',
            'isActive'  => 'sometimes|boolean',
        ]);

        $priceBase = $this->priceBaseService->addPriceBase($data);

        return $this->respond(new PriceBaseResource($priceBase), 'Price base created', Response::HTTP_CREATED);
    }

    // ========== ADMIN: PUT /api/price-base/{id} ==========
    public function update(string $id, Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $data = $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'isActive' => 'sometimes|boolean',
        ]);

        $priceBase = $this->priceBaseService->updatePriceBase($id, $data);

        return $this->respond(new PriceBaseResource($priceBase), 'Price base updated');
    }

    // ========== ADMIN: DELETE /api/price-base/{id} ==========
    public function destroy(string $id)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $this->priceBaseService->deletePriceBase($id);

        return $this->respond(null, 'Price base deleted');
    }
}
