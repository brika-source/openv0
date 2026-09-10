<?php

declare(strict_types=1);

namespace App;

/**
 * Bilingual label lookup.
 *
 * The original prototypes rendered every label as English and Arabic together
 * rather than switching languages, and this port keeps that behaviour exactly:
 *
 *   t($key)    -> "English\nArabic" — stacked on two lines (the body style sets
 *                 white-space:pre-line, so the newline becomes a line break)
 *   topt($key) -> "English / Arabic" — inline, for use inside a sentence
 *
 * Unknown keys return the key itself, which makes a missing label obvious in
 * the UI instead of silently rendering an empty string.
 */
final class I18n
{
    /** @var array<string,array<string,string>>|null */
    private static ?array $dict = null;

    private static function dict(): array
    {
        if (self::$dict !== null) {
            return self::$dict;
        }

        /** @var array<string,array<string,string>> $base */
        $base = require __DIR__ . '/Lang/labels.php';
        /** @var array<string,array<string,string>> $extra */
        $extra = require __DIR__ . '/Lang/extra.php';

        self::$dict = [
            'en' => array_merge($base['en'] ?? [], $extra['en'] ?? []),
            'ar' => array_merge($base['ar'] ?? [], $extra['ar'] ?? []),
        ];

        return self::$dict;
    }

    /** English and Arabic stacked on two lines. */
    public static function t(string $key): string
    {
        $dict = self::dict();
        $en   = $dict['en'][$key] ?? $key;
        $ar   = $dict['ar'][$key] ?? '';

        return $ar !== '' ? $en . "\n" . $ar : $en;
    }

    /** English and Arabic on one line, separated by a slash. */
    public static function topt(string $key): string
    {
        $dict = self::dict();
        $en   = $dict['en'][$key] ?? $key;
        $ar   = $dict['ar'][$key] ?? '';

        return $ar !== '' ? $en . ' / ' . $ar : $en;
    }

    /** English only — for CSV columns, filenames, log lines and e-mail subjects. */
    public static function en(string $key): string
    {
        return self::dict()['en'][$key] ?? $key;
    }

    /** Arabic only. Falls back to English when a key has no Arabic entry. */
    public static function ar(string $key): string
    {
        $dict = self::dict();

        return ($dict['ar'][$key] ?? '') !== '' ? $dict['ar'][$key] : ($dict['en'][$key] ?? $key);
    }

    /**
     * Build a sentence out of label keys and literal values, and return the
     * English and Arabic renderings stacked on two lines.
     *
     * Concatenating topt() fragments — as the prototypes did — interleaves the
     * two languages inside one line ("Escalated — / تم التصعيد — 52h unactioned
     * / ساعة بدون إجراء"), which is hard to read in either language and mixes
     * text directions mid-sentence. Composing each language separately and then
     * stacking them keeps both readable:
     *
     *   Escalated — 52h unactioned (lead notified)
     *   تم التصعيد — 52 ساعة بدون إجراء (تم إبلاغ رئيس الفريق)
     *
     * A part is one of:
     *   ['key' => 'some_label']        a translated fragment
     *   ['en' => '...', 'ar' => '...'] a literal that differs per language,
     *                                  for spacing around numbers (English
     *                                  writes "52h", Arabic "52 ساعة")
     *   'text' | 42                    a literal used verbatim in both
     *
     * @param list<array{key:string}|array{en:string,ar:string}|string|int|float> $parts
     */
    public static function sentence(array $parts): string
    {
        $english = '';
        $arabic  = '';

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['key'])) {
                $english .= self::en((string) $part['key']);
                $arabic  .= self::ar((string) $part['key']);
                continue;
            }

            if (is_array($part) && (isset($part['en']) || isset($part['ar']))) {
                $english .= (string) ($part['en'] ?? '');
                $arabic  .= (string) ($part['ar'] ?? $part['en'] ?? '');
                continue;
            }

            $literal  = (string) $part;
            $english .= $literal;
            $arabic  .= $literal;
        }

        $english = trim($english);
        $arabic  = trim($arabic);

        return $arabic !== '' && $arabic !== $english ? $english . "\n" . $arabic : $english;
    }

    public static function statusLabel(string $status): string
    {
        return self::topt('status_' . $status);
    }

    public static function ftwStatusLabel(string $status): string
    {
        return self::topt('ftw_' . $status);
    }

    public static function specialtyLabel(string $specialty): string
    {
        return self::topt('spec_' . $specialty);
    }

    public static function jobDemandLabel(string $demand): string
    {
        return self::topt('jd_' . $demand);
    }

    public static function roleLabel(string $role): string
    {
        return self::topt('role_' . $role);
    }

    public static function has(string $key): bool
    {
        return isset(self::dict()['en'][$key]);
    }
}
