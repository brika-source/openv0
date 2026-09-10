<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Tabs;
use App\I18n;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Repo\Users;
use App\Service\Analytics;
use App\Service\PatientStory;

/**
 * Occupational Health screens: dashboard, the three work queues, pattern flags,
 * restriction expiry and the patient lookup.
 */
final class MedicalController
{
    /** @param array<string,mixed> $user */
    public static function dashboard(array $user, string $tab): string
    {
        $search = Request::query('q');

        return Layout::page($user, $tab, 'medical/dashboard', [
            'kpis'     => Analytics::kpis(),
            'activity' => Analytics::activityFeed(12),
            'search'   => $search,
            'matches'  => $search === '' ? [] : Users::searchEmployees($search),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function reviewQueue(array $user, string $tab): string
    {
        $queue = Cases::reviewQueue();
        $rows  = [];

        foreach ($queue as $case) {
            $rows[] = [
                'case'     => $case,
                'employee' => Users::find((string) $case['employee_id']),
                'sla'      => Analytics::sla($case),
            ];
        }

        return Layout::page($user, $tab, 'medical/review-queue', ['rows' => $rows]);
    }

    /** @param array<string,mixed> $user */
    public static function rtwQueue(array $user, string $tab): string
    {
        $rows = [];

        foreach (Cases::rtwQueue() as $case) {
            $rows[] = [
                'case'     => $case,
                'employee' => Users::find((string) $case['employee_id']),
            ];
        }

        return Layout::page($user, $tab, 'medical/rtw-queue', ['rows' => $rows]);
    }

    /** @param array<string,mixed> $user */
    public static function ftwQueue(array $user, string $tab): string
    {
        $rows = [];

        foreach (Ftw::queue() as $record) {
            $rows[] = [
                'record'   => $record,
                'employee' => Users::find((string) $record['employee_id']),
            ];
        }

        return Layout::page($user, $tab, 'medical/ftw-queue', ['rows' => $rows]);
    }

    /** @param array<string,mixed> $user */
    public static function flags(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'medical/flags', [
            'flagged' => Analytics::flaggedEmployees(),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function expiry(array $user, string $tab): string
    {
        $rows = [];

        foreach (Analytics::expiringRestrictions() as $item) {
            $rows[] = $item + ['employee' => Users::find($item['employee_id'])];
        }

        return Layout::page($user, $tab, 'medical/expiry', ['rows' => $rows]);
    }

    /**
     * The full patient profile for one employee, reached from the dashboard
     * lookup.
     *
     * @param array<string,mixed> $user
     */
    public static function patient(array $user): string
    {
        $employeeId = Request::query('id');
        $employee   = Users::find($employeeId);

        if ($employee === null) {
            Response::html(Router::errorPage($user, I18n::t('err_not_found')), 404);
        }

        return Layout::page($user, Tabs::defaultFor((string) $user['role']), 'medical/patient', [
            'story'    => PatientStory::for($employeeId),
            'employee' => $employee,
        ]);
    }
}
