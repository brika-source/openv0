<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Typed access to the request. Every accessor returns a predictable type, so a
 * missing or malformed parameter can never surface as a PHP warning or an
 * unexpected array in the middle of a query.
 */
final class Request
{
    public static function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return is_string($method) ? strtoupper($method) : 'GET';
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** A scalar GET parameter, trimmed. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** A scalar POST parameter, trimmed. */
    public static function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** A POST parameter kept verbatim, for free text where trailing layout matters. */
    public static function postRaw(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function postInt(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function queryInt(string $key, int $default = 0): int
    {
        $value = $_GET[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function postBool(string $key): bool
    {
        $value = $_POST[$key] ?? null;

        return in_array($value, ['1', 'yes', 'true', 'on', 1, true], true);
    }

    /** @return list<string> */
    public static function postArray(string $key): array
    {
        $value = $_POST[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
            $value
        ));
    }

    /** @return list<string> */
    public static function queryArray(string $key): array
    {
        $value = $_GET[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
            $value
        ));
    }

    /** @return array<string,mixed> */
    public static function allQuery(): array
    {
        return is_array($_GET) ? $_GET : [];
    }

    /**
     * The URL to return to after a POST — a same-origin path from the form's
     * hidden `_return` field, or a fallback.
     */
    public static function returnTo(string $fallback): string
    {
        $return = self::post('_return');

        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
            return $return;
        }

        return $fallback;
    }
}
