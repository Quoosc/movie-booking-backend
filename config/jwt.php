<?php

return [
    'secret' => env('JWT_SECRET', 'change-me'),
    'ttl'    => env('JWT_TTL', 500), // minutes
    'issuer' => env('JWT_ISSUER', 'movie-booking-backend-laravel'),
];
