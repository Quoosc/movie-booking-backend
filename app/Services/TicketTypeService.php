<?php

namespace App\Services;

use App\Http\Resources\TicketTypePublicResource;
use App\Http\Resources\TicketTypeResource;
use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\SeatLockSeat;
use App\Models\Showtime;
use App\Models\ShowtimeTicketType;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TicketTypeService
{
    public function __construct(
        private PriceCalculationService $priceCalculationService
    ) {}

    /** GET /ticket-types (không kèm showtime) */
    public function getAllActiveTicketTypes(): Collection
    {
        return TicketType::where('active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /** GET /ticket-types?showtimeId=... */
    public function getTicketTypesForShowtime(string $showtimeId, ?string $userId): Collection
    {
        $showtime = Showtime::where('showtime_id', $showtimeId)->firstOrFail();

        $assignments = ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('active', true)
            ->get();

        if ($assignments->isEmpty()) {
            Log::warning("No ticket types assigned to showtime {$showtimeId}, fallback to all active.");
            $ticketTypes = TicketType::where('active', true)
                ->orderBy('sort_order')
                ->get();
        } else {
            $ids = $assignments->pluck('ticket_type_id');
            $ticketTypes = TicketType::whereIn('id', $ids)
                ->where('active', true)
                ->orderBy('sort_order')
                ->get();
        }

        // reference seat NORMAL
        $referenceSeat = new Seat([
            'seat_type' => 'NORMAL',
        ]);
        $basePrice = $this->priceCalculationService->calculatePrice($showtime, $referenceSeat);

        return $ticketTypes->map(function (TicketType $tt) use ($basePrice) {
            $tt->price = $this->applyTicketTypeModifier($basePrice, $tt);
            return $tt;
        });
    }

    /** Áp dụng modifier giống Spring */
    public function applyTicketTypeModifier(float $basePrice, TicketType $ticketType): float
    {
        $value = (float) $ticketType->modifier_value;

        switch ($ticketType->modifier_type) {
            case 'PERCENTAGE':
                $multiplier = 1 + ($value / 100.0);
                return round($basePrice * $multiplier);

            case 'FIXED_AMOUNT':
                return round($basePrice + $value);

            default:
                return round($basePrice);
        }
    }

    /** GET /ticket-types/admin */
    public function getAllTicketTypesForAdmin(): Collection
    {
        return TicketType::orderBy('sort_order')->get();
    }

    /** POST /ticket-types */
    public function createTicketType(array $data): TicketType
    {
        if (TicketType::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Ticket type code already exists.'],
            ]);
        }

        return TicketType::create([
            'code'          => $data['code'],
            'label'         => $data['label'],
            'modifier_type' => $data['modifierType'],
            'modifier_value' => $data['modifierValue'],
            'active'        => $data['active'],
            'sort_order'    => $data['sortOrder'],
        ]);
    }

    /** PUT /ticket-types/{id} */
    public function updateTicketType(string $id, array $data): TicketType
    {
        $ticketType = TicketType::findOrFail($id);

        if (array_key_exists('label', $data) && $data['label'] !== null) {
            $ticketType->label = $data['label'];
        }

        if (array_key_exists('modifierType', $data) && $data['modifierType'] !== null) {
            $ticketType->modifier_type = $data['modifierType'];
        }

        if (array_key_exists('modifierValue', $data) && $data['modifierValue'] !== null) {
            $ticketType->modifier_value = $data['modifierValue'];
        }

        if (array_key_exists('active', $data) && $data['active'] !== null) {
            $ticketType->active = $data['active'];
        }

        if (array_key_exists('sortOrder', $data) && $data['sortOrder'] !== null) {
            $ticketType->sort_order = $data['sortOrder'];
        }

        $ticketType->save();

        return $ticketType;
    }

    /** DELETE /ticket-types/{id} – soft/hard giống Java */
    public function deleteTicketType(string $id): void
    {
        $ticketType = TicketType::findOrFail($id);

        $usedInLocks = SeatLockSeat::where('ticket_type_id', $id)->exists();
        $usedInBookings = BookingSeat::where('ticket_type_id', $id)->exists();

        if ($usedInLocks || $usedInBookings) {
            $ticketType->active = false;
            $ticketType->save();
            Log::info("Soft deleted ticket type {$ticketType->code}");
        } else {
            $ticketType->delete();
            Log::info("Hard deleted ticket type {$ticketType->code}");
        }
    }

    /** Check ticket type có hợp lệ cho showtime không (cho bước lock seat sau này) */
    public function validateTicketTypeForShowtime(string $showtimeId, string $ticketTypeId): void
    {
        $ticketType = TicketType::findOrFail($ticketTypeId);

        $exists = ShowtimeTicketType::where('showtime_id', $showtimeId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('active', true)
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException(
                "Ticket type '{$ticketType->code}' is not available for this showtime."
            );
        }
    }
}
