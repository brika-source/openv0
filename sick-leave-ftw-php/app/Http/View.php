<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;
use RuntimeException;

/**
 * Minimal template renderer: plain PHP templates, rendered with an isolated
 * variable scope, optionally wrapped in a layout.
 *
 * Templates receive exactly the variables they are given — no globals leak in —
 * and every value they print is escaped with e(), so the output is safe by
 * default rather than by review.
 */
final class View
{
    /** @var array<string,mixed> Shared across every render in the request. */
    private static array $shared = [];

    /** @param array<string,mixed> $data */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * Render a template to a string.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = []): string
    {
        $path = self::path($template);

        // Isolate the template's scope: it sees $data plus the shared values,
        // and nothing from the caller.
        $render = static function (string $__path, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__path;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        };

        return $render($path, array_merge(self::$shared, $data));
    }

    /**
     * Render a template inside the application layout.
     *
     * @param array<string,mixed> $data
     */
    public static function page(string $template, array $data = []): string
    {
        $content = self::render($template, $data);

        return self::render('layout', array_merge($data, ['content' => $content]));
    }

    private static function path(string $template): string
    {
        $views = (string) Config::get('paths.views');
        // Template names are internal constants, never user input; the check is
        // belt-and-braces against a future refactor passing something dynamic.
        if (!preg_match('#^[A-Za-z0-9_/\-]+$#', $template)) {
            throw new RuntimeException('Invalid template name: ' . $template);
        }

        $path = $views . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View not found: ' . $path);
        }

        return $path;
    }

    public static function exists(string $template): bool
    {
        try {
            self::path($template);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
