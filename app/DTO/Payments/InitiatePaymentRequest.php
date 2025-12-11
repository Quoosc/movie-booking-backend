<?php
// app/DTO/Payments/InitiatePaymentRequest.php
namespace App\DTO\Payments;

class InitiatePaymentRequest
{
    public function __construct(
        public string $bookingId,
        public float  $amount,
    ) {}
}
