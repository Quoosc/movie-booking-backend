<?php

namespace App\Services;

use App\DTO\Bookings\LockSeatsRequest;
use App\DTO\Bookings\LockSeatsResponse;
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
use App\Services\RedisLockService;
use App\Services\PriceCalculationService;
use App\Services\TicketTypeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BookingService
{
    public function __construct(
        protected Booking                 $bookingModel,
        protected Showtime                $showtimeModel,
        protected ShowtimeSeat            $showtimeSeatModel,
        protected Snack                   $snackModel,
        protected TicketType              $ticketTypeModel,
        protected Promotion               $promotionModel,
        protected RedisLockService        $redisLockService,
        protected PriceCalculationService $priceCalculationService,
        protected TicketTypeService       $ticketTypeService,
    ) {}

    /**
     * Dùng cho Payment service (PayPal / Momo)
     */
    public function getBookingById(string $bookingId): Booking
    {
        /** @var Booking|null $booking */
        $booking = $this->bookingModel->newQuery()
            ->with(['user', 'showtime.movie', 'showtime.room.cinema', 'bookingSeats.showtimeSeat.seat'])
            ->find($bookingId);

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        return $booking;
    }

    /* ============================================================
     *  SEAT LOCKS (STEP 1 – đã dùng bởi SeatLockController)
     * ============================================================
     */

    /**
     * LOCK GHẾ (Step 1 checkout)
     */
    public function lockSeats(LockSeatsRequest $request, SessionContext $sessionContext): LockSeatsResponse
    {
        return DB::transaction(function () use ($request, $sessionContext) {

            /** @var Showtime|null $showtime */
            $showtime = $this->showtimeModel->newQuery()
                ->with(['room.cinema', 'movie'])
                ->find($request->showtimeId);

            if (!$showtime) {
                throw new ResourceNotFoundException('Showtime not found');
            }

            if (empty($request->seats)) {
                throw new CustomException('No seats selected', Response::HTTP_BAD_REQUEST);
            }

            $seatIds = array_map(fn($s) => $s['showtimeSeatId'], $request->seats);

            /** @var \Illuminate\Support\Collection<int, ShowtimeSeat> $showtimeSeats */
            $showtimeSeats = $this->showtimeSeatModel->newQuery()
                ->with('seat')
                ->whereIn('showtime_seat_id', $seatIds)
                ->get();

            if ($showtimeSeats->count() !== count($seatIds)) {
                throw new CustomException('One or more seats not found', Response::HTTP_BAD_REQUEST);
            }

            // Validate seat thuộc showtime & AVAILABLE
            foreach ($showtimeSeats as $ss) {
                if ((string) $ss->showtime_id !== $request->showtimeId) {
                    throw new CustomException('Seat does not belong to this showtime', Response::HTTP_BAD_REQUEST);
                }
                if ($ss->status !== SeatStatus::AVAILABLE) {
                    throw new CustomException('One or more seats are not available', Response::HTTP_CONFLICT);
                }
            }

            // TTL cho lock (vd 10 phút)
            $ttlSeconds = config('booking.lock_ttl_seconds', 600);
            $lockToken  = $this->redisLockService->generateLockToken();

            // Acquire lock Redis
            $locked = $this->redisLockService
                ->acquireMultipleSeatsLock($request->showtimeId, $seatIds, $lockToken, $ttlSeconds);

            if (!$locked) {
                throw new CustomException(
                    'Some of the selected seats are already locked or being reserved by another user.',
                    Response::HTTP_CONFLICT
                );
            }

            // ========== TÍNH GIÁ TỪNG GHẾ ==========
            $seatItems       = [];
            $ticketSubtotal  = 0.0;

            $ticketTypeMap   = [];
            $ticketTypeIds   = array_filter(array_map(fn($s) => $s['ticketTypeId'] ?? null, $request->seats));

            if (count($ticketTypeIds) > 0) {
                $ticketTypes = $this->ticketTypeModel->newQuery()
                    ->whereIn('id', $ticketTypeIds)
                    ->get()
                    ->keyBy('id');
                $ticketTypeMap = $ticketTypes->all();
            }

            $seatSelectionById = [];
            foreach ($request->seats as $s) {
                $seatSelectionById[$s['showtimeSeatId']] = $s;
            }

            foreach ($showtimeSeats as $ss) {
                $seatEntity = $ss->seat;
                $selection  = $seatSelectionById[(string) $ss->showtime_seat_id] ?? null;

                if (!$selection) {
                    throw new CustomException('Seat selection mismatch', Response::HTTP_BAD_REQUEST);
                }

                $ticketTypeId = $selection['ticketTypeId'] ?? null;

                // base price (chưa ticket type)
                [$basePrice, $breakdownJson] = $this->priceCalculationService
                    ->calculatePriceWithBreakdown($showtime, $seatEntity);

                $finalSeatPrice = $basePrice;
                $ticketTypeInfo = null;

                if ($ticketTypeId) {
                    /** @var \App\Models\TicketType|null $ticketType */
                    $ticketType = $ticketTypeMap[$ticketTypeId] ?? null;
                    if (!$ticketType) {
                        throw new CustomException('Invalid ticket type', Response::HTTP_BAD_REQUEST);
                    }

                    $this->ticketTypeService
                        ->validateTicketTypeForShowtime($request->showtimeId, $ticketTypeId);

                    $finalSeatPrice = $this->ticketTypeService
                        ->applyTicketTypeModifier($basePrice, $ticketType);

                    $ticketTypeInfo = [
                        'id'    => (string) $ticketType->id,
                        'code'  => $ticketType->code,
                        'label' => $ticketType->label,
                    ];
                }

                $ticketSubtotal += $finalSeatPrice;

                $seatItems[] = [
                    'showtimeSeatId' => (string) $ss->showtime_seat_id,
                    'rowLabel'       => $seatEntity->row_label,
                    'seatNumber'     => $seatEntity->seat_number,
                    'seatType'       => $seatEntity->seat_type,
                    'basePrice'      => $basePrice,
                    'finalPrice'     => $finalSeatPrice,
                    'ticketType'     => $ticketTypeInfo,
                    'priceBreakdown' => $breakdownJson,
                ];
            }

            // ========== GIÁ SNACK ==========
            // Note: Snacks và promotionCode không được lock ở bước này
            // Chỉ lock ghế, giá snacks và discount sẽ tính ở bước price-preview hoặc checkout
            $snackItems      = [];
            $snacksSubtotal  = 0.0;

            $subtotal = $ticketSubtotal + $snacksSubtotal;

            // ========== DISCOUNT ==========
            $userId        = $sessionContext->userId;
            $promotionCode = null; // Không có promotion code ở bước lock seats

            $discountResult = $this->priceCalculationService
                ->calculateDiscounts($subtotal, $userId, $promotionCode);

            $totalDiscount      = (float) $discountResult->totalDiscount;
            $membershipDiscount = (float) $discountResult->membershipDiscount;
            $promotionDiscount  = (float) $discountResult->promotionDiscount;
            $discountReason     = $discountResult->discountReason;

            $finalTotal = max(0.0, $subtotal - $totalDiscount);

            $now       = Carbon::now();
            $expiresAt = $now->clone()->addSeconds($ttlSeconds);

            $priceSummary = [
                'ticketSubtotal'      => $ticketSubtotal,
                'snacksSubtotal'      => $snacksSubtotal,
                'subtotal'            => $subtotal,
                'membershipDiscount'  => $membershipDiscount,
                'promotionDiscount'   => $promotionDiscount,
                'totalDiscount'       => $totalDiscount,
                'finalTotal'          => $finalTotal,
                'discountReason'      => $discountReason,
                'currency'            => config('currency.base_currency', 'VND'),
            ];

            return new LockSeatsResponse(
                lockToken:      $lockToken,
                showtimeId:     (string) $showtime->showtime_id,
                remainSeconds:  $ttlSeconds,
                expiresAtIso:   $expiresAt->toIso8601String(),
                seatItems:      $seatItems,
                snackItems:     $snackItems,
                priceSummary:   $priceSummary,
            );
        });
    }

    /**
     * READ-ONLY: availability cho showtime (used by SeatLockController)
     */
    public function checkAvailability(string $showtimeId, ?\App\DTO\SessionContext $sessionContext): array
    {
        $result = $this->redisLockService->getAvailabilityForShowtime($showtimeId);
        
        // Nếu có sessionContext, thêm sessionLockInfo
        if ($sessionContext) {
            $result['sessionLockInfo'] = [
                'lockId' => null,
                'myLockedSeats' => [],
                'remainingSeconds' => 0
            ];
        }
        
        return $result;
    }

    /**
     * Giải phóng lock (manual)
     */
    public function releaseSeats(string $showtimeId): void
    {
        $this->redisLockService->releaseAllLocksForShowtime($showtimeId);
    }

    /* ============================================================
     *  BOOKING LIST / DETAIL / QR (My bookings)
     *  => các hàm BookingController đang gọi mà báo Undefined method
     * ============================================================
     */

    /**
     * Lấy danh sách booking của 1 user (GET /bookings/my-bookings)
     */
    public function getUserBookings(string $userId): array
    {
        $bookings = $this->bookingModel->newQuery()
            ->with(['showtime.movie', 'showtime.room.cinema', 'bookingSeats.showtimeSeat.seat'])
            ->where('user_id', $userId)
            ->orderByDesc('booked_at')
            ->get();

        return $bookings
            ->map(fn(Booking $b) => $this->mapBookingToResponse($b))
            ->all();
    }

    /**
     * Chi tiết booking của user (GET /bookings/{bookingId})
     */
    public function getBookingByIdForUser(string $bookingId, string $userId): array
    {
        /** @var Booking|null $booking */
        $booking = $this->bookingModel->newQuery()
            ->with(['showtime.movie', 'showtime.room.cinema', 'bookingSeats.showtimeSeat.seat'])
            ->where('booking_id', $bookingId)
            ->where('user_id', $userId)
            ->first();

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        return $this->mapBookingToResponse($booking);
    }

    /**
     * Cập nhật QR (PATCH /bookings/{bookingId}/qr)
     */
    public function updateQrCode(string $bookingId, string $qrCodeUrl): array
    {
        /** @var Booking|null $booking */
        $booking = $this->bookingModel->newQuery()->find($bookingId);

        if (!$booking) {
            throw new ResourceNotFoundException('Booking not found');
        }

        $booking->qr_code_url = $qrCodeUrl;
        $booking->save();

        return $this->mapBookingToResponse($booking);
    }

    /* ============================================================
     *  PRICE PREVIEW + CONFIRM BOOKING
     *  (sẽ bám đúng spec bạn gửi, nhưng hiện mình để TODO)
     * ============================================================
     */

    /**
     * POST /bookings/price-preview
     */
    public function calculatePricePreview(array $payload, $user, ?string $sessionId): array
    {
        $lockId = $payload['lockId'];
        $promotionCode = $payload['promotionCode'] ?? null;
        $snacks = $payload['snacks'] ?? [];

        // Lấy thông tin lock từ Redis
        $lockData = $this->redisLockService->getLockData($lockId);
        if (!$lockData) {
            throw new CustomException('Lock not found or expired', Response::HTTP_NOT_FOUND);
        }

        // Tính giá vé (tickets subtotal)
        $ticketSubtotal = (float) ($lockData['ticketSubtotal'] ?? 0);

        // Tính giá snacks
        $snacksSubtotal = 0.0;
        if (!empty($snacks)) {
            $snackIds = array_map(fn($s) => $s['snackId'], $snacks);
            $snackModels = $this->snackModel->newQuery()
                ->whereIn('snack_id', $snackIds)
                ->get()
                ->keyBy('snack_id');

            foreach ($snacks as $s) {
                $snack = $snackModels[$s['snackId']] ?? null;
                if ($snack) {
                    $qty = max(0, (int) $s['quantity']);
                    $snacksSubtotal += (float) $snack->price * $qty;
                }
            }
        }

        $subtotal = $ticketSubtotal + $snacksSubtotal;

        // Tính discount
        $userId = $user?->user_id;
        $discountResult = $this->priceCalculationService
            ->calculateDiscounts($subtotal, $userId, $promotionCode);

        $discount = (float) $discountResult->totalDiscount;
        $total = max(0.0, $subtotal - $discount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total'    => $total,
        ];
    }

    /**
     * POST /bookings/confirm
     */
    public function confirmBooking(array $payload, $user, ?string $sessionId): array
    {
        return DB::transaction(function () use ($payload, $user, $sessionId) {
            $lockId = $payload['lockId'];
            $promotionCode = $payload['promotionCode'] ?? null;
            $snackCombos = $payload['snackCombos'] ?? [];
            $guestInfo = $payload['guestInfo'] ?? null;

            // Kiểm tra lock
            $lockData = $this->redisLockService->getLockData($lockId);
            if (!$lockData) {
                throw new CustomException('Lock not found or expired', Response::HTTP_NOT_FOUND);
            }

            $showtimeId = $lockData['showtimeId'];
            $seatIds = $lockData['seatIds'] ?? [];

            // Nếu là guest, tạo user account
            if (!$user && $guestInfo) {
                $user = $this->createGuestUser($guestInfo);
            }

            if (!$user) {
                throw new CustomException('User information required', Response::HTTP_BAD_REQUEST);
            }

            // Tạo booking
            $booking = $this->createBookingFromLock(
                $lockData,
                $user->user_id,
                $promotionCode,
                $snackCombos
            );

            // Giải phóng lock (xóa lock data)
            $seatIds = $lockData['seatIds'] ?? [];
            if (!empty($seatIds)) {
                $this->redisLockService->releaseMultipleSeatsLock($showtimeId, $seatIds, $lockId);
            }

            return $this->mapBookingToResponse($booking);
        });
    }

    /**
     * Tạo guest user account
     */
    private function createGuestUser(array $guestInfo)
    {
        $user = new \App\Models\User();
        $user->user_id = \Illuminate\Support\Str::uuid()->toString();
        $user->username = $guestInfo['username'];
        $user->email = $guestInfo['email'];
        $user->phone_number = $guestInfo['phoneNumber'] ?? null;
        $user->role = 'GUEST';
        $user->password = bcrypt(\Illuminate\Support\Str::random(32)); // random password
        $user->save();

        return $user;
    }

    /**
     * Tạo booking từ lock data
     */
    private function createBookingFromLock(array $lockData, string $userId, ?string $promotionCode, array $snackCombos)
    {
        // Logic này cần implement chi tiết hơn
        // Tạm thời throw exception để báo chưa hoàn thiện
        throw new CustomException(
            'createBookingFromLock() needs full implementation with seat booking, snacks, and price calculation',
            Response::HTTP_NOT_IMPLEMENTED
        );
    }

    /* ============================================================
     *  HELPER: map Booking -> BookingResponse (đúng spec Java)
     * ============================================================
     */

    private function mapBookingToResponse(Booking $booking): array
    {
        $showtime = $booking->showtime;
        $movie    = $showtime?->movie;
        $room     = $showtime?->room;
        $cinema   = $room?->cinema;

        $seats = [];
        foreach ($booking->bookingSeats as $bs) {
            $ss   = $bs->showtimeSeat;
            $seat = $ss?->seat;

            $seats[] = [
                'rowLabel'        => $seat?->row_label,
                'seatNumber'      => $seat?->seat_number,
                'seatType'        => $seat?->seat_type,
                'ticketTypeLabel' => $bs->ticket_type_label ?? null,
                'price'           => (float) $bs->price,
            ];
        }

        return [
            'bookingId'         => (string) $booking->booking_id,
            'showtimeId'        => (string) $booking->showtime_id,
            'movieTitle'        => $movie?->title,
            'showtimeStartTime' => optional($showtime?->start_time)->toIso8601String(),
            'cinemaName'        => $cinema?->name,
            'roomName'          => $room
                ? trim(($room->room_type ?? '') . ' ' . ($room->room_number ?? ''))
                : null,
            'seats'             => $seats,
            'totalPrice'        => (float) ($booking->total_price ?? 0),
            'discountReason'    => $booking->discount_reason,
            'discountValue'     => (float) ($booking->discount_value ?? 0),
            'finalPrice'        => (float) ($booking->final_price ?? 0),
            'status'            => method_exists($booking->status, 'value')
                ? $booking->status->value
                : $booking->status,
            'bookedAt'          => optional($booking->booked_at)->toIso8601String(),
            'qrCode'            => $booking->qr_code_url,
            'qrPayload'         => $booking->qr_payload,
            'paymentExpiresAt'  => optional($booking->payment_expires_at)->toIso8601String(),
        ];
    }
}
