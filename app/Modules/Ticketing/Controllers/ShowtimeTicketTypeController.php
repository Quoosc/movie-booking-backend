<?php

namespace App\Modules\Ticketing\Controllers;

use App\Core\Http\Controllers\BaseController;

use App\Modules\Ticketing\Services\ShowtimeTicketTypeService;
use Illuminate\Http\Request;

class ShowtimeTicketTypeController extends BaseController
{
    public function __construct(
        private ShowtimeTicketTypeService $service
    ) {
    }

    // GET /api/showtimes/{showtimeId}/ticket-types
    public function index(string $showtimeId)
    {
        $ids = $this->service->getAssignedTicketTypeIds($showtimeId);

        return response()->json([
            'showtimeId'           => $showtimeId,
            'assignedTicketTypeIds'=> $ids,
        ]);
    }

    // POST /api/showtimes/{showtimeId}/ticket-types/{ticketTypeId}
    public function assignSingle(string $showtimeId, string $ticketTypeId)
    {
        $this->service->assignTicketTypeToShowtime($showtimeId, $ticketTypeId);
        return response()->json(null, 201);
    }

    // POST /api/showtimes/{showtimeId}/ticket-types
    public function assignMultiple(string $showtimeId, Request $request)
    {
        $validated = $request->validate([
            'ticketTypeIds'   => ['required', 'array', 'min:1'],
            'ticketTypeIds.*' => ['uuid'],
        ]);

        $this->service->assignTicketTypesToShowtime($showtimeId, $validated['ticketTypeIds']);

        return response()->json(null, 201);
    }

    // PUT /api/showtimes/{showtimeId}/ticket-types
    public function replace(string $showtimeId, Request $request)
    {
        $validated = $request->validate([
            'ticketTypeIds'   => ['required', 'array', 'min:1'],
            'ticketTypeIds.*' => ['uuid'],
        ]);

        $this->service->replaceTicketTypesForShowtime($showtimeId, $validated['ticketTypeIds']);

        return response()->json(null, 200);
    }

    // DELETE /api/showtimes/{showtimeId}/ticket-types/{ticketTypeId}
    public function remove(string $showtimeId, string $ticketTypeId)
    {
        $this->service->removeTicketTypeFromShowtime($showtimeId, $ticketTypeId);

        return response()->json(null, 204);
    }
}
