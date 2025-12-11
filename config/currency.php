<?php

return [
    'exchange_api'       => env('CURRENCY_EXCHANGE_API', 'https://latest.currency-api.pages.dev/v1/currencies'),
    'exchange_cache_ttl' => env('CURRENCY_EXCHANGE_CACHE_TTL', 900),
    'base_currency'      => env('CURRENCY_BASE', 'VND'),
    'paypal_currency'    => env('PAYPAL_CURRENCY', 'USD'),
];
