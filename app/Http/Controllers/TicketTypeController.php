<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTypePublicResource;
use App\Http\Resources\TicketTypeResource;
use App\Services\TicketTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TicketTypeController extends Controller
{
    public function __construct(
        private TicketTypeService $ticketTypeService
    ) {}

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

    // GET /api/ticket-types?showtimeId=&userId=
    public function index(Request $request)
    {
        $showtimeId = $request->query('showtimeId');
        $userId     = $request->query('userId');

        try {
            if (!$showtimeId) {
                return $this->respond(null, 'showtimeId query parameter is required', 400);
            }

            // Validate UUID format
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $showtimeId)) {
                return $this->respond(null, 'Invalid showtimeId format', 400);
            }

            $list = $this->ticketTypeService->getTicketTypesForShowtime($showtimeId, $userId);

            // If no ticket types found, return empty array
            if (empty($list)) {
                return $this->respond([]);
            }

            return $this->respond(TicketTypePublicResource::collection($list));
        } catch (\App\Exceptions\ResourceNotFoundException $e) {
            return $this->respond(null, $e->getMessage(), 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ticket types error: ' . $e->getMessage(), [
                'showtimeId' => $showtimeId ?? null,
                'exception' => $e->getTraceAsString(),
            ]);
            return $this->respond(null, 'Server error', 500);
        }
    }

    // GET /api/ticket-types/admin (admin-only)
    public function adminIndex()
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $list = $this->ticketTypeService->getAllTicketTypesForAdmin();
        return $this->respond(TicketTypeResource::collection($list));
    }

    // POST /api/ticket-types
    public function store(Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $validated = $request->validate([
            'code'        => ['required', 'regex:/^[a-z_]+$/', 'unique:ticket_types,code'],
            'label'       => ['required', 'string'],
            'modifierType' => ['required', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'modifierValue' => ['required', 'numeric', 'min:-100'],
            'active'      => ['required', 'boolean'],
            'sortOrder'   => ['required', 'integer', 'min:0'],
        ]);

        $ticketType = $this->ticketTypeService->createTicketType($validated);

        return $this->respond(new TicketTypeResource($ticketType), 'Ticket type created', Response::HTTP_CREATED);
    }

    // PUT /api/ticket-types/{id}
    public function update(string $id, Request $request)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $validated = $request->validate([
            'label'        => ['sometimes', 'nullable', 'string'],
            'modifierType' => ['sometimes', 'nullable', 'in:PERCENTAGE,FIXED_AMOUNT'],
            'modifierValue' => ['sometimes', 'nullable', 'numeric', 'min:-100'],
            'active'       => ['sometimes', 'nullable', 'boolean'],
            'sortOrder'    => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $ticketType = $this->ticketTypeService->updateTicketType($id, $validated);

        return $this->respond(new TicketTypeResource($ticketType), 'Ticket type updated');
    }

    // DELETE /api/ticket-types/{id}
    public function destroy(string $id)
    {
        if ($resp = $this->ensureAdmin()) {
            return $resp;
        }

        $this->ticketTypeService->deleteTicketType($id);

        return $this->respond(null, 'Ticket type deleted');
    }
}
