<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;
use App\Domain;
use App\Helpers;

/**
 * Fit-to-work assessment access. Same hydration pattern as Cases: the flat row
 * plus its job demands, attachments and history, batch-loaded for lists.
 */
final class Ftw
{
    /** @return array<string,mixed>|null */
    public static function find(string $id): ?array
    {
        $row = Database::one('SELECT * FROM ftw_requests WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return null;
        }

        return self::hydrateMany([$row])[0];
    }

    /**
     * @param  array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function hydrateMany(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $placeholders = [];
        $params       = [];
        foreach ($rows as $index => $row) {
            $placeholders[]        = ':f' . $index;
            $params['f' . $index] = (string) $row['id'];
        }
        $in = implode(', ', $placeholders);

        $demands     = self::groupBy(Database::all(
            "SELECT * FROM ftw_job_demands WHERE ftw_id IN ({$in}) ORDER BY id",
            $params
        ), 'ftw_id');
        $attachments = self::groupBy(Database::all(
            "SELECT * FROM ftw_attachments WHERE ftw_id IN ({$in}) ORDER BY id",
            $params
        ), 'ftw_id');
        $history     = self::groupBy(Database::all(
            "SELECT * FROM ftw_history WHERE ftw_id IN ({$in}) ORDER BY ts, id",
            $params
        ), 'ftw_id');

        $out = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];

            $row['demands']     = array_map(
                static fn (array $d): string => (string) $d['demand'],
                $demands[$id] ?? []
            );
            $row['attachments'] = $attachments[$id] ?? [];
            $row['history']     = $history[$id] ?? [];

            $out[] = $row;
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

    /** @return array<int,array<string,mixed>> */
    public static function forEmployee(string $employeeId): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM ftw_requests WHERE employee_id = :e ORDER BY created_at DESC, id DESC',
            ['e' => $employeeId]
        ));
    }

    /** Awaiting assessment: newly submitted, plus those sent for a face-to-face. */
    public static function queue(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM ftw_requests WHERE status IN (:s1, :s2) ORDER BY created_at ASC, id ASC',
            ['s1' => Domain::FTW_SUBMITTED, 's2' => Domain::FTW_F2F]
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM ftw_requests ORDER BY created_at DESC, id DESC'
        ));
    }

    /** Restriction records still in force, oldest review date first. */
    public static function restricted(): array
    {
        return self::hydrateMany(Database::all(
            'SELECT * FROM ftw_requests
             WHERE status = :s AND restriction_details IS NOT NULL AND restriction_review_date IS NOT NULL
             ORDER BY restriction_review_date ASC',
            ['s' => Domain::FTW_RESTRICT]
        ));
    }

    /** The most recent active restriction for an employee, or null. */
    public static function activeRestrictionFor(string $employeeId): ?array
    {
        $row = Database::one(
            'SELECT * FROM ftw_requests
             WHERE employee_id = :e AND status = :s
             ORDER BY created_at DESC, id DESC',
            ['e' => $employeeId, 's' => Domain::FTW_RESTRICT]
        );

        return $row === null ? null : self::hydrateMany([$row])[0];
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
            'SELECT COUNT(*) FROM ftw_requests WHERE status IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    // -----------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return string the new FTW id
     */
    public static function create(array $data): string
    {
        $id  = Helpers::refId('FTW', Sequences::next('ftw'));
        $now = Helpers::now();

        Database::insert('ftw_requests', [
            'id'              => $id,
            'employee_id'     => $data['employee_id'],
            'requester_id'    => $data['requester_id'] ?? null,
            'requester_label' => $data['requester_label'],
            'diagnosis'       => $data['diagnosis'],
            'med_history'     => $data['med_history'] ?? '',
            'job_free_text'   => $data['job_free_text'] ?? '',
            'status'          => Domain::FTW_SUBMITTED,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        foreach ((array) ($data['demands'] ?? []) as $demand) {
            // Only demands from the known catalogue are stored.
            if (in_array($demand, Domain::jobDemands(), true)) {
                Database::insert('ftw_job_demands', ['ftw_id' => $id, 'demand' => $demand]);
            }
        }

        return $id;
    }

    public static function addHistory(string $ftwId, string $actor, string $action, string $comment = ''): void
    {
        $ts = Helpers::now();

        Database::insert('ftw_history', [
            'ftw_id'  => $ftwId,
            'ts'      => $ts,
            'actor'   => $actor,
            'action'  => $action,
            'comment' => $comment,
        ]);

        Database::update('ftw_requests', ['updated_at' => $ts], 'id', $ftwId);
    }

    /** @param array<string,mixed> $fields */
    public static function update(string $ftwId, array $fields): void
    {
        $fields['updated_at'] = Helpers::now();
        Database::update('ftw_requests', $fields, 'id', $ftwId);
    }

    /** @param array<string,mixed> $attachment */
    public static function addAttachment(string $ftwId, array $attachment): void
    {
        Database::insert('ftw_attachments', [
            'ftw_id'      => $ftwId,
            'name'        => $attachment['name'],
            'stored_name' => $attachment['stored_name'] ?? null,
            'size_bytes'  => (int) ($attachment['size_bytes'] ?? 0),
            'mime'        => $attachment['mime'] ?? null,
            'uploaded_at' => Helpers::now(),
            'uploaded_by' => $attachment['uploaded_by'] ?? null,
        ]);
    }

    /** @return array<string,mixed>|null */
    public static function findAttachment(int $attachmentId): ?array
    {
        return Database::one('SELECT * FROM ftw_attachments WHERE id = :id', ['id' => $attachmentId]);
    }
}
