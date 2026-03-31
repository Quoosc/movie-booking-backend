<?php

namespace App\ValueObjects;

// app/ValueObjects/DiscountResult.php
class DiscountResult
{
    public function __construct(
        public float  $totalDiscount,
        public float  $membershipDiscount,
        public float  $promotionDiscount,
        public ?string $discountReason,
    ) {}
}
