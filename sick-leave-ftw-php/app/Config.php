<?php

declare(strict_types=1);

namespace App;

/**
 * Dot-notation access to config/config.php, loaded once per request.
 */
final class Config
{
    /** @var array<string,mixed>|null */
    private static ?array $items = null;

    public static function load(string $file): void
    {
        /** @var array<string,mixed> $data */
        $data = require $file;
        self::$items = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$items === null) {
            self::load(dirname(__DIR__) . '/config/config.php');
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$items === null) {
            self::load(dirname(__DIR__) . '/config/config.php');
        }
        return self::$items;
    }
}
