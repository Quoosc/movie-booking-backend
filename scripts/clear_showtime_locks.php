<?php
// scripts/clear_showtime_locks.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;

$showtimeId = $argv[1] ?? null;
if (!$showtimeId) {
    echo "Usage: php clear_showtime_locks.php <showtimeId>\n";
    exit(1);
}

$pattern = 'lock:seat:' . $showtimeId . ':*';
$keys = Redis::keys($pattern);
if (!empty($keys)) {
    // Redis::del accepts multiple args; expand array
    Redis::del(...$keys);
    echo count($keys) . " keys deleted\n";
} else {
    echo "no keys\n";
}
