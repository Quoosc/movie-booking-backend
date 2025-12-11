<?php

namespace App\Exceptions;

use Exception;

class SeatLockedException extends Exception
{
    public array $unavailableSeats;

    public function __construct(string $message, array $unavailableSeats, int $code = 409)
    {
        parent::__construct($message, $code);
        $this->unavailableSeats = $unavailableSeats;
    }

    public function getUnavailableSeats(): array
    {
        return $this->unavailableSeats;
    }
}
