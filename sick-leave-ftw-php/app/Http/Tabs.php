<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain;

/**
 * The tab bar for each role.
 *
 * The five role tab sets are exactly those of the prototypes; the Admin role
 * additionally gets the Audit Trail tab, which this edition adds.
 */
final class Tabs
{
    /** @return array<string,list<array{0:string,1:string}>> role => [[tab, labelKey], ...] */
    public static function map(): array
    {
        return [
            Domain::ROLE_EMPLOYEE => [
                ['dash', 'tab_dash'],
                ['submit', 'tab_submit'],
                ['mycases', 'tab_mycases'],
                ['myftw', 'tab_myftw'],
            ],
            Domain::ROLE_MANAGER => [
                ['teamdash', 'tab_teamdash'],
                ['ftwinit', 'tab_ftwinit'],
                ['feed', 'tab_feed'],
            ],
            Domain::ROLE_MEDICAL => [
                ['mdash', 'tab_mdash'],
                ['review', 'tab_review'],
                ['rtw', 'tab_rtw'],
                ['ftwqueue', 'tab_ftwqueue'],
                ['ftwinit', 'tab_ftwinit'],
                ['reports', 'tab_reports'],
                ['flags', 'tab_flags'],
                ['expiry', 'tab_expiry'],
            ],
            Domain::ROLE_HR => [
                ['ftwinit', 'tab_ftwinit'],
                ['reports', 'tab_reports'],
            ],
            Domain::ROLE_ADMIN => [
                ['users', 'tab_users'],
                ['settings', 'tab_settings'],
                ['reports', 'tab_reports'],
                ['audit', 'tab_audit'],
            ],
        ];
    }

    /** @return list<array{0:string,1:string}> */
    public static function forRole(string $role): array
    {
        return self::map()[$role] ?? [];
    }

    /** The tab a role lands on after signing in. */
    public static function defaultFor(string $role): string
    {
        $tabs = self::forRole($role);

        return $tabs === [] ? 'dash' : $tabs[0][0];
    }

    public static function allowed(string $role, string $tab): bool
    {
        foreach (self::forRole($role) as [$name]) {
            if ($name === $tab) {
                return true;
            }
        }

        return false;
    }

    /** tab => [Controller class, method] */
    public static function handler(string $tab): ?array
    {
        $handlers = [
            'dash'     => [Controller\EmployeeController::class, 'dashboard'],
            'submit'   => [Controller\EmployeeController::class, 'submitForm'],
            'mycases'  => [Controller\EmployeeController::class, 'myCases'],
            'myftw'    => [Controller\EmployeeController::class, 'myFtw'],

            'teamdash' => [Controller\ManagerController::class, 'teamDashboard'],
            'feed'     => [Controller\ManagerController::class, 'feed'],

            'ftwinit'  => [Controller\FtwController::class, 'initForm'],

            'mdash'    => [Controller\MedicalController::class, 'dashboard'],
            'review'   => [Controller\MedicalController::class, 'reviewQueue'],
            'rtw'      => [Controller\MedicalController::class, 'rtwQueue'],
            'ftwqueue' => [Controller\MedicalController::class, 'ftwQueue'],
            'flags'    => [Controller\MedicalController::class, 'flags'],
            'expiry'   => [Controller\MedicalController::class, 'expiry'],

            'reports'  => [Controller\ReportController::class, 'index'],

            'users'    => [Controller\AdminController::class, 'users'],
            'settings' => [Controller\AdminController::class, 'settings'],
            'audit'    => [Controller\AdminController::class, 'audit'],
        ];

        return $handlers[$tab] ?? null;
    }
}
