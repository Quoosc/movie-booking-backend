<?php

namespace App\Services;

use App\DTO\SessionContext;
use App\Enums\SeatStatus;
use App\Exceptions\{
    ResourceNotFoundException,
    SeatLockedException,
    LockExpiredException,
    MaxSeatsExceededException,
    CustomException
};
use App\Models\{SeatLock, SeatLockSeat, Booking};
use App\Repositories\{
    SeatLockRepository,
    ShowtimeSeatRepository,
    BookingRepository
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingService
{
    protected int $lockDurationMinutes;
    protected int $maxSeatsPerBooking;

    public function __construct(
        protected SeatLockRepository $seatLockRepository,
        protected ShowtimeSeatRepository $showtimeSeatRepository,
        protected BookingRepository $bookingRepository,
        protected RedisLockService $redisLockService,
        protected PriceCalculationService $priceCalculationService,
        protected TicketTypeService $ticketTypeService
    ) {
        $this->lockDurationMinutes = config('booking.lock.duration.minutes', 10);
        $this->maxSeatsPerBooking = config('booking.max.seats', 10);
    }

    /**
     * LOCK SEATS (Step 1)
     * POST /api/seat-locks
     */
    public function lockSeats(array $requestData, SessionContext $sessionContext): array
    {
        return DB::transaction(function () use ($requestData, $sessionContext) {
            $showtimeId = $requestData['showtimeId'];
            $seats = $requestData['seats'];

            if (count($seats) > $this->maxSeatsPerBooking) {
                throw new MaxSeatsExceededException($this->maxSeatsPerBooking, count($seats));
            }

            // Enforce single active lock per session by releasing any active locks
            $existingLocks = $this->seatLockRepository
                ->findAllActiveLocksForOwner($sessionContext->getLockOwnerId());

            foreach ($existingLocks as $lock) {
                $this->releaseSeats($sessionContext->getLockOwnerId(), $lock->showtime_id);
            }

            $showtimeSeatIds = array_column($seats, 'showtimeSeatId');
            $showtimeSeats = $this->showtimeSeatRepository
                ->findByIdsAndShowtime($showtimeSeatIds, $showtimeId);

            if ($showtimeSeats->count() !== count($showtimeSeatIds)) {
                throw new ResourceNotFoundException('One or more seats not found');
            }

            foreach ($seats as $seatData) {
                $this->ticketTypeService->validateTicketTypeForShowtime(
                    $showtimeId,
                    $seatData['ticketTypeId']
                );
            }

            $unavailableSeats = [];
            foreach ($showtimeSeats as $seat) {
                if ($seat->status !== SeatStatus::AVAILABLE) {
                    $unavailableSeats[] = $seat->showtime_seat_id;
                }
            }

            if (!empty($unavailableSeats)) {
                throw new SeatLockedException(
                    'Some seats are locked or already booked',
                    $unavailableSeats,
                    423
                );
            }

            $lockToken = Str::uuid()->toString();
            $ttlSeconds = $this->lockDurationMinutes * 60;

            Log::info('Attempting to acquire distributed locks for seats', [
                'showtimeId' => $showtimeId,
                'seatIds' => $showtimeSeatIds,
                'lockToken' => $lockToken,
                'ttlSeconds' => $ttlSeconds,
            ]);

            $acquired = $this->redisLockService->acquireMultipleSeatsLock(
                $showtimeId,
                $showtimeSeatIds,
                $lockToken,
                $ttlSeconds
            );

            if (!$acquired) {
                foreach ($showtimeSeatIds as $seatId) {
                    try {
                        $key = $this->redisLockService->generateSeatLockKey($showtimeId, $seatId);
                        $isLocked = $this->redisLockService->isSeatLocked($showtimeId, $seatId);
                        $ttl = $this->redisLockService->getLockTtl($key);
                        $value = $this->redisLockService->getLockValue($key);
                        Log::warning('Seat lock acquisition failed - current lock state', [
                            'showtimeId' => $showtimeId,
                            'seatId' => $seatId,
                            'lockKey' => $key,
                            'isLocked' => $isLocked,
                            'ttl' => $ttl,
                            'lockValue' => $value,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to inspect seat lock status', [
                            'seatId' => $seatId,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }

                throw new SeatLockedException(
                    'Failed to acquire locks for selected seats',
                    $showtimeSeatIds,
                    423
                );
            }

            $seatLock = new SeatLock([
                'lock_key' => $lockToken,
                'lock_owner_id' => $sessionContext->getLockOwnerId(),
                'lock_owner_type' => $sessionContext->getLockOwnerType(),
                'user_id' => $sessionContext->getUserId(),
                'showtime_id' => $showtimeId,
                'expires_at' => Carbon::now()->addMinutes($this->lockDurationMinutes),
            ]);
            $seatLock = $this->seatLockRepository->save($seatLock);

            $lockedSeatPayload = [];
            $totalPrice = 0;

            foreach ($seats as $seatData) {
                $showtimeSeat = $showtimeSeats->firstWhere(
                    'showtime_seat_id',
                    $seatData['showtimeSeatId']
                );

                if (!$showtimeSeat) {
                    continue;
                }

                $ticketType = \App\Models\TicketType::find($seatData['ticketTypeId']);
                if (!$ticketType) {
                    continue;
                }

                $basePrice = (float) $showtimeSeat->price;
                $finalPrice = $this->ticketTypeService->applyTicketTypeModifier(
                    $basePrice,
                    $ticketType
                );

                $seatLockSeat = new SeatLockSeat([
                    'seat_lock_id' => $seatLock->seat_lock_id,
                    'showtime_seat_id' => $seatData['showtimeSeatId'],
                    'ticket_type_id' => $seatData['ticketTypeId'],
                    'price' => $finalPrice,
                ]);
                $seatLockSeat->save();

                $lockedSeatPayload[] = [
                    'seatId' => $showtimeSeat->showtime_seat_id,
                    'rowLabel' => $showtimeSeat->seat->row_label ?? '',
                    'seatNumber' => $showtimeSeat->seat->seat_number ?? 0,
                    'seatType' => $showtimeSeat->seat->seat_type ?? '',
                    'ticketTypeId' => $seatData['ticketTypeId'],
                    'ticketTypeLabel' => $this->ticketTypeService->getTicketTypeLabel($seatData['ticketTypeId']),
                    'price' => (float) $finalPrice,
                ];

                $totalPrice += $finalPrice;
            }

            $this->showtimeSeatRepository->updateStatusBatch(
                $showtimeSeatIds,
                SeatStatus::LOCKED->value
            );

            return [
                'lockId' => $seatLock->seat_lock_id,
                'lockKey' => $seatLock->lock_key,
                'showtimeId' => $showtimeId,
                'lockedSeats' => $lockedSeatPayload,
                'totalPrice' => (float) $totalPrice,
                'expiresAt' => $seatLock->expires_at->toIso8601String(),
                'remainingSeconds' => $seatLock->getRemainingSeconds(),
                'message' => 'Seats locked successfully',
            ];
        });
    }

    /**
     * RELEASE SEATS
     * DELETE /api/seat-locks/showtime/{showtimeId}
     */
    public function releaseSeats(string $lockOwnerId, string $showtimeId): void
    {
        DB::transaction(function () use ($lockOwnerId, $showtimeId) {
            $seatLock = $this->seatLockRepository
                ->findByLockOwnerIdAndShowtimeId($lockOwnerId, $showtimeId);

            if (!$seatLock) {
                return; // Already released or expired
            }

            $seatIds = $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray();

            $this->redisLockService->releaseMultipleSeatsLock(
                $showtimeId,
                $seatIds,
                $seatLock->lock_key
            );

            $updatedCount = $this->showtimeSeatRepository->updateStatusBatch(
                $seatIds,
                SeatStatus::AVAILABLE->value
            );

            Log::info("Releasing seat locks", [
                'showtime' => $showtimeId,
                'owner' => $lockOwnerId,
                'seatIds' => $seatIds,
                'updatedCount' => $updatedCount,
                'expectedCount' => count($seatIds)
            ]);

            $this->seatLockRepository->delete($seatLock);

            Log::info("Released seat lock successfully for showtime: {$showtimeId}, owner: {$lockOwnerId}");
        });
    }

    /**
     * CHECK AVAILABILITY
     * GET /api/seat-locks/availability/showtime/{showtimeId}
     */
    public function checkAvailability(string $showtimeId, ?SessionContext $sessionContext): array
    {
        $allSeats = $this->showtimeSeatRepository->findByShowtimeId($showtimeId);

        $availableSeats = [];
        $lockedSeats = [];
        $bookedSeats = [];

        foreach ($allSeats as $seat) {
            $seatId = $seat->showtime_seat_id;

            switch ($seat->status) {
                case SeatStatus::AVAILABLE:
                    $availableSeats[] = $seatId;
                    break;
                case SeatStatus::LOCKED:
                    $lockedSeats[] = $seatId;
                    break;
                case SeatStatus::BOOKED:
                    $bookedSeats[] = $seatId;
                    break;
            }
        }

        $response = [
            'showtimeId' => $showtimeId,
            'availableSeats' => $availableSeats,
            'lockedSeats' => $lockedSeats,
            'bookedSeats' => $bookedSeats,
            'yourActiveLocks' => [],
            'message' => 'Seat availability retrieved',
        ];

        if ($sessionContext) {
            $seatLock = $this->seatLockRepository->findByLockOwnerIdAndShowtimeId(
                $sessionContext->getLockOwnerId(),
                $showtimeId
            );

            if ($seatLock && $seatLock->isActive()) {
                $response['yourActiveLocks'][] = [
                    'lockId' => $seatLock->seat_lock_id,
                    'seats' => $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray(),
                    'expiresAt' => $seatLock->expires_at->toIso8601String(),
                    'remainingSeconds' => $seatLock->getRemainingSeconds(),
                ];
            }
        }

        return $response;
    }

    /**
     * CALCULATE PRICE PREVIEW
     * POST /api/bookings/price-preview
     */
    public function calculatePricePreview(array $requestData, SessionContext $sessionContext): array
    {
        $lockId = $requestData['lockId'];
        $promotionCode = $requestData['promotionCode'] ?? null;
        $snacks = $requestData['snacks'] ?? [];

        $seatLock = $this->seatLockRepository->findById($lockId);

        if (!$seatLock) {
            throw new ResourceNotFoundException('Lock not found or expired');
        }

        if ($seatLock->lock_owner_id !== $sessionContext->getLockOwnerId()) {
            throw new CustomException('You do not own this lock', 403);
        }

        if (!$seatLock->isActive()) {
            throw new LockExpiredException();
        }

        if ($sessionContext->isGuest() && $promotionCode) {
            throw new CustomException('Guests cannot use promotions', 403);
        }

        // Recalculate ticket subtotal using base price + modifiers + ticket type (avoid stale lock prices)
        $seatLock->loadMissing([
            'seatLockSeats.ticketType',
            'seatLockSeats.showtimeSeat.seat',
            'showtime',
        ]);

        $ticketSubtotal = 0;
        foreach ($seatLock->seatLockSeats as $lockSeat) {
            $showtimeSeat = $lockSeat->showtimeSeat;
            $seat = $showtimeSeat?->seat;
            $ticketType = $lockSeat->ticketType;

            if (!$showtimeSeat || !$seat) {
                continue;
            }

            // Base price includes price modifiers for this seat/showtime
            $basePrice = $this->priceCalculationService->calculatePrice($seatLock->showtime, $seat);

            // Apply ticket type modifier if available
            $finalPrice = $ticketType
                ? $this->ticketTypeService->applyTicketTypeModifier($basePrice, $ticketType)
                : $basePrice;

            $ticketSubtotal += $finalPrice;
        }

        $snackSubtotal = 0;
        if (!empty($snacks)) {
            foreach ($snacks as $snack) {
                $snackModel = \App\Models\Snack::find($snack['snackId']);
                if ($snackModel) {
                    $snackSubtotal += $snackModel->price * $snack['quantity'];
                }
            }
        }

        $subtotal = $ticketSubtotal + $snackSubtotal;

        $discountResult = $this->priceCalculationService->calculateDiscounts(
            $subtotal,
            $sessionContext->isAuthenticated() ? $sessionContext->getUserId() : null,
            $promotionCode
        );

        $discount = $discountResult->totalDiscount ?? 0;
        $total = max(0, $subtotal - $discount);

        return [
            'subtotal' => (float) $subtotal,
            'discount' => (float) $discount,
            'total' => (float) $total,
        ];
    }

    /**
     * GET USER BOOKINGS
     * GET /api/bookings/my-bookings
     */
    public function getUserBookings(string $userId): array
    {
        $bookings = $this->bookingRepository->findByUserId($userId);

        return $bookings->map(function ($booking) {
            return $this->mapBookingToResponse($booking);
        })->toArray();
    }

    /**
     * GET BOOKING BY ID FOR USER
     * GET /api/bookings/{bookingId}
     */
    public function getBookingByIdForUser(string $bookingId, string $userId): array
    {
        $booking = $this->bookingRepository->findByIdAndUserId($bookingId, $userId);

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        return $this->mapBookingToResponse($booking);
    }

    /**
     * UPDATE QR CODE
     * PATCH /api/bookings/{bookingId}/qr
     */
    public function updateQrCode(string $bookingId, string $userId, string $qrCodeUrl): array
    {
        $booking = $this->bookingRepository->findByIdAndUserId($bookingId, $userId);

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        $booking->qr_code = $qrCodeUrl;
        $this->bookingRepository->save($booking);

        return $this->mapBookingToResponse($booking);
    }

    /**
     * Map Booking to Response
     */
    public function mapBookingToResponse(Booking $booking): array
    {
        $booking->loadMissing([
            'bookingSeats.showtimeSeat.seat',
            'bookingSeats.ticketType',
            'bookingSnacks.snack',
            'showtime.movie',
            'showtime.room.cinema',
        ]);

        $showtime = $booking->showtime;
        $movie = $showtime?->movie;
        $room = $showtime?->room;
        $cinema = $room?->cinema;
        $posterUrl = $movie?->poster_url ?? $movie?->posterUrl ?? $movie?->poster ?? null;

        $seats = $booking->bookingSeats->map(function ($bs) {
            $seat = $bs->showtimeSeat?->seat;
            return [
                'rowLabel' => $seat?->row_label,
                'seatNumber' => $seat?->seat_number,
                'seatType' => $seat?->seat_type,
                'ticketTypeLabel' => $bs->ticketType?->label ?? null,
                'price' => (float) $bs->price,
            ];
        })->toArray();

        $snacks = $booking->bookingSnacks->map(function ($bs) {
            $snack = $bs->snack;
            $unitPrice = $snack ? (float) $snack->price : null;
            return [
                'snackId' => $snack?->snack_id,
                'name' => $snack?->name,
                'quantity' => $bs->quantity,
                'unitPrice' => $unitPrice,
                'totalPrice' => $unitPrice !== null ? $unitPrice * $bs->quantity : null,
                'imageUrl' => $snack->image_url ?? $snack->imageUrl ?? null,
            ];
        })->toArray();

        return [
            'bookingId' => $booking->booking_id,
            'showtimeId' => $booking->showtime_id,
            'movieTitle' => $movie?->title,
            'posterUrl' => $posterUrl,
            'showtimeStartTime' => $showtime?->start_time?->toIso8601String(),
            'cinemaName' => $cinema?->name,
            'roomName' => $room?->room_number,
            'seats' => $seats,
            'snacks' => $snacks,
            'totalPrice' => (float) $booking->total_price,
            'discountReason' => $booking->discount_reason,
            'discountValue' => (float) $booking->discount_value,
            'finalPrice' => (float) $booking->final_price,
            'status' => $booking->status->value,
            'bookedAt' => $booking->booked_at?->toIso8601String(),
            'qrCode' => $booking->qr_code,
            'qrPayload' => $booking->qr_payload,
            'paymentExpiresAt' => $booking->payment_expires_at?->toIso8601String(),
        ];
    }

    /**
     * Get booking by ID (for internal use, no user validation)
     */
    public function getBookingById(string $bookingId): ?Booking
    {
        return $this->bookingRepository->findById($bookingId);
    }

    /**
     * Map Booking to Public-safe Response
     */
    public function mapBookingToPublicResponse(Booking $booking): array
    {
        $booking->loadMissing([
            'bookingSeats.showtimeSeat.seat',
            'bookingSeats.ticketType',
            'bookingSnacks.snack',
            'payments',
            'showtime.movie',
            'showtime.room.cinema',
        ]);

        $showtime = $booking->showtime;
        $movie = $showtime?->movie;
        $room = $showtime?->room;
        $cinema = $room?->cinema;
        $posterUrl = $movie?->poster_url ?? $movie?->posterUrl ?? $movie?->poster ?? null;

        $seats = $booking->bookingSeats->map(function ($bs) {
            $seat = $bs->showtimeSeat?->seat;
            return [
                'rowLabel' => $seat?->row_label,
                'seatNumber' => $seat?->seat_number,
                'seatType' => $seat?->seat_type,
                'ticketTypeLabel' => $bs->ticketType?->label ?? null,
                'price' => (float) $bs->price,
            ];
        })->toArray();

        $snacks = $booking->bookingSnacks->map(function ($bs) {
            $snack = $bs->snack;
            $unitPrice = $snack ? (float) $snack->price : null;
            return [
                'snackId' => $snack?->snack_id,
                'name' => $snack?->name,
                'quantity' => $bs->quantity,
                'unitPrice' => $unitPrice,
                'totalPrice' => $unitPrice !== null ? $unitPrice * $bs->quantity : null,
                'imageUrl' => $snack->image_url ?? $snack->imageUrl ?? null,
            ];
        })->toArray();

        $latestPayment = $booking->payments
            ->sortByDesc(fn ($payment) => $payment->created_at ?? $payment->paid_at)
            ->first();

        return [
            'bookingId' => $booking->booking_id,
            'status' => $booking->status->value,
            'movieTitle' => $movie?->title,
            'posterUrl' => $posterUrl,
            'showtimeStartTime' => $showtime?->start_time?->toIso8601String(),
            'cinemaName' => $cinema?->name,
            'cinemaAddress' => $cinema?->address ?? null,
            'roomName' => $room?->room_number,
            'seats' => $seats,
            'snacks' => $snacks,
            'totalPrice' => (float) $booking->total_price,
            'discountValue' => (float) $booking->discount_value,
            'finalPrice' => (float) $booking->final_price,
            'paymentMethod' => $latestPayment?->method?->value ?? null,
            'paymentStatus' => $latestPayment?->status?->value ?? null,
            'paidAt' => $latestPayment?->paid_at?->toIso8601String(),
        ];
    }
}
