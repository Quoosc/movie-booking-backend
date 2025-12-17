<?php

return [
    'lock' => [
        'duration' => [
            // Spring spec: seat lock TTL is 10 minutes
            'minutes' => env('BOOKING_LOCK_DURATION_MINUTES', 10),
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
