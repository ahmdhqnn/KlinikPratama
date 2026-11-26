<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

if (! is_file($root.'/.env') && is_file($root.'/.env.example')) {
    copy($root.'/.env.example', $root.'/.env');
}

// Forcefully load .env values into process environment so stale terminal variables
// (e.g. DB_CONNECTION=sqlite injected by IDE terminal extensions) never override .env.
if (is_file($root.'/.env') && getenv('APP_ENV') !== 'testing') {
    $loaded = Dotenv::createMutable($root)->safeLoad();
    foreach ($loaded as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

foreach ([
    $root.'/storage/app/public',
    $root.'/storage/app/private',
    $root.'/storage/framework/cache/data',
    $root.'/storage/framework/sessions',
    $root.'/storage/framework/views',
    $root.'/bootstrap/cache',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$publicStorage = $root.'/public/storage';
if (! file_exists($publicStorage)) {
    (new Process([PHP_BINARY, 'artisan', 'storage:link', '--quiet'], $root))->run();
}

// Ensure PostgreSQL Docker container is running and responsive when configured for local port 5433.
if (($_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION')) === 'pgsql' && (string) ($_ENV['DB_PORT'] ?? getenv('DB_PORT')) === '5433') {
    $host = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
    $pgReady = false;
    $socket = @stream_socket_client("tcp://{$host}:5433", $errno, $errstr, 1.5);
    if (is_resource($socket)) {
        stream_set_timeout($socket, 2);
        @fwrite($socket, pack('NN', 8, 80877103));
        $reply = @fread($socket, 1);
        fclose($socket);
        $pgReady = in_array($reply, ['N', 'S'], true);
    }

    if (! $pgReady) {
        fwrite(STDOUT, "[dev] Menghidupkan container PostgreSQL klinikpratama-postgres-1...\n");
        (new Process(['docker', 'start', 'klinikpratama-postgres-1'], $root))->run();
        usleep(1_500_000);
    }
}

if (is_file($root.'/bootstrap/cache/config.php')) {
    (new Process([PHP_BINARY, 'artisan', 'config:clear', '--quiet'], $root))->run();
}

$extraArgs = array_slice($_SERVER['argv'] ?? [], 1);
$devProcess = new Process([PHP_BINARY, 'artisan', 'dev', ...$extraArgs], $root, $_ENV + $_SERVER, null, null);
if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(static function () use ($devProcess): void {
        if ($devProcess->isRunning()) {
            $devProcess->stop(3);
        }
    });
}

exit($devProcess->run(static function (string $type, string $buffer): void {
    fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
}));
