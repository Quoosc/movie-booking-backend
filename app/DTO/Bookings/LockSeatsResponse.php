<?php

namespace App\DTO\Bookings;

class LockSeatsResponse
{
    public function __construct(
        public string $lockToken,
        public string $showtimeId,
        public int    $remainSeconds,
        public string $expiresAtIso,
        public array  $seatItems,   // danh sách ghế + giá
        public array  $snackItems,  // danh sách snack + giá
        public array  $priceSummary // tổng hợp: tickets/snacks/discount/final
    ) {}
}
