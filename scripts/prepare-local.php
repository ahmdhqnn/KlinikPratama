<?php

use Symfony\Component\Process\Process;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

if (! is_file($root.'/.env')) {
    if (! copy($root.'/.env.example', $root.'/.env')) {
        exit(1);
    }

    $key = new Process([PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'], $root);
    $key->mustRun(static function (string $type, string $output): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
    });
}

$database = $root.'/database/database.sqlite';

if (! is_file($database) && ! touch($database)) {
    exit(1);
}
