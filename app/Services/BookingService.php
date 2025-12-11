<?php

namespace App\Services;

use App\DTO\SessionContext;
use App\Enums\SeatStatus;
use App\Enums\LockOwnerType;
use App\Exceptions\{
    ResourceNotFoundException,
    SeatLockedException,
    ConcurrentBookingException,
    LockExpiredException,
    MaxSeatsExceededException,
    CustomException
};
use App\Models\{SeatLock, SeatLockSeat, ShowtimeSeat, Booking, User};
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

            // 1. Validate số lượng ghế
            if (count($seats) > $this->maxSeatsPerBooking) {
                throw new MaxSeatsExceededException($this->maxSeatsPerBooking, count($seats));
            }

            // 2. Tìm existing active locks của lockOwnerId
            $existingLocks = $this->seatLockRepository
                ->findAllActiveLocksForOwner($sessionContext->getLockOwnerId());

            // 3. Nếu có lock cho CÙNG showtime => throw ConcurrentBookingException
            foreach ($existingLocks as $lock) {
                if ($lock->showtime_id === $showtimeId) {
                    throw new ConcurrentBookingException(
                        'You already have an active lock for this showtime'
                    );
                }
            }

            // 4. Nếu có locks cho KHÁC showtime => release chúng
            if ($existingLocks->isNotEmpty()) {
                foreach ($existingLocks as $lock) {
                    $this->releaseSeats($sessionContext->getLockOwnerId(), $lock->showtime_id);
                }
            }

            // 5. Fetch showtime seats
            $showtimeSeatIds = array_column($seats, 'showtimeSeatId');
            $showtimeSeats = $this->showtimeSeatRepository
                ->findByIdsAndShowtime($showtimeSeatIds, $showtimeId);

            if ($showtimeSeats->count() !== count($showtimeSeatIds)) {
                throw new ResourceNotFoundException('One or more seats not found');
            }

            // 6. Validate ticket types thuộc showtime
            $ticketTypeIds = array_column($seats, 'ticketTypeId');
            foreach ($ticketTypeIds as $ticketTypeId) {
                $this->ticketTypeService->validateTicketTypeForShowtime($showtimeId, $ticketTypeId);
            }

            // 7. Check seats có status = AVAILABLE không
            $unavailableSeats = [];
            foreach ($showtimeSeats as $seat) {
                if ($seat->status !== SeatStatus::AVAILABLE) {
                    $unavailableSeats[] = $seat->showtime_seat_id;
                }
            }

            if (!empty($unavailableSeats)) {
                throw new SeatLockedException(
                    'Some seats are not available',
                    $unavailableSeats
                );
            }

            // 8. Generate lockToken
            $lockToken = Str::uuid()->toString();
            $ttlSeconds = $this->lockDurationMinutes * 60;

            // 9. Acquire Redis distributed lock cho từng seat
            $acquired = $this->redisLockService->acquireMultipleSeatsLock(
                $showtimeId,
                $showtimeSeatIds,
                $lockToken,
                $ttlSeconds
            );

            if (!$acquired) {
                throw new SeatLockedException(
                    'Failed to acquire locks for selected seats',
                    $showtimeSeatIds
                );
            }

            // 10. Create SeatLock record
            $seatLock = new SeatLock([
                'lock_key' => $lockToken,
                'lock_owner_id' => $sessionContext->getLockOwnerId(),
                'lock_owner_type' => $sessionContext->getLockOwnerType(),
                'user_id' => $sessionContext->getUserId(),
                'showtime_id' => $showtimeId,
                'expires_at' => Carbon::now()->addMinutes($this->lockDurationMinutes),
            ]);
            $seatLock = $this->seatLockRepository->save($seatLock);

            // 11. Create SeatLockSeat records với calculated prices
            $seatLockSeats = [];
            $totalPrice = 0;

            foreach ($seats as $seatData) {
                $showtimeSeat = $showtimeSeats->firstWhere(
                    'showtime_seat_id',
                    $seatData['showtimeSeatId']
                );

                if (!$showtimeSeat) {
                    continue;
                }

                // Fetch ticket type
                $ticketType = \App\Models\TicketType::find($seatData['ticketTypeId']);
                if (!$ticketType) {
                    continue;
                }

                // Calculate price với ticket type modifier
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

                $seatLockSeats[] = [
                    'showtimeSeatId' => $showtimeSeat->showtime_seat_id,
                    'seatRow' => $showtimeSeat->seat->row_label ?? '',
                    'seatNumber' => $showtimeSeat->seat->seat_number ?? 0,
                    'seatType' => $showtimeSeat->seat->seat_type ?? '',
                    'ticketTypeId' => $seatData['ticketTypeId'],
                    'ticketTypeLabel' => $this->ticketTypeService->getTicketTypeLabel($seatData['ticketTypeId']),
                    'price' => $finalPrice,
                ];

                $totalPrice += $finalPrice;
            }

            // 12. Update showtime_seats status = LOCKED
            $this->showtimeSeatRepository->updateStatusBatch(
                $showtimeSeatIds,
                SeatStatus::LOCKED->value
            );

            // 13. Return LockSeatsResponse
            return [
                'lockId' => $seatLock->seat_lock_id,
                'showtimeId' => $showtimeId,
                'lockOwnerId' => $sessionContext->getLockOwnerId(),
                'lockOwnerType' => $sessionContext->getLockOwnerType()->value,
                'lockedSeats' => $seatLockSeats,
                'totalPrice' => $totalPrice,
                'expiresAt' => $seatLock->expires_at->toIso8601String(),
                'lockDurationMinutes' => $this->lockDurationMinutes,
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

            // Get seat IDs
            $seatIds = $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray();

            // Release Redis locks
            $this->redisLockService->releaseMultipleSeatsLock(
                $showtimeId,
                $seatIds,
                $seatLock->lock_key
            );

            // Update showtime_seats status = AVAILABLE
            $this->showtimeSeatRepository->updateStatusBatch($seatIds, SeatStatus::AVAILABLE->value);

            // Delete SeatLock record (cascade delete seatLockSeats)
            $this->seatLockRepository->delete($seatLock);

            Log::info("Released seat lock for showtime: {$showtimeId}, owner: {$lockOwnerId}");
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
            'message' => 'Seat availability retrieved',
        ];

        // Nếu có session => get lock info của user đó
        if ($sessionContext) {
            $seatLock = $this->seatLockRepository->findByLockOwnerIdAndShowtimeId(
                $sessionContext->getLockOwnerId(),
                $showtimeId
            );

            if ($seatLock && $seatLock->isActive()) {
                $myLockedSeats = $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray();

                $response['sessionLockInfo'] = [
                    'lockId' => $seatLock->seat_lock_id,
                    'myLockedSeats' => $myLockedSeats,
                    'remainingSeconds' => $seatLock->getRemainingSeconds(),
                ];
            } else {
                $response['sessionLockInfo'] = null;
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

        // Find seat lock
        $seatLock = $this->seatLockRepository->findById($lockId);

        if (!$seatLock) {
            throw new ResourceNotFoundException('Lock not found or expired');
        }

        // Validate ownership
        if ($seatLock->lock_owner_id !== $sessionContext->getLockOwnerId()) {
            throw new CustomException('You do not own this lock', 403);
        }

        // Check if active
        if (!$seatLock->isActive()) {
            throw new LockExpiredException();
        }

        // Calculate ticket subtotal
        $ticketSubtotal = $seatLock->seatLockSeats->sum('price');

        // Calculate snack subtotal
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

        // Calculate discounts
        $discountResult = $this->priceCalculationService->calculateDiscounts(
            $subtotal,
            $sessionContext->getUserId(),
            $promotionCode
        );

        $discount = $discountResult->totalDiscount ?? 0;
        $total = max(0, $subtotal - $discount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
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
    private function mapBookingToResponse(Booking $booking): array
    {
        $showtime = $booking->showtime;
        $movie = $showtime?->movie;
        $room = $showtime?->room;
        $cinema = $room?->cinema;

        $seats = $booking->bookingSeats->map(function ($bs) {
            $seat = $bs->showtimeSeat?->seat;
            return [
                'rowLabel' => $seat?->row_label,
                'seatNumber' => $seat?->seat_number,
                'seatType' => $seat?->seat_type,
                'price' => (float) $bs->price,
            ];
        })->toArray();

        return [
            'bookingId' => $booking->booking_id,
            'showtimeId' => $booking->showtime_id,
            'movieTitle' => $movie?->title,
            'showtimeStartTime' => $showtime?->start_time?->toIso8601String(),
            'cinemaName' => $cinema?->name,
            'roomName' => $room?->room_number,
            'seats' => $seats,
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
}
