<?php

namespace App\Exceptions;

class ResourceNotFoundException extends CustomException
{
    public function __construct(
        string $resource,
        string $field = 'id',
        mixed  $value = null
    ) {
        $msg = $value === null
            ? "{$resource} not found"
            : "{$resource} not found with {$field} = {$value}";

        parent::__construct($msg, 404);
    }
}
