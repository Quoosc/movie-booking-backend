<?php

return [
    'lock' => [
        'duration' => [
            'minutes' => env('BOOKING_LOCK_DURATION_MINUTES', 30),
        ],
    ],
    'max' => [
        'seats' => env('BOOKING_MAX_SEATS', 10),
    ],
    'payment' => [
        'timeout' => [
            'minutes' => env('BOOKING_PAYMENT_TIMEOUT_MINUTES', 15),
        ],
    ],
];
