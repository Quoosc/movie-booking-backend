<?php

namespace App\Exceptions;

class BookingNotFoundException extends \Exception
{
    protected $code = 404;

    public function __construct(string $message = "Booking not found")
    {
        parent::__construct($message);
    }
}
