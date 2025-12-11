<?php

namespace App\Services;

use App\DTO\SessionContext;
use App\Enums\{BookingStatus, SeatStatus};
use App\Exceptions\{ResourceNotFoundException, LockExpiredException, CustomException};
use App\Models\{SeatLock, Booking, User, BookingSeat, BookingSnack, BookingPromotion};
use App\Repositories\{SeatLockRepository, BookingRepository, ShowtimeSeatRepository};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CheckoutService
{
    protected int $paymentTimeoutMinutes;

    public function __construct(
        protected SeatLockRepository $seatLockRepository,
        protected BookingRepository $bookingRepository,
        protected ShowtimeSeatRepository $showtimeSeatRepository,
        protected PriceCalculationService $priceCalculationService,
        protected PayPalService $paypalService,
        protected MomoService $momoService
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

            // 1. Find và validate seat lock
            $seatLock = $this->seatLockRepository->findById($lockId);

            if (!$seatLock) {
                throw new ResourceNotFoundException('Lock not found or expired');
            }

            // 2. Check lock ownership
            if ($seatLock->lock_owner_id !== $sessionContext->getLockOwnerId()) {
                throw new CustomException('You do not own this lock', 403);
            }

            // 3. Check lock expiry
            if (!$seatLock->isActive()) {
                throw new LockExpiredException();
            }

            // 4. Get or create User
            $user = $this->getOrCreateUser($sessionContext, $guestInfo);

            // 5. Nếu guest => update seatLock->user_id
            if ($sessionContext->isGuest() && $user) {
                $seatLock->user_id = $user->user_id;
                $this->seatLockRepository->save($seatLock);
            }

            // 6. Calculate pricing
            $pricingData = $this->calculateBookingPrice(
                $seatLock,
                $snackCombos,
                $promotionCode,
                $user->user_id ?? null
            );

            // 7. Create Booking
            $booking = $this->createBooking($user, $seatLock, $pricingData, $snackCombos);

            // 8. Update showtime_seats status = BOOKED
            $seatIds = $seatLock->seatLockSeats->pluck('showtime_seat_id')->toArray();
            $this->showtimeSeatRepository->updateStatusBatch($seatIds, SeatStatus::BOOKED->value);

            // 9. Delete SeatLock
            $this->seatLockRepository->delete($seatLock);

            // 10. Return BookingResponse
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
            // 1. Confirm booking first
            $bookingResponse = $this->confirmBooking($requestData, $sessionContext);
            
            $bookingId = $bookingResponse['bookingId'];
            $paymentMethod = $requestData['paymentMethod'];
            $amount = $bookingResponse['finalPrice'];

            // 2. Initiate payment
            try {
                $paymentResult = $this->initiatePayment($bookingId, $paymentMethod, $amount);

                return [
                    'bookingId' => $bookingId,
                    'paymentId' => $paymentResult['paymentId'],
                    'paymentMethod' => $paymentMethod,
                    'redirectUrl' => $paymentResult['redirectUrl'],
                    'message' => 'Booking confirmed and payment initiated',
                ];
            } catch (\Exception $e) {
                // Payment initiation failed => rollback sẽ tự động xảy ra
                throw new CustomException(
                    'Failed to initiate payment: ' . $e->getMessage(),
                    500
                );
            }
        });
    }

    /**
     * Get or Create User
     */
    private function getOrCreateUser(SessionContext $sessionContext, ?array $guestInfo): User
    {
        // Authenticated user
        if ($sessionContext->isAuthenticated()) {
            $user = User::find($sessionContext->getUserId());
            if (!$user) {
                throw new ResourceNotFoundException('User not found');
            }
            return $user;
        }

        // Guest user
        if ($guestInfo) {
            // Check if email exists
            $existingUser = User::where('email', $guestInfo['email'])->first();
            if ($existingUser) {
                return $existingUser;
            }

            // Create new guest user
            $user = new User([
                'user_id' => Str::uuid()->toString(),
                'username' => $guestInfo['username'],
                'email' => $guestInfo['email'],
                'phone_number' => $guestInfo['phoneNumber'] ?? null,
                'role' => 'GUEST',
                'password' => bcrypt(Str::random(32)), // Random password
            ]);
            $user->save();

            return $user;
        }

        throw new CustomException('User information required for guests', 400);
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
        // Ticket subtotal
        $ticketSubtotal = $seatLock->seatLockSeats->sum('price');

        // Snack subtotal
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

        // Calculate discounts
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
        // Create booking
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

        // Create BookingSeat records
        foreach ($seatLock->seatLockSeats as $seatLockSeat) {
            $bookingSeat = new BookingSeat([
                'booking_id' => $booking->booking_id,
                'showtime_seat_id' => $seatLockSeat->showtime_seat_id,
                'ticket_type_id' => $seatLockSeat->ticket_type_id,
                'price' => $seatLockSeat->price,
            ]);
            $bookingSeat->save();
        }

        // Create BookingSnack records
        foreach ($pricingData['snackItems'] as $snackItem) {
            $bookingSnack = new BookingSnack([
                'booking_id' => $booking->booking_id,
                'snack_id' => $snackItem['snackId'],
                'quantity' => $snackItem['quantity'],
                'unit_price' => $snackItem['unitPrice'],
            ]);
            $bookingSnack->save();
        }

        // Create BookingPromotion record if promotion used
        if (!empty($pricingData['promotionCode'])) {
            $promotion = \App\Models\Promotion::where('code', $pricingData['promotionCode'])->first();
            if ($promotion) {
                $bookingPromotion = new BookingPromotion([
                    'booking_id' => $booking->booking_id,
                    'promotion_id' => $promotion->promotion_id,
                    'discount_value' => $pricingData['discountValue'],
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
                    'paymentUrl' => $response->approvalUrl,
                    'orderId' => $response->paypalOrderId,
                ];
            case 'MOMO':
                $response = $this->momoService->createOrder($paymentRequest);
                return [
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
                'ticketTypeLabel' => $bs->ticketType?->label ?? null,
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
}
