<?php
/**
 * Application configuration.
 *
 * Every value can be overridden with an environment variable of the same
 * name (see the $env helper below), so the same source tree runs unchanged
 * in development and production.
 */

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        default            => $value,
    };
};

$root = dirname(__DIR__);

return [
    // ---------------------------------------------------------------
    // Application
    // ---------------------------------------------------------------
    'app' => [
        'name'     => 'Corporate Sick Leave & Fit-to-Work Management System',
        'version'  => '1.0.0',
        // Show demo passwords + demo account picker on the login screen.
        // Set APP_DEMO_MODE=false for a real deployment.
        'demo_mode' => (bool) $env('APP_DEMO_MODE', true),
        // Turn on to print PHP errors to the browser. Never enable in production.
        'debug'    => (bool) $env('APP_DEBUG', false),
        'timezone' => (string) $env('APP_TIMEZONE', 'UTC'),
        // Base URL path the app is served from, e.g. '/sickleave'. '' = domain root.
        'base_path' => rtrim((string) $env('APP_BASE_PATH', ''), '/'),
    ],

    // ---------------------------------------------------------------
    // Database — SQLite by default (zero setup). Switch driver to
    // 'mysql' or 'pgsql' and fill in the credentials for a server DB.
    // ---------------------------------------------------------------
    'db' => [
        'driver'   => (string) $env('DB_DRIVER', 'sqlite'),
        'sqlite'   => [
            'path' => (string) $env('DB_SQLITE_PATH', $root . '/storage/db/slms.sqlite'),
        ],
        'mysql'    => [
            'host'     => (string) $env('DB_HOST', '127.0.0.1'),
            'port'     => (int) $env('DB_PORT', 3306),
            'database' => (string) $env('DB_DATABASE', 'slms'),
            'username' => (string) $env('DB_USERNAME', 'root'),
            'password' => (string) $env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ],
        'pgsql'    => [
            'host'     => (string) $env('DB_HOST', '127.0.0.1'),
            'port'     => (int) $env('DB_PORT', 5432),
            'database' => (string) $env('DB_DATABASE', 'slms'),
            'username' => (string) $env('DB_USERNAME', 'postgres'),
            'password' => (string) $env('DB_PASSWORD', ''),
        ],
    ],

    // ---------------------------------------------------------------
    // Sessions
    // ---------------------------------------------------------------
    'session' => [
        'name'     => (string) $env('SESSION_NAME', 'SLMSSESSID'),
        // Idle timeout in seconds (default 45 minutes).
        'lifetime' => (int) $env('SESSION_LIFETIME', 2700),
        // Send the session cookie over HTTPS only. Enable in production.
        'secure'   => (bool) $env('SESSION_SECURE', false),
    ],

    // ---------------------------------------------------------------
    // File uploads (medical reports, X-rays, job descriptions, ...)
    // ---------------------------------------------------------------
    'uploads' => [
        'path'          => (string) $env('UPLOAD_PATH', $root . '/storage/uploads'),
        'max_bytes'     => (int) $env('UPLOAD_MAX_BYTES', 10 * 1024 * 1024),
        'max_files'     => (int) $env('UPLOAD_MAX_FILES', 10),
        'allowed_ext'   => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
        ],
    ],

    // ---------------------------------------------------------------
    // Business defaults. These seed the `settings` table on install;
    // afterwards an Admin edits them in the UI (Retention / SLA tab).
    // ---------------------------------------------------------------
    'business' => [
        'retention_years'    => 15,
        'sla_amber_hrs'      => 24,
        'sla_red_hrs'        => 48,
        'entitlement_days'   => 21,
        // Restrictions whose review date falls within this window appear
        // in the Restriction Expiry tab.
        'expiry_window_days' => 7,
        // Pattern-flag thresholds (informational only, never blocking).
        'flag_window_days'   => 90,
        'flag_short_count'   => 3,
        'flag_monfri_count'  => 2,
    ],

    'paths' => [
        'root'    => $root,
        'views'   => $root . '/resources/views',
        'storage' => $root . '/storage',
        'logs'    => $root . '/storage/logs',
    ],
];
