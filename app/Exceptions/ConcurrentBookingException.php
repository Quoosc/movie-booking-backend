<?php

namespace App\Exceptions;

use Exception;

class ConcurrentBookingException extends Exception
{
    public function __construct(string $message = 'You have an active booking in progress for another showtime', int $code = 409)
    {
        parent::__construct($message, $code);
    }
}
