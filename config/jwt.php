<?php

return [
    'secret'      => env('JWT_SECRET', env('APP_KEY')),
    'ttl'         => env('JWT_TTL', 500), // minutes
    'refresh_ttl' => env('JWT_REFRESH_TTL', 60 * 24 * 30), // minutes
    'issuer'      => env('JWT_ISSUER', 'movie-booking-backend-laravel'),
];
