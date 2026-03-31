<?php

namespace App\Modules\Payment\DTO;

// app/DTO/Payments/InitiatePaymentResponse.php
class InitiatePaymentResponse
{
    public function __construct(
        public string  $paymentId,
        public ?string $paypalOrderId,
        public ?string $momoOrderId,
        public ?string $approvalUrl,
    ) {}
}
