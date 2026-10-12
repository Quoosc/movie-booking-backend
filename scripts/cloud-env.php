<?php

// Run on the server (or locally before uploading .env.cloud). No secrets printed.
$root = dirname(__DIR__);
$host = $argv[1] ?? '';
$email = $argv[2] ?? '';
if (!preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)+$/i', $host)
    || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/cloud-env.php API_HOST ACME_EMAIL\n");
    exit(1);
}
$path = $root.'/.env.cloud';
if (file_exists($path)) {
    fwrite(STDERR, ".env.cloud already exists; refusing to replace stable keys.\n");
    exit(1);
}
$contents = file_get_contents($root.'/.env.cloud.example');
$contents = str_replace(['api.example.com', 'you@example.com'], [$host, $email], $contents);
$values = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'JWT_SECRET' => bin2hex(random_bytes(32)),
    'DB_PASSWORD' => bin2hex(random_bytes(24)),
    'DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)),
];
foreach ($values as $key => $value) {
    $contents = preg_replace('/^'.preg_quote($key, '/').'=$/m', $key.'='.$value, $contents);
}
// The private bundle can carry existing sandbox/OAuth credentials, never DB keys.
$providers = $root.'/demo-provider.env';
if (file_exists($providers)) {
    $allowed = ['PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'MOMO_PARTNER_CODE',
        'MOMO_ACCESS_KEY', 'MOMO_SECRET_KEY', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'];
    foreach (file($providers, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        if (in_array($key, $allowed, true)) {
            $contents = preg_replace_callback('/^'.preg_quote($key, '/').'=$/m',
                static fn () => $key.'='.$value, $contents);
        }
    }
}
$previousMask = umask(0077);
$handle = fopen($path, 'x');
if (!$handle || fwrite($handle, $contents) !== strlen($contents)) {
    fwrite(STDERR, "Failed to create .env.cloud.\n");
    exit(1);
}
fclose($handle);
umask($previousMask);
echo "Created .env.cloud with fresh keys and production URLs. Provider settings imported if present in the private bundle.\n";
