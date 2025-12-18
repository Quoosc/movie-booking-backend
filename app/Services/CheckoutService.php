<?php

namespace App\Services;

use App\DTO\SessionContext;
use App\Enums\{BookingStatus, SeatStatus};
use App\Exceptions\{ResourceNotFoundException, LockExpiredException, CustomException};
use App\Models\{SeatLock, Booking, User, BookingSeat, BookingSnack, BookingPromotion};
use App\Repositories\{SeatLockRepository, BookingRepository, ShowtimeSeatRepository};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\TicketTypeService;

class CheckoutService
{
    protected int $paymentTimeoutMinutes;

    public function __construct(
        protected SeatLockRepository $seatLockRepository,
        protected BookingRepository $bookingRepository,
        protected ShowtimeSeatRepository $showtimeSeatRepository,
        protected PriceCalculationService $priceCalculationService,
        protected TicketTypeService $ticketTypeService,
        protected PayPalService $paypalService,
        protected MomoService $momoService,
        protected RedisLockService $redisLockService,
        protected BookingService $bookingService
    ) {
        $this->paymentTimeoutMinutes = config('booking.payment.timeout.minutes', 15);
    }

    /**
     * CONFIRM BOOKING (without payment)
     * POST /api/bookings/confirm
     */
    public function confirmBooking(array $requestData, SessionContext $sessionContext): array
    {
        return DB::transaction(function () use ($requestData, $sessionContext) {
            $lockId = $requestData['lockId'];
            $promotionCode = $requestData['promotionCode'] ?? null;
            $snackCombos = $requestData['snackCombos'] ?? [];
            $guestInfo = $requestData['guestInfo'] ?? null;

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

            if ($promotionCode && !$sessionContext->isAuthenticated()) {
                throw new CustomException('Guests cannot use promotions', 403);
            }

            $user = $this->getOrCreateUser($sessionContext, $guestInfo);

            if ($sessionContext->isGuest() && $user) {
                $seatLock->user_id = $user->user_id;
                $this->seatLockRepository->save($seatLock);
            }

            $pricingData = $this->calculateBookingPrice(
                $seatLock,
                $snackCombos,
                $promotionCode,
                $sessionContext->isAuthenticated() ? $user->user_id : null
            );

            // Ensure seats are loaded for persistence and logging
            $seatLock->loadMissing('seatLockSeats');
            $booking = $this->createBooking($user, $seatLock, $pricingData, $snackCombos);
            Log::info('[booking.confirm] persisted booking seats', [
                'bookingId' => $booking->booking_id,
                'seatCount' => $booking->bookingSeats()->count(),
            ]);

            $seatIds = $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray();
            $this->showtimeSeatRepository->updateStatusBatch($seatIds, SeatStatus::BOOKED->value);

            $this->redisLockService->releaseMultipleSeatsLock(
                $seatLock->showtime_id,
                $seatIds,
                $seatLock->lock_key
            );

            $this->seatLockRepository->delete($seatLock);

            return $this->mapBookingToResponse($booking);
        });
    }

    /**
     * CONFIRM BOOKING AND INITIATE PAYMENT
     * POST /api/checkout
     */
    public function confirmBookingAndInitiatePayment(array $requestData, SessionContext $sessionContext): array
    {
        return DB::transaction(function () use ($requestData, $sessionContext) {
            $bookingResponse = $this->confirmBooking($requestData, $sessionContext);

            $bookingId = $bookingResponse['bookingId'];
            $paymentMethod = $requestData['paymentMethod'];
            $amount = $bookingResponse['finalPrice'];

            $paymentResult = $this->initiatePayment($bookingId, $paymentMethod, $amount);

            return [
                'bookingId' => $bookingId,
                'paymentId' => $paymentResult['paymentId'],
                'paymentMethod' => $paymentMethod,
                'redirectUrl' => $paymentResult['paymentUrl'] ?? $paymentResult['redirectUrl'] ?? null,
                'message' => 'Booking confirmed and payment initiated',
            ];
        });
    }

    private function getOrCreateUser(SessionContext $sessionContext, ?array $guestInfo): User
    {
        if ($sessionContext->isAuthenticated()) {
            $user = User::find($sessionContext->getUserId());
            if (!$user) {
                throw new ResourceNotFoundException('User not found');
            }
            return $user;
        }

        if (!$guestInfo) {
            throw new CustomException('Guest information required', 400);
        }

        $existingUser = User::where('email', $guestInfo['email'])->first();
        if ($existingUser) {
            return $existingUser;
        }

        $fullName = $guestInfo['fullName'] ?? $guestInfo['username'] ?? null;

        $user = new User([
            'user_id' => Str::uuid()->toString(),
            'username' => $fullName ?? ($guestInfo['email'] ?? 'guest'),
            'email' => $guestInfo['email'],
            'phoneNumber' => $guestInfo['phoneNumber'] ?? null,
            'role' => 'GUEST',
            'password' => bcrypt(Str::random(32)),
        ]);
        $user->save();

        return $user;
    }

    /**
     * Calculate Booking Price
     */
    private function calculateBookingPrice(
        SeatLock $seatLock,
        array $snackCombos,
        ?string $promotionCode,
        ?string $userId
    ): array {
        // Recalculate ticket subtotal using base price + modifiers + ticket type
        $seatLock->loadMissing([
            'seatLockSeats.ticketType',
            'seatLockSeats.showtimeSeat.seat',
            'showtime',
        ]);

        $ticketSubtotal = 0;
        $ticketItems = [];

        foreach ($seatLock->seatLockSeats as $lockSeat) {
            $showtimeSeat = $lockSeat->showtimeSeat;
            $seat = $showtimeSeat?->seat;
            $ticketType = $lockSeat->ticketType;

            if (!$showtimeSeat || !$seat) {
                continue;
            }

            $basePrice = $this->priceCalculationService->calculatePrice($seatLock->showtime, $seat);
            $finalPrice = $ticketType
                ? $this->ticketTypeService->applyTicketTypeModifier($basePrice, $ticketType)
                : $basePrice;

            $ticketSubtotal += $finalPrice;

            $ticketItems[] = [
                'seatLockSeatId' => $lockSeat->id,
                'showtimeSeatId' => $lockSeat->showtime_seat_id,
                'ticketTypeId' => $lockSeat->ticket_type_id,
                'price' => $finalPrice,
            ];
        }
        $snackSubtotal = 0;
        $snackItems = [];

        foreach ($snackCombos as $combo) {
            $snack = \App\Models\Snack::find($combo['snackId']);
            if ($snack) {
                $quantity = $combo['quantity'];
                $lineTotal = $snack->price * $quantity;
                $snackSubtotal += $lineTotal;

                $snackItems[] = [
                    'snackId' => $snack->snack_id,
                    'quantity' => $quantity,
                    'unitPrice' => $snack->price,
                    'totalPrice' => $lineTotal,
                ];
            }
        }

        $subtotal = $ticketSubtotal + $snackSubtotal;

        if ($promotionCode && !$userId) {
            throw new CustomException('Promotions are available for registered users only', 403);
        }

        $discountResult = $this->priceCalculationService->calculateDiscounts(
            $subtotal,
            $userId,
            $promotionCode
        );

        $totalDiscount = $discountResult->totalDiscount ?? 0;
        $discountReason = $discountResult->discountReason ?? null;
        $finalPrice = max(0, $subtotal - $totalDiscount);

        return [
            'totalPrice' => $subtotal,
            'discountValue' => $totalDiscount,
            'discountReason' => $discountReason,
            'finalPrice' => $finalPrice,
            'ticketItems' => $ticketItems,
            'snackItems' => $snackItems,
            'promotionCode' => $promotionCode,
        ];
    }

    /**
     * Create Booking
     */
    private function createBooking(
        User $user,
        SeatLock $seatLock,
        array $pricingData,
        array $snackCombos
    ): Booking {
        $booking = new Booking([
            'user_id' => $user->user_id,
            'showtime_id' => $seatLock->showtime_id,
            'booked_at' => Carbon::now(),
            'total_price' => $pricingData['totalPrice'],
            'discount_reason' => $pricingData['discountReason'],
            'discount_value' => $pricingData['discountValue'],
            'final_price' => $pricingData['finalPrice'],
            'status' => BookingStatus::PENDING_PAYMENT,
            'payment_expires_at' => Carbon::now()->addMinutes($this->paymentTimeoutMinutes),
        ]);
        $booking = $this->bookingRepository->create($booking->toArray());

        foreach ($seatLock->seatLockSeats as $seatLockSeat) {
            $ticketItem = collect($pricingData['ticketItems'] ?? [])
                ->firstWhere('seatLockSeatId', $seatLockSeat->id);
            $seatPrice = $ticketItem['price'] ?? $seatLockSeat->price;
            $bookingSeat = new BookingSeat([
                'booking_id' => $booking->booking_id,
                'showtime_seat_id' => $seatLockSeat->showtime_seat_id,
                'seat_lock_seat_id' => $seatLockSeat->id,
                'ticket_type_id' => $seatLockSeat->ticket_type_id,
                //'price' => $seatLockSeat->price,
                'price' => $seatPrice,
            ]);
            $bookingSeat->save();
        }

        foreach ($pricingData['snackItems'] as $snackItem) {
            $bookingSnack = new BookingSnack([
                'booking_id' => $booking->booking_id,
                'snack_id' => $snackItem['snackId'],
                'quantity' => $snackItem['quantity'],
            ]);
            $bookingSnack->save();
        }

        if (!empty($pricingData['promotionCode'])) {
            $promotion = \App\Models\Promotion::where('code', $pricingData['promotionCode'])->first();
            if ($promotion) {
                $bookingPromotion = new BookingPromotion([
                    'booking_id' => $booking->booking_id,
                    'promotion_id' => $promotion->promotion_id,
                    'discount_amount' => $pricingData['discountValue'],
                    'applied_at' => now(),
                ]);
                $bookingPromotion->save();
            }
        }

        return $booking->fresh(['bookingSeats', 'bookingSnacks', 'bookingPromotions', 'showtime.movie', 'showtime.room.cinema']);
    }

    /**
     * Initiate Payment
     */
    private function initiatePayment(string $bookingId, string $paymentMethod, float $amount): array
    {
        $paymentRequest = new \App\DTO\Payments\InitiatePaymentRequest(
            bookingId: $bookingId,
            amount: $amount
        );

        switch (strtoupper($paymentMethod)) {
            case 'PAYPAL':
                $response = $this->paypalService->createOrder($paymentRequest);
                return [
                    'paymentId' => $response->paymentId,
                    'paymentUrl' => $response->approvalUrl,
                    'orderId' => $response->paypalOrderId,
                ];
            case 'MOMO':
                $response = $this->momoService->createOrder($paymentRequest);
                return [
                    'paymentId' => $response->paymentId,
                    'paymentUrl' => $response->approvalUrl,
                    'orderId' => $response->momoOrderId,
                ];
            default:
                throw new CustomException('Unsupported payment method', 400);
        }
    }

    /**
     * Map Booking to Response
     */
    private function mapBookingToResponse(Booking $booking): array
    {
        return $this->bookingService->mapBookingToResponse($booking);
    }
}
