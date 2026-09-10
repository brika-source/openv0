<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Repo\Audit;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Repo\Users;
use App\Service\Reports;

/**
 * Reports & Audit — Module C. Available to the medical team, HR and Admin.
 */
final class ReportController
{
    /** @param array<string,mixed> $user */
    public static function index(array $user, string $tab): string
    {
        $filters = Reports::filtersFromQuery(Request::allQuery());
        $cases   = Reports::cases($filters);

        $rows = [];
        foreach ($cases as $case) {
            $rows[] = [
                'case'      => $case,
                'employee'  => Users::find((string) $case['employee_id']),
                'reviewers' => Cases::reviewersOf($case),
                'days'      => Cases::totalDays($case),
            ];
        }

        $ftwRows = [];
        foreach (Ftw::all() as $record) {
            $ftwRows[] = [
                'record'   => $record,
                'employee' => Users::find((string) $record['employee_id']),
            ];
        }

        return Layout::page($user, $tab, 'reports/index', [
            'filters'     => $filters,
            'rows'        => $rows,
            'ftw_rows'    => $ftwRows,
            'employees'   => Users::employees(false),
            'departments' => Users::departments(),
            'statuses'    => Domain::statuses(),
            'specialties' => Domain::specialties(),
            'total'       => count($rows),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function csv(array $user): never
    {
        $filters = Reports::filtersFromQuery(Request::allQuery());

        Audit::log($user, 'report.export', 'report', null, 'csv | ' . json_encode($filters, JSON_UNESCAPED_UNICODE));

        Response::download(Reports::csv($filters), Reports::csvFilename());
    }
}
