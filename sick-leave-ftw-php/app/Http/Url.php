<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;

/**
 * URL building.
 *
 * Routes live in the query string ('?p=approver&r=tab&t=review') rather than in
 * the path, so the application runs unchanged on the PHP built-in server, on
 * Apache with or without mod_rewrite, and on nginx, with no rewrite rules to
 * get wrong.
 */
final class Url
{
    /** Script path the app is served from, e.g. '/index.php' or '/slms/index.php'. */
    public static function base(): string
    {
        $basePath = (string) Config::get('app.base_path', '');

        return ($basePath === '' ? '' : $basePath) . '/index.php';
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function to(array $params = []): string
    {
        // Drop nulls and empty strings so URLs stay short and predictable.
        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $clean[$key] = $value;
        }

        if ($clean === []) {
            return self::base();
        }

        return self::base() . '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }

    /** Current request URL, safe to use as a redirect target. */
    public static function current(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? self::base();

        return is_string($uri) ? $uri : self::base();
    }

    /** A portal's tab URL. */
    public static function tab(string $portal, string $tab, array $extra = []): string
    {
        return self::to(['p' => $portal, 'r' => 'tab', 't' => $tab] + $extra);
    }

    public static function portal(string $portal): string
    {
        return self::to(['p' => $portal]);
    }
}
