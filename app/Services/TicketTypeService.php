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
use App\Exceptions\ResourceNotFoundException;

class TicketTypeService
{
    public function __construct(
        protected TicketType          $ticketTypeModel,
        protected Showtime            $showtimeModel,
        protected ShowtimeTicketType  $showtimeTicketTypeModel,
        protected PriceCalculationService $priceCalculationService,
    ) {}

    /** GET /ticket-types (không kèm showtime) */
    public function getAllActiveTicketTypes(): array
    {
        return $this->ticketTypeModel
            ->newQuery()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /** GET /ticket-types?showtimeId=... */
    public function getTicketTypesForShowtime(string $showtimeId, ?string $userId = null): array
    {
        /** @var Showtime|null $showtime */
        $showtime = $this->showtimeModel->newQuery()->find($showtimeId);

        if (!$showtime) {
            throw new ResourceNotFoundException('Showtime not found');
        }

        $showtimeTicketTypes = $this->showtimeTicketTypeModel
            ->newQuery()
            ->where('showtime_id', $showtimeId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        if ($showtimeTicketTypes->isEmpty()) {
            Log::warning("No ticket types assigned to showtime {$showtimeId}. Falling back to all active ticket types.");
            $ticketTypes = $this->ticketTypeModel
                ->newQuery()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get();
        } else {
            $ticketTypes = $showtimeTicketTypes
                ->map(fn(ShowtimeTicketType $stt) => $stt->ticketType)
                ->filter()
                ->values();
        }

        // Reference price: NORMAL seat
        $referenceSeat = new Seat([
            'seat_type' => 'NORMAL', // hoặc SeatType::NORMAL->value
        ]);

        $referencePrice = $this->priceCalculationService->calculatePrice($showtime, $referenceSeat);

        $result = [];

        foreach ($ticketTypes as $ticketType) {
            $priceWithModifier = $this->applyTicketTypeModifier($referencePrice, $ticketType);
            $result[] = [
                'id'            => $ticketType->id,
                'code'          => $ticketType->code,
                'label'         => $ticketType->label,
                'modifier_type' => $ticketType->modifier_type,
                'modifier_value' => $ticketType->modifier_value,
                'active'        => $ticketType->active,
                'sort_order'    => $ticketType->sort_order,
                'price'         => $priceWithModifier,
            ];
        }

        return $result;
    }


    /** Áp dụng modifier giống Spring */
    public function applyTicketTypeModifier(float $basePrice, TicketType $ticketType): float
    {
        if ($ticketType->modifier_type === 'PERCENTAGE') {
            $multiplier = 1.0 + ((float) $ticketType->modifier_value / 100.0);
            return round($basePrice * $multiplier, 0);
        }

        if ($ticketType->modifier_type === 'FIXED_AMOUNT') {
            return round($basePrice + (float) $ticketType->modifier_value, 0);
        }

        return $basePrice;
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
        /** @var TicketType|null $ticketType */
        $ticketType = $this->ticketTypeModel->newQuery()->find($ticketTypeId);

        if (!$ticketType) {
            throw new ResourceNotFoundException('TicketType not found');
        }

        $exists = $this->showtimeTicketTypeModel
            ->newQuery()
            ->where('showtime_id', $showtimeId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('active', true)
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Ticket type '%s' is not available for this showtime. Please select from the available ticket types for this showtime.",
                    $ticketType->code
                )
            );
        }
    }
}
