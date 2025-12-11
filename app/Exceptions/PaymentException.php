<?php

namespace App\Exceptions;

class PaymentException extends \Exception
{
    protected $code = 400;

    public function __construct(string $message = "Payment error occurred")
    {
        parent::__construct($message);
    }
}
