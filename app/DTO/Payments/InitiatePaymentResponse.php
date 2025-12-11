<?php
// app/DTO/Payments/InitiatePaymentResponse.php
namespace App\DTO\Payments;

class InitiatePaymentResponse
{
    public function __construct(
        public string  $paymentId,
        public ?string $paypalOrderId,
        public ?string $momoOrderId,
        public ?string $approvalUrl,
    ) {}
}
