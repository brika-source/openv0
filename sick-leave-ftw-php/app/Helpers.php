<?php

declare(strict_types=1);

namespace App;

/**
 * Formatting and escaping helpers.
 *
 * Timestamps are stored as 'Y-m-d H:i:s' strings in the application timezone
 * (config app.timezone, UTC by default) and dates as 'Y-m-d'. Because the whole
 * application runs in one timezone there is no conversion step anywhere, which
 * keeps day counts and SLA arithmetic unambiguous.
 */
final class Helpers
{
    public const EM_DASH = '—';

    /** Escape for HTML text and attribute contexts. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escape a value for use inside a single-quoted JavaScript string. */
    public static function js(mixed $value): string
    {
        $json = json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
        return $json === false ? '""' : $json;
    }

    // -----------------------------------------------------------------
    // Clock
    // -----------------------------------------------------------------

    /** Current timestamp in storage format. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Today's date in storage format. */
    public static function today(): string
    {
        return date('Y-m-d');
    }

    /** A date offset from today by whole days, in storage format. */
    public static function dayOffset(int $days): string
    {
        return date('Y-m-d', strtotime(sprintf('%+d days', $days)));
    }

    /** A timestamp offset from now by whole hours, in storage format. */
    public static function hourOffset(int $hours): string
    {
        return date('Y-m-d H:i:s', strtotime(sprintf('%+d hours', $hours)));
    }

    // -----------------------------------------------------------------
    // Display formatting — mirrors the prototype's en-GB output
    // -----------------------------------------------------------------

    /** "09 Sep 2026", or an em dash when empty. */
    public static function fmtDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return self::EM_DASH;
        }
        $ts = strtotime($date);
        return $ts === false ? self::EM_DASH : date('d M Y', $ts);
    }

    /** "09 Sep, 14:30", or an em dash when empty. */
    public static function fmtDateTime(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return self::EM_DASH;
        }
        $ts = strtotime($timestamp);
        return $ts === false ? self::EM_DASH : date('d M, H:i', $ts);
    }

    /** "09 Sep 2026 14:30" — used in printable records and the audit trail. */
    public static function fmtFull(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return self::EM_DASH;
        }
        $ts = strtotime($timestamp);
        return $ts === false ? self::EM_DASH : date('d M Y H:i', $ts);
    }

    // -----------------------------------------------------------------
    // Arithmetic
    // -----------------------------------------------------------------

    /**
     * Inclusive day count between two dates, never less than 1 — a single-day
     * leave counts as one day. Matches the prototype's daysBetween().
     */
    public static function daysBetween(string $from, string $to): int
    {
        $a = strtotime($from);
        $b = strtotime($to);
        if ($a === false || $b === false) {
            return 1;
        }
        $days = (int) round(($b - $a) / 86400) + 1;
        return max(1, $days);
    }

    /** Whole and fractional hours elapsed since a timestamp. */
    public static function hoursSince(string $timestamp): float
    {
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return 0.0;
        }
        return (time() - $ts) / 3600;
    }

    /** Days elapsed since a date, floored at zero. */
    public static function daysSince(string $date): int
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return 0;
        }
        return max(0, (int) floor((time() - $ts) / 86400));
    }

    public static function isPast(?string $date): bool
    {
        if ($date === null || $date === '') {
            return false;
        }
        $ts = strtotime($date);
        return $ts !== false && $ts < time();
    }

    // -----------------------------------------------------------------
    // Identifiers and text
    // -----------------------------------------------------------------

    /** "SL-0001" / "FTW-0002" — the prototype's uid(). */
    public static function refId(string $prefix, int $sequence): string
    {
        return $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    /** A badge span, matching the prototype's badge() helper. Text is escaped. */
    public static function badge(string $text, string $class): string
    {
        return '<span class="badge ' . self::e($class) . '">' . self::e($text) . '</span>';
    }

    /** Strip characters that would let a filename escape the upload directory. */
    public static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}\.\-_ ()]+/u', '_', $name) ?? 'file';
        $name = trim($name, ". \t\n\r\0\x0B");
        if ($name === '') {
            $name = 'file';
        }
        return mb_substr($name, 0, 180);
    }

    public static function isValidDate(?string $date): bool
    {
        if ($date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y);
    }

    /** Truncate for table cells and audit details without breaking UTF-8. */
    public static function truncate(string $text, int $limit = 120): string
    {
        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
    }
}
