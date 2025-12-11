<?php

namespace App\Exceptions;

class EntityDeletionForbiddenException extends CustomException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 409); // 409 Conflict
    }
}
