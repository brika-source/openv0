<?php

declare(strict_types=1);

namespace App;

/**
 * Session handling with an idle timeout, fixation protection on login and
 * strict cookie flags.
 *
 * A single browser session can hold one login per portal ('requester' /
 * 'approver'), so the two portals can be used side by side in one browser
 * exactly like the two original HTML prototypes were.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = (int) Config::get('session.lifetime', 2700);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (string) (Config::get('app.base_path') ?: '') . '/',
            'domain'   => '',
            'secure'   => (bool) Config::get('session.secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name((string) Config::get('session.name', 'SLMSSESSID'));
        session_start();
        self::$started = true;

        // Idle timeout: any request more than `lifetime` seconds after the
        // previous one drops all logins.
        $last = self::get('_last_activity');
        if (is_int($last) && (time() - $last) > $lifetime) {
            self::flushLogins();
            self::set('_timed_out', true);
        }
        self::set('_last_activity', time());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Log the user out of every portal but keep the session (and its CSRF token). */
    public static function flushLogins(): void
    {
        self::forget('login.requester');
        self::forget('login.approver');
    }

    // -----------------------------------------------------------------
    // Flash messages (rendered as toasts on the next page load)
    // -----------------------------------------------------------------

    public static function flash(string $message, string $type = 'info'): void
    {
        $queue   = self::get('_flash', []);
        $queue   = is_array($queue) ? $queue : [];
        $queue[] = ['message' => $message, 'type' => $type];
        self::set('_flash', $queue);
    }

    /** @return array<int,array{message:string,type:string}> */
    public static function takeFlashes(): array
    {
        /** @var array<int,array{message:string,type:string}> $queue */
        $queue = self::pull('_flash', []);
        return is_array($queue) ? $queue : [];
    }

    /**
     * Remember arbitrary form state so a failed POST can re-render the form
     * with the user's input instead of a blank slate.
     *
     * @param array<string,mixed> $input
     */
    public static function flashInput(array $input): void
    {
        self::set('_old_input', $input);
    }

    /** @return array<string,mixed> */
    public static function oldInput(): array
    {
        /** @var array<string,mixed> $old */
        $old = self::pull('_old_input', []);
        return is_array($old) ? $old : [];
    }
}
