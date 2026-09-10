<?php

declare(strict_types=1);

/**
 * Short global helpers, available inside every view template.
 *
 * Keeping these as functions rather than static calls is what lets the
 * templates stay readable: <?= e($case['diagnosis']) ?> instead of a
 * fully-qualified class call on every line.
 */

use App\Csrf;
use App\Helpers;
use App\Http\Url;
use App\I18n;

if (!function_exists('e')) {
    /** Escape for HTML output. Use for every value that reaches a template. */
    function e(mixed $value): string
    {
        return Helpers::e($value);
    }
}

if (!function_exists('t')) {
    /** Bilingual label, English and Arabic stacked on two lines. */
    function t(string $key): string
    {
        return I18n::t($key);
    }
}

if (!function_exists('te')) {
    /**
     * Bilingual label, escaped, with the line break between the English and the
     * Arabic rendered as a real <br>.
     *
     * The prototypes achieved the same stacking with white-space:pre-line on
     * the body, but that also turns every newline in the markup itself into a
     * visible blank line — which a server-rendered template, unlike a
     * JavaScript template string, has plenty of. Escaping first and inserting
     * the <br> afterwards keeps the output both safe and layout-stable.
     */
    function te(string $key): string
    {
        return nl2br(Helpers::e(I18n::t($key)), false);
    }
}

if (!function_exists('enl')) {
    /** Escape arbitrary text and turn its newlines into <br> elements. */
    function enl(mixed $value): string
    {
        return nl2br(Helpers::e($value), false);
    }
}

if (!function_exists('topt')) {
    /** Bilingual label on a single line: "English / Arabic". */
    function topt(string $key): string
    {
        return I18n::topt($key);
    }
}

if (!function_exists('topte')) {
    /** Single-line bilingual label, escaped. */
    function topte(string $key): string
    {
        return Helpers::e(I18n::topt($key));
    }
}

if (!function_exists('url')) {
    /**
     * Build an application URL.
     *
     * @param array<string,mixed> $params
     */
    function url(array $params = []): string
    {
        return Url::to($params);
    }
}

if (!function_exists('csrf')) {
    /** Hidden CSRF input for a form. */
    function csrf(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('badge')) {
    /** A status badge span. */
    function badge(string $text, string $class): string
    {
        return Helpers::badge($text, $class);
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $date): string
    {
        return Helpers::fmtDate($date);
    }
}

if (!function_exists('fmt_dt')) {
    function fmt_dt(?string $timestamp): string
    {
        return Helpers::fmtDateTime($timestamp);
    }
}
