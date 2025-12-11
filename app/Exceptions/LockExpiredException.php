<?php

namespace App\Exceptions;

use Exception;

class LockExpiredException extends Exception
{
    public function __construct(string $message = 'Seat lock has expired', int $code = 410)
    {
        parent::__construct($message, $code);
    }
}
