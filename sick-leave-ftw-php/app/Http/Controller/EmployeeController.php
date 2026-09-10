<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Http\Layout;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Service\Access;
use App\Service\Analytics;

/**
 * Employee screens: dashboard, submission form, own cases, own FTW records.
 *
 * Everything here is scoped to the signed-in employee's own id — there is no
 * parameter by which an employee could ask for someone else's data.
 */
final class EmployeeController
{
    /** @param array<string,mixed> $user */
    public static function dashboard(array $user, string $tab): string
    {
        $employeeId = (string) $user['id'];
        $cases      = Cases::forEmployee($employeeId);

        // The banner describes the first case that is not finished.
        $active = null;
        foreach ($cases as $case) {
            if (!in_array((string) $case['status'], [
                Domain::STATUS_CLOSED,
                Domain::STATUS_REJECTED,
                Domain::STATUS_RETURNED_RESTRICTED,
            ], true)) {
                $active = $case;
                break;
            }
        }

        return Layout::page($user, $tab, 'employee/dashboard', [
            'balance'    => Analytics::balance($employeeId),
            'cases'      => $cases,
            'ftw_count'  => count(Ftw::forEmployee($employeeId)),
            'active'     => $active,
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function submitForm(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'employee/submit', [
            'specialties' => Domain::specialties(),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function myCases(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'employee/cases', [
            'cases' => Cases::forEmployee((string) $user['id']),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function myFtw(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'employee/ftw', [
            'records'    => Ftw::forEmployee((string) $user['id']),
            'demands'    => Domain::jobDemands(),
            'candidates' => Access::ftwCandidates($user),
        ]);
    }
}
