<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = DB::table('payments')
    ->whereNull('booking_id')
    ->orWhereNotIn('booking_id', function ($q) {
        $q->select('booking_id')->from('bookings');
    })
    ->delete();

echo "Deleted {$count} orphan payments" . PHP_EOL;
