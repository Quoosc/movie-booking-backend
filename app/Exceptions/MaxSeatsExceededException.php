<?php

namespace App\Exceptions;

use Exception;

class MaxSeatsExceededException extends Exception
{
    public int $maxSeats;
    public int $requestedSeats;

    public function __construct(int $maxSeats, int $requestedSeats, int $code = 400)
    {
        $message = "Maximum seats exceeded. Max: {$maxSeats}, Requested: {$requestedSeats}";
        parent::__construct($message, $code);
        $this->maxSeats = $maxSeats;
        $this->requestedSeats = $requestedSeats;
    }
}
