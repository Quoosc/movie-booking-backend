<?php

return [
    'client_id' => env('PAYPAL_CLIENT_ID', ''),
    'secret' => env('PAYPAL_CLIENT_SECRET', env('PAYPAL_SECRET', '')),
    'mode' => env('PAYPAL_MODE', 'sandbox'),
    'return_url' => env('PAYPAL_RETURN_URL', env('APP_URL') . '/payments/paypal/return'),
    'cancel_url' => env('PAYPAL_CANCEL_URL', env('APP_URL') . '/payments/paypal/cancel'),
    'currency' => env('PAYMENT_PAYPAL_CURRENCY', env('PAYPAL_CURRENCY', 'USD')),
];
