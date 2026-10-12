<?php

// Read-only database snapshot. The output is private and must not be committed.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.'.config('database.default'));
if (($config['driver'] ?? '') !== 'mysql' || !empty($config['url'])) {
    fwrite(STDERR, "This export expects a MySQL connection configured with DB_HOST/DB_DATABASE.\n");
    exit(1);
}
$root = dirname(__DIR__);
$directory = $root.'/.demo-deploy';
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$output = $directory.'/demo.sql';
if (file_exists($output)) {
    fwrite(STDERR, "Snapshot already exists at .demo-deploy/demo.sql; move it aside before creating another.\n");
    exit(1);
}
$executable = $argv[1] ?? 'mysqldump';
$quote = static fn ($value) => '"'.str_replace(
    ["\\", '"', "\r", "\n"], ["\\\\", '\\"', '\\r', '\\n'], (string) $value
).'"';
$credentials = tempnam(sys_get_temp_dir(), 'cineon-dump-');
chmod($credentials, 0600);
$options = "[client]\n";
foreach (['host' => 'host', 'port' => 'port', 'user' => 'username', 'password' => 'password'] as $key => $field) {
    $options .= $key.'='.$quote($config[$field] ?? '')."\n";
}
file_put_contents($credentials, $options);
try {
    $process = proc_open([
        $executable,
        '--defaults-extra-file='.$credentials,
        '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF',
        '--hex-blob', '--default-character-set=utf8mb4', '--skip-add-drop-table',
        '--skip-add-locks', '--skip-lock-tables', '--result-file='.$output,
        $config['database'],
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start mysqldump.');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !filesize($output)) {
        if (file_exists($output)) {
            unlink($output); // Only remove the incomplete file created by this run.
        }
        throw new RuntimeException('MySQL export failed. Check connection and client version.');
    }
    chmod($output, 0600);
    echo 'Private snapshot created: .demo-deploy/demo.sql ('.filesize($output)." bytes)\n";
    foreach (['movies', 'cinemas', 'showtimes', 'snacks'] as $table) {
        if (Illuminate\Support\Facades\Schema::hasTable($table)) {
            echo $table.': '.Illuminate\Support\Facades\DB::table($table)->count()." records\n";
        }
    }
} finally {
    unlink($credentials);
}
