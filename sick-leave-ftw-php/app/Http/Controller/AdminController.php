<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Url;
use App\I18n;
use App\Repo\Audit;
use App\Repo\Settings;
use App\Repo\Users;
use App\Schema;
use App\Seeder;
use App\Service\Uploads;
use App\Session;
use App\ValidationException;

/**
 * Admin screens: users and roles, retention/SLA settings, audit trail and the
 * demo data reset.
 */
final class AdminController
{
    /** @param array<string,mixed> $user */
    public static function users(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'admin/users', [
            'users'    => Users::all(),
            'roles'    => Domain::roles(),
            'managers' => Users::byRole(Domain::ROLE_MANAGER, false),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function settings(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'admin/settings', [
            'retention_years'  => Settings::retentionYears(),
            'sla_amber_hrs'    => Settings::slaAmberHours(),
            'sla_red_hrs'      => Settings::slaRedHours(),
            'entitlement_days' => Settings::entitlementDays(),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function audit(array $user, string $tab): string
    {
        $perPage = 100;
        $page    = max(1, Request::queryInt('page', 1));
        $total   = Audit::count();

        return Layout::page($user, $tab, 'admin/audit', [
            'entries'  => Audit::recent($perPage, ($page - 1) * $perPage),
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $user */
    public static function changeRole(array $user): never
    {
        $targetId = Request::post('id');
        $role     = Request::post('role');

        if (!in_array($role, Domain::roles(), true)) {
            throw new ValidationException(I18n::t('err_generic'));
        }

        // An admin must not be able to lock themselves out of the admin tools.
        if ($targetId === (string) $user['id']) {
            throw new ValidationException(I18n::t('err_cannot_self_demote'));
        }

        $target = Users::find($targetId);
        if ($target === null) {
            throw new ValidationException(I18n::t('err_not_found'));
        }

        Users::setRole($targetId, $role);
        Audit::log($user, 'user.role', 'user', $targetId, ($target['role'] ?? '') . ' -> ' . $role);

        Session::flash(
            $target['name'] . ' ' . I18n::topt('toast_role_updated') . ' ' . I18n::roleLabel($role) . '.',
            'success'
        );

        Response::redirect(Url::tab((string) $user['portal'], 'users'));
    }

    /** @param array<string,mixed> $user */
    public static function setActive(array $user): never
    {
        $targetId = Request::post('id');
        $active   = Request::post('active') === '1';

        if ($targetId === (string) $user['id']) {
            throw new ValidationException(I18n::t('err_cannot_self_demote'));
        }

        $target = Users::find($targetId);
        if ($target === null) {
            throw new ValidationException(I18n::t('err_not_found'));
        }

        Users::setActive($targetId, $active);
        Audit::log($user, $active ? 'user.reactivate' : 'user.deactivate', 'user', $targetId);

        Session::flash(
            I18n::topt($active ? 'toast_user_reactivated' : 'toast_user_deactivated'),
            'success'
        );

        Response::redirect(Url::tab((string) $user['portal'], 'users'));
    }

    /** @param array<string,mixed> $user */
    public static function createUser(array $user): never
    {
        $name     = Request::post('name');
        $email    = strtolower(Request::post('email'));
        $dept     = Request::post('dept');
        $role     = Request::post('role');
        $manager  = Request::post('manager_id');
        $password = Request::postRaw('password');

        if ($name === '' || $email === '' || $dept === '') {
            throw new ValidationException(I18n::t('err_required'));
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(I18n::t('err_required'));
        }
        if (!in_array($role, Domain::roles(), true)) {
            throw new ValidationException(I18n::t('err_generic'));
        }
        if (strlen($password) < 8) {
            throw new ValidationException(I18n::t('err_password_short'));
        }
        if (Users::emailExists($email)) {
            throw new ValidationException(I18n::t('err_email_taken'));
        }

        // A manager reference must point at a real manager.
        if ($manager !== '') {
            $candidate = Users::find($manager);
            if ($candidate === null || (string) $candidate['role'] !== Domain::ROLE_MANAGER) {
                $manager = '';
            }
        }

        $newId = Users::create([
            'name'       => $name,
            'email'      => $email,
            'dept'       => $dept,
            'role'       => $role,
            'manager_id' => $manager === '' ? null : $manager,
            'password'   => $password,
        ]);

        Audit::log($user, 'user.create', 'user', $newId, $name . ' | ' . $role);
        Session::flash(I18n::topt('toast_user_added') . ' ' . $newId, 'success');

        Response::redirect(Url::tab((string) $user['portal'], 'users'));
    }

    /** @param array<string,mixed> $user */
    public static function saveSettings(array $user): never
    {
        // Each value is clamped to a sane range rather than trusted.
        $values = [
            'retention_years'  => min(100, max(1, Request::postInt('retention_years', 15))),
            'sla_amber_hrs'    => min(720, max(1, Request::postInt('sla_amber_hrs', 24))),
            'sla_red_hrs'      => min(1440, max(1, Request::postInt('sla_red_hrs', 48))),
            'entitlement_days' => min(365, max(1, Request::postInt('entitlement_days', 21))),
        ];

        // Escalation must come at or after the warning.
        if ($values['sla_red_hrs'] < $values['sla_amber_hrs']) {
            $values['sla_red_hrs'] = $values['sla_amber_hrs'];
        }

        foreach ($values as $key => $value) {
            Settings::set($key, $value);
        }

        Audit::log($user, 'settings.save', 'settings', null, json_encode($values, JSON_UNESCAPED_UNICODE));
        Session::flash(I18n::topt('toast_settings_saved'), 'success');

        Response::redirect(Url::tab((string) $user['portal'], 'settings'));
    }

    /**
     * Wipe and reseed. Deliberately requires a typed confirmation phrase, so a
     * stray click cannot destroy a populated database.
     *
     * @param array<string,mixed> $user
     */
    public static function resetDemoData(array $user): never
    {
        if (Request::post('confirm') !== 'RESET') {
            throw new ValidationException(I18n::t('err_generic'));
        }

        Audit::log($user, 'demo.reset', null, null, 'requested by ' . $user['id']);

        Schema::drop();
        Schema::create();
        Seeder::run();
        Settings::flush();

        // Remove stored uploads so the filesystem matches the fresh database.
        foreach (glob(Uploads::directory() . '/*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                @unlink($file);
            }
        }

        // Every account, including this one, was just recreated.
        Session::flushLogins();
        Session::flash(I18n::topt('toast_reset_done'), 'success');

        Response::redirect(Url::portal((string) $user['portal']));
    }
}
