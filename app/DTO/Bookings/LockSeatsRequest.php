<?php

namespace App\DTO\Bookings;

class LockSeatsRequest
{
    /**
     * @param string $showtimeId
     * @param array<int, array{showtimeSeatId:string, ticketTypeId:?string}> $seats
     * @param array<int, array{snackId:string, quantity:int}> $snacks
     * @param string|null $promotionCode
     */
    public function __construct(
        public string $showtimeId,
        public array $seats,
        public array $snacks = [],
        public ?string $promotionCode = null,
    ) {}
}
