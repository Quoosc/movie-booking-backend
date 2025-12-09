<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTypePublicResource;
use App\Http\Resources\TicketTypeResource;
use App\Services\TicketTypeService;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function __construct(
        private TicketTypeService $ticketTypeService
    ) {}

    // GET /api/ticket-types?showtimeId=&userId=
    public function index(Request $request)
    {
        $showtimeId = $request->query('showtimeId');
        $userId     = $request->query('userId');

        if ($showtimeId) {
            $list = $this->ticketTypeService->getTicketTypesForShowtime($showtimeId, $userId);
        } else {
            $list = $this->ticketTypeService->getAllActiveTicketTypes();
        }

        return TicketTypePublicResource::collection($list);
    }

    // GET /api/ticket-types/admin (admin-only)
    public function adminIndex()
    {
        $list = $this->ticketTypeService->getAllTicketTypesForAdmin();
        return TicketTypeResource::collection($list);
    }

    // POST /api/ticket-types
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => ['required', 'regex:/^[a-z_]+$/', 'unique:ticket_types,code'],
            'label'       => ['required', 'string'],
            'modifierType' => ['required', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'modifierValue' => ['required', 'numeric', 'min:-100'],
            'active'      => ['required', 'boolean'],
            'sortOrder'   => ['required', 'integer', 'min:0'],
        ]);

        $ticketType = $this->ticketTypeService->createTicketType($validated);

        return (new TicketTypeResource($ticketType))
            ->response()
            ->setStatusCode(201);
    }

    // PUT /api/ticket-types/{id}
    public function update(string $id, Request $request)
    {
        $validated = $request->validate([
            'label'        => ['sometimes', 'nullable', 'string'],
            'modifierType' => ['sometimes', 'nullable', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'modifierValue' => ['sometimes', 'nullable', 'numeric', 'min:-100'],
            'active'       => ['sometimes', 'nullable', 'boolean'],
            'sortOrder'    => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $ticketType = $this->ticketTypeService->updateTicketType($id, $validated);

        return new TicketTypeResource($ticketType);
    }

    // DELETE /api/ticket-types/{id}
    public function destroy(string $id)
    {
        $this->ticketTypeService->deleteTicketType($id);

        return response()->json(null, 200);
    }
}
