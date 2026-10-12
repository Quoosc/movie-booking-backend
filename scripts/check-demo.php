<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

echo 'Database: '.(DB::selectOne('SELECT 1 AS ok')->ok === 1 ? 'OK' : 'unexpected')."\n";
if (in_array('--redis', $argv, true)) {
    Redis::ping();
    echo "Redis: OK\n";
    $locks = app(App\Shared\Services\RedisLockService::class);
    $key = 'deployment-check:'.Illuminate\Support\Str::uuid();
    $token = bin2hex(random_bytes(16));
    try {
        if (!$locks->acquireLock($key, $token, 15) || $locks->acquireLock($key, 'another-owner', 15)) {
            throw new RuntimeException('Redis exclusive-lock check failed.');
        }
        echo "Redis exclusive lock: OK (second owner rejected)\n";
    } finally {
        $locks->releaseLock($key, $token);
    }
}
foreach (['movies', 'cinemas', 'showtimes', 'snacks'] as $table) {
    echo $table.': '.DB::table($table)->count()." records\n";
}
$days = DB::table('showtimes')
    ->selectRaw('DATE(DATE_ADD(start_time, INTERVAL 7 HOUR)) AS day, COUNT(*) AS total')
    ->where('is_active', true)->groupBy('day')->orderBy('day')->get();
foreach ($days as $day) {
    echo 'Showtimes (Vietnam): '.$day->day.' = '.$day->total."\n";
}
foreach (['movies' => 'poster_url', 'snacks' => 'image_url', 'hero_slides' => 'image_url'] as $table => $column) {
    if (!Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
        continue;
    }
    $localImages = DB::table($table)->where(function ($query) use ($column) {
        $query->where($column, 'like', '%localhost%')->orWhere($column, 'like', '%127.0.0.1%');
    })->count();
    echo "$table: $localImages image URL(s) refer to localhost\n";
}
