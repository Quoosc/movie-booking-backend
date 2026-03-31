<?php

return [
    'receiver_email' => env('CONTACT_RECEIVER_EMAIL', env('MAIL_FROM_ADDRESS', '')),
    'receiver_name' => env('CONTACT_RECEIVER_NAME', env('APP_NAME', 'Movie Booking')),
    'notify_enabled' => (bool) env('CONTACT_NOTIFY_ENABLED', true),
];
