<?php

namespace App\Services;

use App\DTO\SessionContext;
use App\Enums\SeatStatus;
use App\Exceptions\CustomException;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Showtime;
use App\Models\ShowtimeSeat;
use App\Models\Snack;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BookingService
{
    public function __construct(
        protected Booking               $bookingModel,
        protected Showtime              $showtimeModel,
        protected ShowtimeSeat          $showtimeSeatModel,
        protected Snack                 $snackModel,
        protected TicketType            $ticketTypeModel,
        protected Promotion             $promotionModel,
        protected RedisLockService      $redisLockService,
        protected PriceCalculationService $priceCalculationService,
        protected TicketTypeService     $ticketTypeService,
    ) {}

    /**
     * Dùng cho PayPalService/MomoService
     */
    public function getBookingById(string $bookingId): Booking
    {
        /** @var Booking|null $booking */
        $booking = $this->bookingModel->newQuery()
            ->with(['user', 'showtime', 'bookingSeats.showtimeSeat.seat'])
            ->find($bookingId);

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        return $booking;
    }

    /**
     * POST /seat-locks
     *
     * Request (payload):
     * {
     *   "showtimeId": "UUID",
     *   "seats": [
     *     { "showtimeSeatId": "UUID", "ticketTypeId": "UUID" }
     *   ]
     * }
     *
     * SessionContext: xác định USER / GUEST để set lockOwnerId + lockOwnerType
     *
     * Response:
     * {
     *   "lockId": "UUID",
     *   "showtimeId": "UUID",
     *   "lockOwnerId": "string",
     *   "lockOwnerType": "USER|GUEST",
     *   "lockedSeats": [
     *     {
     *       "showtimeSeatId": "UUID",
     *       "seatRow": "A",
     *       "seatNumber": 5,
     *       "seatType": "VIP",
     *       "ticketTypeId": "UUID",
     *       "ticketTypeLabel": "NGƯỜI LỚN",
     *       "price": 120000
     *     }
     *   ],
     *   "totalPrice": 240000,
     *   "expiresAt": "2025-12-11T10:00:00Z",
     *   "lockDurationMinutes": 10,
     *   "message": "Seats locked successfully"
     * }
     */
    public function lockSeats(array $payload, SessionContext $sessionContext): array
    {
        return DB::transaction(function () use ($payload, $sessionContext) {
            $showtimeId = $payload['showtimeId'] ?? null;
            $seatsInput = $payload['seats'] ?? [];

            if (!$showtimeId) {
                throw new CustomException('showtimeId is required', Response::HTTP_BAD_REQUEST);
            }

            if (empty($seatsInput)) {
                throw new CustomException('No seats selected', Response::HTTP_BAD_REQUEST);
            }

            /** @var Showtime|null $showtime */
            $showtime = $this->showtimeModel->newQuery()
                ->with(['room.cinema', 'movie'])
                ->find($showtimeId);

            if (!$showtime) {
                throw new ResourceNotFoundException('Showtime not found');
            }

            // Lấy danh sách showtime_seat_id từ payload
            $showtimeSeatIds = array_map(
                fn (array $s) => $s['showtimeSeatId'],
                $seatsInput
            );

            /** @var \Illuminate\Support\Collection<int, ShowtimeSeat> $showtimeSeats */
            $showtimeSeats = $this->showtimeSeatModel->newQuery()
                ->with('seat')
                ->whereIn('showtime_seat_id', $showtimeSeatIds)
                ->get();

            if ($showtimeSeats->count() !== count($showtimeSeatIds)) {
                throw new CustomException('One or more seats not found', Response::HTTP_BAD_REQUEST);
            }

            // Validate: seat thuộc showtime + đang AVAILABLE
            foreach ($showtimeSeats as $ss) {
                if ((string) $ss->showtime_id !== $showtimeId) {
                    throw new CustomException('Seat does not belong to this showtime', Response::HTTP_BAD_REQUEST);
                }

                if ($ss->status !== SeatStatus::AVAILABLE) {
                    throw new CustomException('One or more seats are not available', Response::HTTP_CONFLICT);
                }
            }

            // TTL cho lock (vd: 600s = 10 phút)
            $ttlSeconds = (int) config('booking.lock_ttl_seconds', 600);
            $lockId     = $this->redisLockService->generateLockToken();

            // Acquire lock cho nhiều ghế cùng lúc
            $locked = $this->redisLockService
                ->acquireMultipleSeatsLock($showtimeId, $showtimeSeatIds, $lockId, $ttlSeconds);

            if (!$locked) {
                throw new CustomException(
                    'Some of the selected seats are already locked or being reserved by another user.',
                    Response::HTTP_CONFLICT
                );
            }

            // Chuẩn bị map ticketType
            $ticketTypeIds = array_filter(
                array_map(
                    fn (array $s) => $s['ticketTypeId'] ?? null,
                    $seatsInput
                )
            );

            $ticketTypesById = [];
            if (!empty($ticketTypeIds)) {
                $ticketTypesById = $this->ticketTypeModel->newQuery()
                    ->whereIn('id', $ticketTypeIds)
                    ->get()
                    ->keyBy('id')
                    ->all();
            }

            // Map selection theo showtimeSeatId để dễ lookup
            $selectionBySeatId = [];
            foreach ($seatsInput as $s) {
                $selectionBySeatId[$s['showtimeSeatId']] = $s;
            }

            $lockedSeats = [];
            $totalPrice  = 0.0;

            foreach ($showtimeSeats as $ss) {
                $seat = $ss->seat;

                $selection = $selectionBySeatId[(string) $ss->showtime_seat_id] ?? null;
                if (!$selection) {
                    throw new CustomException('Seat selection mismatch', Response::HTTP_BAD_REQUEST);
                }

                $ticketTypeId = $selection['ticketTypeId'] ?? null;

                // Base price từ PriceCalculationService (chưa áp ticket type)
                [$basePrice] = $this->priceCalculationService
                    ->calculatePriceWithBreakdown($showtime, $seat);

                $finalPrice      = $basePrice;
                $ticketTypeLabel = null;

                if ($ticketTypeId) {
                    /** @var \App\Models\TicketType|null $ticketType */
                    $ticketType = $ticketTypesById[$ticketTypeId] ?? null;
                    if (!$ticketType) {
                        throw new CustomException('Invalid ticket type', Response::HTTP_BAD_REQUEST);
                    }

                    // Validate ticket type có gán cho showtime
                    $this->ticketTypeService
                        ->validateTicketTypeForShowtime($showtimeId, $ticketTypeId);

                    $finalPrice = $this->ticketTypeService
                        ->applyTicketTypeModifier($basePrice, $ticketType);

                    $ticketTypeLabel = $ticketType->label;
                }

                $finalPrice = round($finalPrice, 2);
                $totalPrice += $finalPrice;

                $lockedSeats[] = [
                    'showtimeSeatId'  => (string) $ss->showtime_seat_id,
                    'seatRow'         => $seat->row_label,
                    'seatNumber'      => $seat->seat_number,
                    'seatType'        => $seat->seat_type,
                    'ticketTypeId'    => $ticketTypeId,
                    'ticketTypeLabel' => $ticketTypeLabel,
                    'price'           => $finalPrice,
                ];
            }

            $now       = Carbon::now();
            $expiresAt = $now->clone()->addSeconds($ttlSeconds);

            // USER -> lockOwnerId = userId, lockOwnerType = USER
            // GUEST -> lockOwnerId = sessionId, lockOwnerType = GUEST
            $lockOwnerId   = $sessionContext->userId ?? $sessionContext->sessionId;
            $lockOwnerType = $sessionContext->userId ? 'USER' : 'GUEST';

            return [
                'lockId'             => $lockId,
                'showtimeId'         => (string) $showtime->showtime_id,
                'lockOwnerId'        => $lockOwnerId,
                'lockOwnerType'      => $lockOwnerType,
                'lockedSeats'        => $lockedSeats,
                'totalPrice'         => round($totalPrice, 2),
                'expiresAt'          => $expiresAt->toIso8601String(),
                'lockDurationMinutes'=> (int) ceil($ttlSeconds / 60),
                'message'            => 'Seats locked successfully',
            ];
        });
    }
}
