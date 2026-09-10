<?php

declare(strict_types=1);

namespace App;

/**
 * Per-session CSRF token. Every state-changing request in the application is
 * a POST carrying this token; Router rejects any POST without a valid one.
 */
final class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }

    public static function check(?string $candidate): bool
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '' || !is_string($candidate) || $candidate === '') {
            return false;
        }
        return hash_equals($token, $candidate);
    }
}
