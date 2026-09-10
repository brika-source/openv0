<?php

declare(strict_types=1);

/**
 * Application bootstrap: autoloading, configuration, error handling.
 *
 * Included by public/index.php (web), bin/console.php (CLI) and the test suite.
 * It never starts a session or emits output, so CLI tools can use the same
 * bootstrap as the web front controller.
 */

use App\Config;

// ---------------------------------------------------------------------
// PSR-4 autoloader for the App\ namespace -> app/ directory.
// No Composer, no vendor directory, no external dependencies.
// ---------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

// ---------------------------------------------------------------------
// Configuration and environment
// ---------------------------------------------------------------------
Config::load(dirname(__DIR__) . '/config/config.php');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

$debug   = (bool) Config::get('app.debug', false);
$logDir  = (string) Config::get('paths.logs');

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');

// Turn every PHP notice and warning into an exception so nothing is ever
// silently swallowed — a bad array key becomes a visible, logged failure.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
