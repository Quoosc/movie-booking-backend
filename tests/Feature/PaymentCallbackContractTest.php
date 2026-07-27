<?php

namespace Tests\Feature;

use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Repositories\PaymentRepository;
use App\Modules\Payment\Services\MomoService;
use App\Modules\Payment\Services\PayPalService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PaymentCallbackContractTest extends TestCase
{
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
            Mockery::mock(PaymentRepository::class)
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
            Mockery::mock(PaymentRepository::class)
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
