<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;
use App\Helpers;

/**
 * Append-only audit trail. Every create, decision, role change, settings change
 * and sign-in is recorded; nothing in the UI can edit or delete an entry.
 */
final class Audit
{
    public static function log(
        ?array $actor,
        string $action,
        ?string $entity = null,
        ?string $entityId = null,
        string $detail = ''
    ): void {
        Database::insert('audit_log', [
            'ts'        => Helpers::now(),
            'user_id'   => $actor['id'] ?? null,
            'user_name' => $actor['name'] ?? null,
            'role'      => $actor['role'] ?? null,
            'portal'    => $actor['portal'] ?? null,
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'detail'    => Helpers::truncate($detail, 500),
            'ip'        => self::clientIp(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 200, int $offset = 0): array
    {
        return Database::all(
            'SELECT * FROM audit_log ORDER BY ts DESC, id DESC LIMIT ' . max(1, min(500, $limit))
            . ' OFFSET ' . max(0, $offset)
        );
    }

    public static function count(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM audit_log');
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? substr($ip, 0, 45) : '';
    }
}
