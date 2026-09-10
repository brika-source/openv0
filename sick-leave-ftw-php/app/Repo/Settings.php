<?php

declare(strict_types=1);

namespace App\Repo;

use App\Config;
use App\Database;

/**
 * Runtime business settings (retention, SLA thresholds, entitlement), editable
 * by an Admin in the UI. Values are cached for the duration of a request.
 */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows  = Database::all('SELECT setting_key, setting_value FROM settings');
        $items = [];
        foreach ($rows as $row) {
            $items[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        self::$cache = $items;
        return $items;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::all()[$key] ?? null;
        return $value === null ? $default : (int) $value;
    }

    public static function set(string $key, string|int $value): void
    {
        $exists = Database::scalar(
            'SELECT setting_key FROM settings WHERE setting_key = :k',
            ['k' => $key]
        );

        if ($exists === null) {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => (string) $value]);
        } else {
            Database::update('settings', ['setting_value' => (string) $value], 'setting_key', $key);
        }

        self::$cache = null;
    }

    // ---- Named accessors, each falling back to the config default ----

    public static function retentionYears(): int
    {
        return self::int('retention_years', (int) Config::get('business.retention_years', 15));
    }

    public static function slaAmberHours(): int
    {
        return self::int('sla_amber_hrs', (int) Config::get('business.sla_amber_hrs', 24));
    }

    public static function slaRedHours(): int
    {
        return self::int('sla_red_hrs', (int) Config::get('business.sla_red_hrs', 48));
    }

    public static function entitlementDays(): int
    {
        return self::int('entitlement_days', (int) Config::get('business.entitlement_days', 21));
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
