<?php

return [
    'partner_code' => env('MOMO_PARTNER_CODE', ''),
    'access_key' => env('MOMO_ACCESS_KEY', ''),
    'secret_key' => env('MOMO_SECRET_KEY', ''),
    'endpoint' => env('MOMO_API_ENDPOINT', env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api')),
    'return_url' => env('MOMO_RETURN_URL', env('APP_URL') . '/momo/return'),
    'ipn_url' => env('MOMO_IPN_URL', env('APP_URL') . '/api/payments/momo/ipn'),
];
