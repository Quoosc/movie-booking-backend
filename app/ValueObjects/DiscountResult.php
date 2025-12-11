<?php
// app/ValueObjects/DiscountResult.php

namespace App\ValueObjects;

class DiscountResult
{
    public function __construct(
        public float  $totalDiscount,
        public float  $membershipDiscount,
        public float  $promotionDiscount,
        public ?string $discountReason,
    ) {}
}
