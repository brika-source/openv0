<?php

declare(strict_types=1);

/**
 * Front controller — the only PHP file that needs to be web-reachable.
 *
 * Everything else (application code, templates, the database, uploads) lives
 * above the document root, so pointing a virtual host at this `public/`
 * directory is all the hardening a normal deployment needs.
 */

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/functions.php';

use App\Config;
use App\Http\Response;
use App\Http\View;
use App\Http\Router;

try {
    Router::dispatch();
} catch (Throwable $e) {
    // Log the detail, show the user a plain page. Nothing internal leaks unless
    // APP_DEBUG is explicitly on.
    error_log(sprintf(
        "[%s] %s: %s in %s:%d\n%s",
        date('c'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    if (!headers_sent()) {
        http_response_code(500);
    }

    Response::html(View::render('errors/fatal', [
        'debug'     => (bool) Config::get('app.debug', false),
        'exception' => $e,
    ]), 500);
}
