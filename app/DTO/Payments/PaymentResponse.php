<?php

namespace App\DTO\Payments;

class PaymentResponse
{
    public function __construct(
        public string  $paymentId,
        public ?string $bookingId,
        public ?string $bookingStatus,
        public ?string $paymentStatus = null,
        public ?string $qrPayload = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'paymentId'     => $this->paymentId,
            'bookingId'     => $this->bookingId,
            'bookingStatus' => $this->bookingStatus,
            'paymentStatus' => $this->paymentStatus,
            'qrPayload'     => $this->qrPayload,
        ];
    }
}
