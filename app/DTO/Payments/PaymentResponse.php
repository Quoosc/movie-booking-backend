<?php

namespace App\DTO\Payments;

class PaymentResponse
{
    public function __construct(
        public string  $paymentId,
        public ?string $bookingId,
        public ?string $bookingStatus,
        public ?string $qrPayload,
    ) {
    }

    public function toArray(): array
    {
        return [
            'paymentId'     => $this->paymentId,
            'bookingId'     => $this->bookingId,
            'bookingStatus' => $this->bookingStatus,
            'qrPayload'     => $this->qrPayload,
        ];
    }
}
