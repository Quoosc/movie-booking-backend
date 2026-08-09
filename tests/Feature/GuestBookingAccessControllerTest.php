<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use Mockery;
use Tests\TestCase;

class GuestBookingAccessControllerTest extends TestCase
{
    public function test_guest_can_read_ticket_with_valid_capability_token(): void
    {
        $booking = new Booking();
        $booking->forceFill(['booking_id' => 'booking-1', 'user_id' => 'guest-1']);

        $service = Mockery::mock(BookingService::class);
        $service->shouldReceive('getBookingById')->once()->with('booking-1')->andReturn($booking);
        $service->shouldReceive('hasValidGuestAccessToken')
            ->once()
            ->with($booking, 'valid-token')
            ->andReturnTrue();
        $service->shouldReceive('mapBookingToResponse')
            ->once()
            ->with($booking)
            ->andReturn(['bookingId' => 'booking-1', 'qrPayload' => 'ticket-secret']);
        $this->app->instance(BookingService::class, $service);

        $this->getJson('/api/bookings/booking-1', [
            'X-Booking-Access-Token' => 'valid-token',
        ])->assertOk()
            ->assertJsonPath('data.bookingId', 'booking-1')
            ->assertJsonPath('data.qrPayload', 'ticket-secret');
    }

    public function test_booking_id_alone_no_longer_exposes_guest_ticket(): void
    {
        $booking = new Booking();
        $booking->forceFill(['booking_id' => 'booking-1', 'user_id' => 'guest-1']);

        $service = Mockery::mock(BookingService::class);
        $service->shouldReceive('getBookingById')->once()->with('booking-1')->andReturn($booking);
        $service->shouldReceive('hasValidGuestAccessToken')
            ->once()
            ->with($booking, null)
            ->andReturnFalse();
        $service->shouldNotReceive('mapBookingToResponse');
        $this->app->instance(BookingService::class, $service);

        $this->getJson('/api/bookings/booking-1')
            ->assertForbidden()
            ->assertJsonPath('data', null);
    }
}
