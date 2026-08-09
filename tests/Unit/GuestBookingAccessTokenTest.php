<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Modules\Booking\Repositories\BookingRepository;
use App\Modules\Booking\Repositories\SeatLockRepository;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Pricing\Services\PriceCalculationService;
use App\Modules\Showtime\Repositories\ShowtimeSeatRepository;
use App\Modules\Ticketing\Services\TicketTypeService;
use App\Shared\Services\RedisLockService;
use Mockery;
use Tests\TestCase;

class GuestBookingAccessTokenTest extends TestCase
{
    public function test_guest_booking_token_is_deterministic_and_tamper_resistant(): void
    {
        config(['app.key' => 'base64:test-secret-key']);

        $service = new BookingService(
            Mockery::mock(SeatLockRepository::class),
            Mockery::mock(ShowtimeSeatRepository::class),
            Mockery::mock(BookingRepository::class),
            Mockery::mock(RedisLockService::class),
            Mockery::mock(PriceCalculationService::class),
            Mockery::mock(TicketTypeService::class),
        );

        $booking = new Booking();
        $booking->forceFill([
            'booking_id' => 'booking-1',
            'user_id' => 'guest-user-1',
        ]);

        $token = $service->createGuestAccessToken($booking);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertTrue($service->hasValidGuestAccessToken($booking, $token));
        $this->assertFalse($service->hasValidGuestAccessToken($booking, str_repeat('0', 64)));
        $this->assertFalse($service->hasValidGuestAccessToken($booking, null));
    }
}
