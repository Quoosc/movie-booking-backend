<?php

namespace App\DTO\Bookings;

class LockSeatsRequest
{
    /**
     * @param string $showtimeId
     * @param array<int, array{showtimeSeatId:string, ticketTypeId:?string}> $seats
     */
    public function __construct(
        public string $showtimeId,
        public array $seats,
    ) {}
}
