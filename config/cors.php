<?php

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 86400,

    'supports_credentials' => true,
];
