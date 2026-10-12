<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$values = [];
if (config('paypal.mode') === 'sandbox') {
    $values['PAYPAL_CLIENT_ID'] = config('paypal.client_id');
    $values['PAYPAL_CLIENT_SECRET'] = config('paypal.secret');
}
if (parse_url(config('momo.endpoint'), PHP_URL_HOST) === 'test-payment.momo.vn') {
    $values['MOMO_PARTNER_CODE'] = config('momo.partner_code');
    $values['MOMO_ACCESS_KEY'] = config('momo.access_key');
    $values['MOMO_SECRET_KEY'] = config('momo.secret_key');
}
$values['GOOGLE_CLIENT_ID'] = config('services.google.client_id');
$values['GOOGLE_CLIENT_SECRET'] = config('services.google.client_secret');
$directory = dirname(__DIR__).'/.demo-deploy';
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.'/provider.env';
$mask = umask(0077);
$handle = fopen($path, 'x');
if (!$handle) {
    fwrite(STDERR, "Private provider file already exists or cannot be created.\n");
    exit(1);
}
$count = 0;
foreach ($values as $key => $value) {
    if (is_string($value) && $value !== '') {
        $escaped = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '\\r', '\\n'], $value);
        fwrite($handle, $key.'="'.$escaped.'"'."\n");
        $count++;
    }
}
fclose($handle);
umask($mask);
echo "Private sandbox/OAuth settings exported ($count configured values; secrets not printed).\n";
