<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;
use App\Domain;
use App\Helpers;

/**
 * Notification fan-out and per-user read state.
 *
 * A notification is addressed to one or more targets, using the same three
 * shapes as the prototypes:
 *   'U1'                a specific person
 *   'role:medical'      everyone holding a role
 *   'role:manager:U6'   one specific manager (their direct report is involved)
 */
final class Notifications
{
    /**
     * @param list<string> $targets
     */
    public static function push(array $targets, string $event, string $message, ?string $refId = null): int
    {
        // Drop empty/null targets — an employee with no manager yields
        // 'role:manager:' from the caller, which must never match anyone.
        $targets = array_values(array_unique(array_filter(
            $targets,
            static fn (?string $t): bool => is_string($t) && $t !== '' && $t !== 'role:manager:'
        )));

        Database::insert('notifications', [
            'ts'      => Helpers::now(),
            'event'   => $event,
            'message' => $message,
            'ref_id'  => $refId,
        ]);
        $id = (int) Database::lastInsertId();

        foreach ($targets as $target) {
            Database::insert('notification_targets', [
                'notification_id' => $id,
                'target'          => $target,
            ]);
        }

        return $id;
    }

    /**
     * The target strings that address a given session.
     *
     * @return list<string>
     */
    private static function targetsFor(string $userId, string $role): array
    {
        $targets = [$userId, 'role:' . $role];

        if ($role === Domain::ROLE_MANAGER) {
            $targets[] = 'role:manager:' . $userId;
        }

        return $targets;
    }

    /**
     * Builds "<alias>.id IN (SELECT ...)" restricting notifications to those
     * addressed to this session, plus the bound target parameters.
     *
     * @return array{sql:string,params:array<string,mixed>}
     */
    private static function targetClause(string $userId, string $role, string $alias = 'n'): array
    {
        $placeholders = [];
        $params       = [];
        foreach (self::targetsFor($userId, $role) as $index => $target) {
            $placeholders[]        = ':t' . $index;
            $params['t' . $index] = $target;
        }

        return [
            'sql'    => $alias . '.id IN (SELECT notification_id FROM notification_targets WHERE target IN ('
                        . implode(', ', $placeholders) . '))',
            'params' => $params,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(string $userId, string $role, int $limit = 100): array
    {
        $clause = self::targetClause($userId, $role);

        $rows = Database::all(
            'SELECT n.*,
                    CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
             FROM notifications n
             LEFT JOIN notification_reads r
                    ON r.notification_id = n.id AND r.user_id = :uid
             WHERE ' . $clause['sql'] . '
             ORDER BY n.ts DESC, n.id DESC
             LIMIT ' . max(1, min(500, $limit)),
            $clause['params'] + ['uid' => $userId]
        );

        return $rows;
    }

    public static function unreadCount(string $userId, string $role): int
    {
        $clause = self::targetClause($userId, $role);

        return (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications n
             WHERE ' . $clause['sql'] . '
               AND NOT EXISTS (
                   SELECT 1 FROM notification_reads r
                   WHERE r.notification_id = n.id AND r.user_id = :uid
               )',
            $clause['params'] + ['uid' => $userId]
        );
    }

    /** Mark every notification currently addressed to this user as read. */
    public static function markAllRead(string $userId, string $role): void
    {
        $clause = self::targetClause($userId, $role);

        $rows = Database::all(
            'SELECT n.id FROM notifications n
             WHERE ' . $clause['sql'] . '
               AND NOT EXISTS (
                   SELECT 1 FROM notification_reads r
                   WHERE r.notification_id = n.id AND r.user_id = :uid
               )',
            $clause['params'] + ['uid' => $userId]
        );

        $now = Helpers::now();
        foreach ($rows as $row) {
            Database::insert('notification_reads', [
                'notification_id' => (int) $row['id'],
                'user_id'         => $userId,
                'read_at'         => $now,
            ]);
        }
    }
}
