<?php

declare(strict_types=1);

use App\Console\Kernel;
use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;

require_once __DIR__ . '/../vendor/autoload.php';

$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (($value[0] ?? null) === '"' && ($value[-1] ?? null) === '"') {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

if (!getenv('APP_ENV')) {
    putenv('APP_ENV=local');
    $_ENV['APP_ENV'] = 'local';
}

if (!getenv('APP_DEBUG')) {
    putenv('APP_DEBUG=true');
    $_ENV['APP_DEBUG'] = 'true';
}

if (!getenv('APP_KEY')) {
    putenv('APP_KEY=');
    $_ENV['APP_KEY'] = '';
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', 'On');

$app = new Application(basePath: __DIR__ . '/..');

$app->useConfigPath(__DIR__ . '/../config');
$app->useStoragePath(__DIR__ . '/../storage');

$app->singleton(
    \Illuminate\Contracts\Http\Kernel::class,
    \App\Http\Kernel::class,
);

$app->singleton(
    \Illuminate\Contracts\Console\Kernel::class,
    Kernel::class,
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class,
);

return $app;
