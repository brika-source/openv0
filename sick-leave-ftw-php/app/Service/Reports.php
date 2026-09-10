<?php

declare(strict_types=1);

namespace App\Service;

use App\Database;
use App\Domain;
use App\Repo\Cases;
use App\Repo\Users;

/**
 * Reports & Audit — Module C.
 *
 * Filtering happens in SQL (so a large dataset does not have to be loaded into
 * memory), with the same semantics as the prototype's filteredCases():
 * every filter list is OR within itself and AND across lists, and the date
 * range keeps cases whose From-date is on or after "from" and whose To-date is
 * on or before "to".
 */
final class Reports
{
    /**
     * @param  array<string,mixed> $filters employees[], depts[], statuses[], specialties[], from, to
     * @return array<int,array<string,mixed>>
     */
    public static function cases(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);

        $sql = 'SELECT c.* FROM sick_cases c INNER JOIN users u ON u.id = c.employee_id';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY c.created_at DESC, c.case_id DESC';

        return Cases::hydrateMany(Database::all($sql, $params));
    }

    /**
     * @param  array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildWhere(array $filters): array
    {
        $clauses = [];
        $params  = [];

        $lists = [
            'employees'   => ['column' => 'c.employee_id', 'prefix' => 'e'],
            'depts'       => ['column' => 'u.dept',        'prefix' => 'd'],
            'statuses'    => ['column' => 'c.status',      'prefix' => 's'],
            'specialties' => ['column' => 'c.specialty',   'prefix' => 'p'],
        ];

        foreach ($lists as $key => $meta) {
            $values = self::cleanList($filters[$key] ?? []);
            if ($values === []) {
                continue;
            }

            $placeholders = [];
            foreach ($values as $index => $value) {
                $name                 = $meta['prefix'] . $index;
                $placeholders[]       = ':' . $name;
                $params[$name]        = $value;
            }
            $clauses[] = $meta['column'] . ' IN (' . implode(', ', $placeholders) . ')';
        }

        $from = (string) ($filters['from'] ?? '');
        if ($from !== '' && \App\Helpers::isValidDate($from)) {
            $clauses[]      = 'c.from_date >= :range_from';
            $params['range_from'] = $from;
        }

        $to = (string) ($filters['to'] ?? '');
        if ($to !== '' && \App\Helpers::isValidDate($to)) {
            $clauses[]          = 'c.to_date <= :range_to';
            $params['range_to'] = $to;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Keep only non-empty strings, and for the closed vocabularies keep only
     * values the application actually knows — a hand-edited query string can
     * never introduce an unexpected value.
     *
     * @return list<string>
     */
    private static function cleanList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '', $values),
            static fn (string $v): bool => $v !== ''
        )));
    }

    /**
     * Normalise the report filters coming off the query string, dropping
     * anything outside the known vocabularies.
     *
     * @param  array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function filtersFromQuery(array $query): array
    {
        $knownStatuses    = Domain::statuses();
        $knownSpecialties = Domain::specialties();
        $knownDepts       = Users::departments();
        $knownEmployees   = array_map(
            static fn (array $u): string => (string) $u['id'],
            Users::all()
        );

        $pick = static function (mixed $values, array $allowed): array {
            $clean = self::cleanList($values);
            return array_values(array_intersect($clean, $allowed));
        };

        return [
            'employees'   => $pick($query['employees'] ?? [], $knownEmployees),
            'depts'       => $pick($query['depts'] ?? [], $knownDepts),
            'statuses'    => $pick($query['statuses'] ?? [], $knownStatuses),
            'specialties' => $pick($query['specialties'] ?? [], $knownSpecialties),
            'from'        => \App\Helpers::isValidDate((string) ($query['from'] ?? '')) ? (string) $query['from'] : '',
            'to'          => \App\Helpers::isValidDate((string) ($query['to'] ?? '')) ? (string) $query['to'] : '',
        ];
    }

    /** True when no filter is active. */
    public static function filtersEmpty(array $filters): bool
    {
        return ($filters['employees'] ?? []) === []
            && ($filters['depts'] ?? []) === []
            && ($filters['statuses'] ?? []) === []
            && ($filters['specialties'] ?? []) === []
            && ($filters['from'] ?? '') === ''
            && ($filters['to'] ?? '') === '';
    }

    /**
     * CSV of the filtered case list, with the same columns as the prototype's
     * export. A UTF-8 BOM is prepended so Excel opens the Arabic and the em
     * dashes correctly.
     */
    public static function csv(array $filters): string
    {
        $rows   = self::cases($filters);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");

        // All four arguments are passed explicitly: an empty escape string is
        // strict RFC 4180 (quotes are doubled, nothing is backslash-escaped),
        // and PHP 8.4 deprecates relying on the default.
        $row = static function ($handle, array $fields): void {
            fputcsv($handle, $fields, ',', '"', '');
        };

        $row($handle, [
            'Case ID', 'Employee', 'Employee ID', 'Dept', 'Diagnosis', 'Specialty',
            'From', 'To', 'Days', 'Status', 'Documents', 'Reviewer(s)', 'Extensions',
        ]);

        foreach ($rows as $case) {
            $employee = Users::find((string) $case['employee_id']);

            $row($handle, [
                $case['case_id'],
                $employee['name'] ?? '',
                $case['employee_id'],
                $employee['dept'] ?? '',
                $case['diagnosis'],
                $case['specialty'],
                $case['from_date'],
                $case['to_date'],
                Cases::totalDays($case),
                $case['status'],
                count($case['documents']),
                str_replace(', ', ' | ', Cases::reviewersOf($case)),
                count($case['extensions']),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** Filename for the CSV download, stamped with the date. */
    public static function csvFilename(): string
    {
        return 'sick_leave_report_' . date('Y-m-d') . '.csv';
    }
}
