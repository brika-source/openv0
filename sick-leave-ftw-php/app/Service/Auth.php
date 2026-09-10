<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain;
use App\I18n;
use App\Repo\Audit;
use App\Repo\Users;
use App\Session;

/**
 * Authentication and the per-portal session.
 *
 * One browser session can hold one login per portal, so the Requester and
 * Approver portals can be used side by side in two tabs — the way the two
 * prototype HTML files were.
 */
final class Auth
{
    private const KEY_PREFIX = 'login.';

    /**
     * Attempt a sign-in. Returns null on success, or an error message key's
     * translated text on failure.
     *
     * Both a user id ('U8') and an e-mail address are accepted as the
     * identifier, so the demo account picker and a real login form both work.
     */
    public static function attempt(string $portal, string $identifier, string $password): ?string
    {
        if (!Domain::isPortal($portal)) {
            return I18n::t('err_not_found');
        }

        $identifier = trim($identifier);
        $user       = Users::findForAuth($identifier);

        // Always run a hash comparison so a missing account and a wrong
        // password take a similar amount of time.
        $hash = is_array($user) ? (string) ($user['password_hash'] ?? '') : '';
        $ok   = password_verify($password, $hash !== '' ? $hash : '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');

        if (!is_array($user) || !$ok) {
            Audit::log(null, 'login.failed', 'user', $identifier !== '' ? $identifier : null, 'portal=' . $portal);
            return I18n::t('err_bad_credentials');
        }

        if ((int) $user['is_active'] !== 1) {
            Audit::log(null, 'login.disabled', 'user', (string) $user['id'], 'portal=' . $portal);
            return I18n::t('err_account_disabled');
        }

        $role = (string) $user['role'];
        if (!in_array($role, Domain::rolesForPortal($portal), true)) {
            Audit::log(null, 'login.wrong_portal', 'user', (string) $user['id'], 'portal=' . $portal . ' role=' . $role);
            return I18n::t('err_role_not_allowed');
        }

        // New session id on privilege change, to defeat session fixation.
        Session::regenerate();
        Session::set(self::KEY_PREFIX . $portal, [
            'user_id'  => (string) $user['id'],
            'role'     => $role,
            'since'    => time(),
        ]);
        Session::forget('_timed_out');

        Audit::log(
            ['id' => $user['id'], 'name' => $user['name'], 'role' => $role, 'portal' => $portal],
            'login',
            'user',
            (string) $user['id'],
            'portal=' . $portal
        );

        return null;
    }

    public static function logout(string $portal): void
    {
        $actor = self::user($portal);
        if ($actor !== null) {
            Audit::log($actor, 'logout', 'user', (string) $actor['id'], 'portal=' . $portal);
        }
        Session::forget(self::KEY_PREFIX . $portal);
    }

    public static function isLoggedIn(string $portal): bool
    {
        return self::user($portal) !== null;
    }

    /**
     * The signed-in user for a portal, with 'role' and 'portal' merged in, or
     * null. A login whose account was since deactivated, deleted, or moved to a
     * role that cannot use this portal is treated as signed out.
     *
     * @return array<string,mixed>|null
     */
    public static function user(string $portal): ?array
    {
        if (!Domain::isPortal($portal)) {
            return null;
        }

        $login = Session::get(self::KEY_PREFIX . $portal);
        if (!is_array($login) || !isset($login['user_id'], $login['role'])) {
            return null;
        }

        $user = Users::find((string) $login['user_id']);
        if ($user === null || (int) $user['is_active'] !== 1) {
            Session::forget(self::KEY_PREFIX . $portal);
            return null;
        }

        // The stored role must still be the account's role and still be valid
        // for this portal — an admin demoting someone takes effect immediately.
        $role = (string) $user['role'];
        if ($role !== (string) $login['role'] || !in_array($role, Domain::rolesForPortal($portal), true)) {
            Session::forget(self::KEY_PREFIX . $portal);
            return null;
        }

        $user['role']   = $role;
        $user['portal'] = $portal;

        return $user;
    }

    /** Which portals this browser session is currently signed in to. @return list<string> */
    public static function activePortals(): array
    {
        $active = [];
        foreach ([Domain::PORTAL_REQUESTER, Domain::PORTAL_APPROVER] as $portal) {
            if (self::isLoggedIn($portal)) {
                $active[] = $portal;
            }
        }
        return $active;
    }

    /**
     * Change the signed-in user's own password.
     *
     * @return string|null translated error text, or null on success
     */
    public static function changePassword(array $user, string $current, string $new, string $confirm): ?string
    {
        $hash = Users::passwordHash((string) $user['id']);

        if ($hash === null || !password_verify($current, $hash)) {
            return I18n::t('err_password_wrong');
        }
        if (strlen($new) < 8) {
            return I18n::t('err_password_short');
        }
        if ($new !== $confirm) {
            return I18n::t('err_password_mismatch');
        }

        Users::setPassword((string) $user['id'], $new);
        Audit::log($user, 'password.change', 'user', (string) $user['id']);

        return null;
    }

    /** "Mona Fathy (Employee)" — the actor string written into history rows. */
    public static function actorLabel(array $user): string
    {
        return Users::actorLabel((string) $user['id'], (string) $user['role']);
    }
}
