<?php

// app/Http/Controllers/MembershipTierController.php
namespace App\Http\Controllers;

use App\Http\Resources\MembershipTierResource;
use App\Services\MembershipTierService;
use Illuminate\Http\Request;

class MembershipTierController extends Controller
{
    public function __construct(
        private MembershipTierService $membershipTierService
    ) {}

    // POST /api/membership-tiers
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'minPoints'     => 'required|integer|min:0',
            'discountType'  => 'required|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountValue' => 'required|numeric|min:0.01',
            'description'   => 'nullable|string',
            'isActive'      => 'nullable|boolean',
        ]);

        $tier = $this->membershipTierService->addMembershipTier($data);

        return (new MembershipTierResource($tier))
            ->response()
            ->setStatusCode(201);
    }

    // PUT /api/membership-tiers/{id}
    public function update(string $id, Request $request)
    {
        $data = $request->validate([
            'name'          => 'nullable|string|max:255',
            'minPoints'     => 'nullable|integer|min:0',
            'discountType'  => 'nullable|string|in:PERCENTAGE,FIXED_AMOUNT',
            'discountValue' => 'nullable|numeric|min:0.01',
            'description'   => 'nullable|string',
            'isActive'      => 'nullable|boolean',
        ]);

        $tier = $this->membershipTierService->updateMembershipTier($id, $data);

        return new MembershipTierResource($tier);
    }

    // PATCH /api/membership-tiers/{id}/deactivate
    public function deactivate(string $id)
    {
        $this->membershipTierService->deactivateMembershipTier($id);

        return response()->json(null, 204);
    }

    // DELETE /api/membership-tiers/{id}
    public function destroy(string $id)
    {
        $this->membershipTierService->deleteMembershipTier($id);

        return response()->json(null, 204);
    }

    // GET /api/membership-tiers/{id}
    public function show(string $id)
    {
        $tier = $this->membershipTierService->getMembershipTier($id);

        return new MembershipTierResource($tier);
    }

    // GET /api/membership-tiers/name/{name}
    public function getByName(string $name)
    {
        $tier = $this->membershipTierService->getMembershipTierByName($name);

        return new MembershipTierResource($tier);
    }

    // GET /api/membership-tiers
    public function index()
    {
        $tiers = $this->membershipTierService->getAllMembershipTiers();

        return MembershipTierResource::collection($tiers);
    }

    // GET /api/membership-tiers/active
    public function getActive()
    {
        $tiers = $this->membershipTierService->getActiveMembershipTiers();

        return MembershipTierResource::collection($tiers);
    }
}
