<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;
use App\Domain;
use App\Helpers;

/**
 * Sick leave case access.
 *
 * A "hydrated" case is the flat sick_cases row plus its history, documents and
 * extensions. Lists are hydrated in batch (three queries for any number of
 * cases) rather than one query per row.
 */
final class Cases
{
    /** @return array<string,mixed>|null */
    public static function find(string $caseId): ?array
    {
        $row = Database::one('SELECT * FROM sick_cases WHERE case_id = :id', ['id' => $caseId]);
        if ($row === null) {
            return null;
        }

        $hydrated = self::hydrateMany([$row]);
        return $hydrated[0];
    }

    /**
     * Attach history, documents and extensions to a set of case rows.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function hydrateMany(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids          = array_map(static fn (array $r): string => (string) $r['case_id'], $rows);
        $placeholders = [];
        $params       = [];
        foreach ($ids as $index => $id) {
            $placeholders[]         = ':c' . $index;
            $params['c' . $index]  = $id;
        }
        $in = implode(', ', $placeholders);

        $history    = self::groupBy(Database::all(
            "SELECT * FROM case_history WHERE case_id IN ({$in}) ORDER BY ts, id",
            $params
        ), 'case_id');
        $documents  = self::groupBy(Database::all(
            "SELECT * FROM case_documents WHERE case_id IN ({$in}) ORDER BY name, version, id",
            $params
        ), 'case_id');
        $extensions = self::groupBy(Database::all(
            "SELECT * FROM case_extensions WHERE case_id IN ({$in}) ORDER BY ts, id",
            $params
        ), 'case_id');

        $out = [];
        foreach ($rows as $row) {
            $id                = (string) $row['case_id'];
            $row['history']    = $history[$id] ?? [];
            $row['documents']  = $documents[$id] ?? [];
            $row['extensions'] = $extensions[$id] ?? [];
            $out[]             = $row;
        }

        return $out;
    }

    /**
     * @param  array<int,array<string,mixed>> $rows
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function groupBy(array $rows, string $key): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row[$key]][] = $row;
        }
        return $grouped;
    }

    /**
     * All of one employee's cases, newest first — the prototype ordered by the
     * timestamp of the first history entry, which is the case's created_at.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forEmployee(string $employeeId): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases WHERE employee_id = :e ORDER BY created_at DESC, case_id DESC',
            ['e' => $employeeId]
        ));
    }

    /**
     * Medical review queue: Submitted + Under Medical Review, longest-waiting
     * first (ordered by the last activity on the case).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function reviewQueue(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases WHERE status IN (:s1, :s2) ORDER BY updated_at ASC, case_id ASC',
            ['s1' => Domain::STATUS_SUBMITTED, 's2' => Domain::STATUS_REVIEW]
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public static function rtwQueue(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases WHERE status = :s ORDER BY to_date ASC, case_id ASC',
            ['s' => Domain::STATUS_RTW_PENDING]
        ));
    }

    /**
     * @param  list<string> $statuses
     * @return array<int,array<string,mixed>>
     */
    public static function byStatuses(array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        $placeholders = [];
        $params       = [];
        foreach ($statuses as $index => $status) {
            $placeholders[]        = ':s' . $index;
            $params['s' . $index] = $status;
        }

        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases WHERE status IN (' . implode(', ', $placeholders) . ')
             ORDER BY created_at DESC, case_id DESC',
            $params
        ));
    }

    /** @param list<string> $statuses */
    public static function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        $placeholders = [];
        $params       = [];
        foreach ($statuses as $index => $status) {
            $placeholders[]        = ':s' . $index;
            $params['s' . $index] = $status;
        }

        return (int) Database::scalar(
            'SELECT COUNT(*) FROM sick_cases WHERE status IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases ORDER BY created_at DESC, case_id DESC'
        ));
    }

    /** Cases returned to work under restrictions that are still on file. @return array<int,array<string,mixed>> */
    public static function restricted(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM sick_cases
             WHERE status = :s AND restriction_details IS NOT NULL AND restriction_review_date IS NOT NULL
             ORDER BY restriction_review_date ASC',
            ['s' => Domain::STATUS_RETURNED_RESTRICTED]
        ));
    }

    /** How many RTW assessments concluded "Fit to Return". */
    public static function countRtwCompleted(): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM sick_cases WHERE rtw_outcome = :o',
            ['o' => 'Fit to Return']
        );
    }

    // -----------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return string the new case id
     */
    public static function create(array $data): string
    {
        $caseId = Helpers::refId('SL', Sequences::next('sick_case'));
        $now    = Helpers::now();

        Database::insert('sick_cases', [
            'case_id'      => $caseId,
            'employee_id'  => $data['employee_id'],
            'diagnosis'    => $data['diagnosis'],
            'specialty'    => $data['specialty'],
            'from_date'    => $data['from_date'],
            'to_date'      => $data['to_date'],
            'status'       => Domain::STATUS_SUBMITTED,
            'rtw_required' => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return $caseId;
    }

    public static function addHistory(string $caseId, string $actor, string $action, string $comment = ''): void
    {
        $ts = Helpers::now();

        Database::insert('case_history', [
            'case_id' => $caseId,
            'ts'      => $ts,
            'actor'   => $actor,
            'action'  => $action,
            'comment' => $comment,
        ]);

        // updated_at doubles as "last activity", which drives the SLA clock and
        // the review-queue ordering.
        Database::update('sick_cases', ['updated_at' => $ts], 'case_id', $caseId);
    }

    /** @param array<string,mixed> $fields */
    public static function update(string $caseId, array $fields): void
    {
        $fields['updated_at'] = Helpers::now();
        Database::update('sick_cases', $fields, 'case_id', $caseId);
    }

    /**
     * Store a document, versioning by filename the way the prototype did: a
     * second upload of the same name becomes v2 of that document.
     *
     * @param array<string,mixed> $doc
     */
    public static function addDocument(string $caseId, array $doc): void
    {
        $existing = (int) Database::scalar(
            'SELECT COUNT(*) FROM case_documents WHERE case_id = :c AND name = :n',
            ['c' => $caseId, 'n' => $doc['name']]
        );

        Database::insert('case_documents', [
            'case_id'     => $caseId,
            'name'        => $doc['name'],
            'version'     => $existing + 1,
            'stored_name' => $doc['stored_name'] ?? null,
            'size_bytes'  => (int) ($doc['size_bytes'] ?? 0),
            'mime'        => $doc['mime'] ?? null,
            'uploaded_at' => Helpers::now(),
            'uploaded_by' => $doc['uploaded_by'] ?? null,
        ]);
    }

    public static function addExtension(string $caseId, string $fromDate, string $toDate): void
    {
        Database::insert('case_extensions', [
            'case_id'   => $caseId,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'ts'        => Helpers::now(),
        ]);
    }

    /** @return array<string,mixed>|null */
    public static function findDocument(int $documentId): ?array
    {
        return Database::one('SELECT * FROM case_documents WHERE id = :id', ['id' => $documentId]);
    }

    /** Total leave days a case represents, including every extension. */
    public static function totalDays(array $case): int
    {
        $days = Helpers::daysBetween((string) $case['from_date'], (string) $case['to_date']);

        foreach ($case['extensions'] ?? [] as $extension) {
            $days += Helpers::daysBetween((string) $extension['from_date'], (string) $extension['to_date']);
        }

        return $days;
    }

    /** Timestamp of the most recent activity — the SLA clock's start point. */
    public static function lastActivityTs(array $case): string
    {
        $history = $case['history'] ?? [];
        if ($history !== []) {
            return (string) $history[count($history) - 1]['ts'];
        }
        return (string) $case['updated_at'];
    }

    /** Distinct medical reviewers who touched a case, for the audit report. */
    public static function reviewersOf(array $case): string
    {
        $reviewers = [];
        foreach ($case['history'] ?? [] as $entry) {
            $actor = (string) $entry['actor'];
            if (str_contains($actor, 'Medical')) {
                $reviewers[$actor] = true;
            }
        }

        return $reviewers === [] ? Helpers::EM_DASH : implode(', ', array_keys($reviewers));
    }
}
