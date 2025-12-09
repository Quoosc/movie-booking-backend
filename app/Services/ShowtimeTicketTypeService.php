<?php

namespace App\Services;

use App\Models\Showtime;
use App\Models\ShowtimeTicketType;
use App\Models\TicketType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ShowtimeTicketTypeService
{
    public function assignTicketTypeToShowtime(string $showtimeId, string $ticketTypeId): void
    {
        $showtime = Showtime::where('showtime_id', $showtimeId)->firstOrFail();
        $ticketType = TicketType::findOrFail($ticketTypeId);

        $exists = ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('active', true)
            ->exists();

        if ($exists) {
            Log::info("Ticket type {$ticketTypeId} already assigned to showtime {$showtimeId}");
            return;
        }

        ShowtimeTicketType::create([
            'showtime_id'    => $showtime->showtime_id,
            'ticket_type_id' => $ticketType->id,
            'active'         => true,
        ]);

        Log::info("Assigned ticket type {$ticketTypeId} to showtime {$showtimeId}");
    }

    public function assignTicketTypesToShowtime(string $showtimeId, array $ticketTypeIds): void
    {
        foreach ($ticketTypeIds as $ticketTypeId) {
            $this->assignTicketTypeToShowtime($showtimeId, $ticketTypeId);
        }
    }

    public function removeTicketTypeFromShowtime(string $showtimeId, string $ticketTypeId): void
    {
        $assignments = ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('active', true)
            ->get();

        if ($assignments->isEmpty()) {
            abort(404, 'ShowtimeTicketType not found for given showtimeId and ticketTypeId');
        }

        foreach ($assignments as $assignment) {
            $assignment->active = false;
            $assignment->save();
        }

        Log::info("Removed ticket type {$ticketTypeId} from showtime {$showtimeId}");
    }

    public function getAssignedTicketTypeIds(string $showtimeId): Collection
    {
        Showtime::where('showtime_id', $showtimeId)->firstOrFail();

        return ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('active', true)
            ->pluck('ticket_type_id');
    }

    public function replaceTicketTypesForShowtime(string $showtimeId, array $ticketTypeIds): void
    {
        Showtime::where('showtime_id', $showtimeId)->firstOrFail();

        $existing = ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('active', true)
            ->get();

        foreach ($existing as $assignment) {
            $assignment->active = false;
            $assignment->save();
        }

        $this->assignTicketTypesToShowtime($showtimeId, $ticketTypeIds);

        Log::info("Replaced ticket types for showtime {$showtimeId}");
    }
}
