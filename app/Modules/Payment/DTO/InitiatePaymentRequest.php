<?php

namespace App\Modules\Payment\DTO;

// app/DTO/Payments/InitiatePaymentRequest.php
class InitiatePaymentRequest
{
    public function __construct(
        public string $bookingId,
        public float  $amount,
    ) {}
}
