<?php

namespace Tests\Feature;

use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Repositories\PaymentRepository;
use App\Modules\Payment\Services\MomoService;
use App\Modules\Payment\Services\PayPalService;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Payment\DTO\InitiatePaymentResponse;
use App\Models\Booking;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PaymentCallbackContractTest extends TestCase
{
    public function test_payment_order_uses_server_booking_total_and_guest_capability(): void
    {
        $bookingId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $booking = new Booking();
        $booking->forceFill([
            'booking_id' => $bookingId,
            'user_id' => 'guest-1',
            'final_price' => 250000,
        ]);

        $bookingService = Mockery::mock(BookingService::class);
        $bookingService->shouldReceive('getBookingById')
            ->once()
            ->with($bookingId)
            ->andReturn($booking);
        $bookingService->shouldReceive('hasValidGuestAccessToken')
            ->once()
            ->with($booking, 'guest-capability')
            ->andReturnTrue();

        $momo = Mockery::mock(MomoService::class);
        $momo->shouldReceive('createOrder')
            ->once()
            ->with(Mockery::on(fn ($dto) =>
                $dto->bookingId === $bookingId && $dto->amount === 250000.0
            ))
            ->andReturn(new InitiatePaymentResponse(
                paymentId: 'payment-1',
                paypalOrderId: null,
                momoOrderId: 'momo-1',
                approvalUrl: 'https://payment.example/checkout',
            ));

        $controller = new PaymentController(
            $momo,
            Mockery::mock(PayPalService::class),
            Mockery::mock(PaymentRepository::class),
            $bookingService,
        );
        $request = Request::create('/api/payments/order', 'POST', [
            'bookingId' => $bookingId,
            'paymentMethod' => 'MOMO',
            'amount' => 1,
        ]);
        $request->headers->set('X-Booking-Access-Token', 'guest-capability');

        $response = $controller->initiatePayment($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('payment-1', $response->getData(true)['paymentId']);
    }

    public function test_momo_status_reads_the_server_side_ipn_result(): void
    {
        $momo = Mockery::mock(MomoService::class);
        $momo->shouldReceive('verifyPayment')
            ->once()
            ->with('momo-order-1')
            ->andReturn([
                'status' => 'SUCCESS',
                'bookingId' => 'booking-1',
            ]);

        $controller = new PaymentController(
            $momo,
            Mockery::mock(PayPalService::class),
            Mockery::mock(PaymentRepository::class),
            Mockery::mock(BookingService::class),
        );

        $request = Request::create('/api/payments/order/status', 'GET', [
            'transactionId' => 'momo-order-1',
            'paymentMethod' => 'MOMO',
        ]);

        $response = $controller->paymentStatus($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('SUCCESS', $response->getData(true)['status']);
        $this->assertSame('booking-1', $response->getData(true)['bookingId']);
    }

    public function test_legacy_momo_capture_never_captures_from_browser_data(): void
    {
        $momo = Mockery::mock(MomoService::class);
        $momo->shouldReceive('verifyPayment')
            ->once()
            ->with('momo-order-1')
            ->andReturn(['status' => 'PENDING', 'bookingId' => 'booking-1']);

        $controller = new PaymentController(
            $momo,
            Mockery::mock(PayPalService::class),
            Mockery::mock(PaymentRepository::class),
            Mockery::mock(BookingService::class),
        );

        $request = Request::create('/api/payments/order/capture', 'POST', [
            'transactionId' => 'momo-order-1',
            'paymentMethod' => 'MOMO',
        ]);

        $response = $controller->capturePayment($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('PENDING', $response->getData(true)['status']);
    }
}
