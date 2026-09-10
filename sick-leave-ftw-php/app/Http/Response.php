<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Response emission: HTML, redirects, downloads and file streaming.
 *
 * Every response carries the security headers appropriate to it, including a
 * Content-Security-Policy that blocks inline event handlers and any external
 * origin — the application ships its own CSS and JS files, so nothing needs to
 * be loaded from a CDN.
 */
final class Response
{
    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        self::securityHeaders();
        echo $body;
        exit;
    }

    public static function redirect(string $location, int $status = 303): never
    {
        // Only ever redirect within this application.
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            $location = Url::base();
        }

        http_response_code($status);
        header('Location: ' . $location);
        exit;
    }

    /** A CSV (or other text) download. */
    public static function download(string $body, string $filename, string $contentType = 'text/csv; charset=UTF-8'): never
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . self::headerSafe($filename) . '"');
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }

    /** Stream a stored file from disk as an attachment. */
    public static function file(string $path, string $downloadName, string $contentType): never
    {
        $size = filesize($path);

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . self::headerSafe($downloadName) . '"');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        // Never let a browser sniff an uploaded file into something executable.
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: private, no-store');

        readfile($path);
        exit;
    }

    /** A bare printable page: no app chrome, no CSP restrictions on printing. */
    public static function printable(string $body): never
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        echo $body;
        exit;
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "script-src 'self'; style-src 'self'; img-src 'self' data:; "
            . "font-src 'self'; connect-src 'self'; form-action 'self'; "
            . "base-uri 'self'; frame-ancestors 'none'; object-src 'none'"
        );
        // Records here are medical; never let a shared proxy or the back button
        // resurface a signed-in page.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }

    /** Strip CR/LF and quotes so a filename can never break out of a header. */
    private static function headerSafe(string $value): string
    {
        return str_replace(["\r", "\n", '"'], '', $value);
    }
}
