<?php

return [
    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
        'endpoint' => env('MOMO_API_ENDPOINT', env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api')),
        'ipn_url' => env('MOMO_IPN_URL'),
        'return_url' => env('MOMO_RETURN_URL'),
    ],
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_CLIENT_SECRET', env('PAYPAL_SECRET')),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'return_url' => env('PAYPAL_RETURN_URL'),
        'cancel_url' => env('PAYPAL_CANCEL_URL'),
        'currency' => env('PAYMENT_PAYPAL_CURRENCY', env('PAYPAL_CURRENCY', 'USD')),
    ],
    'exchange_rate' => [
        'fallback_rates' => [
            'VND_TO_USD' => 0.00004
        ],
    ],
];
