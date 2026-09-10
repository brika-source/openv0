<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;
use App\Domain;
use App\Helpers;

/**
 * User directory access.
 */
final class Users
{
    private const COLUMNS = 'id, name, email, dept, role, manager_id, is_active, created_at';

    /** @return array<string,mixed>|null */
    public static function find(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        return Database::one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::one(
            'SELECT ' . self::COLUMNS . ', password_hash FROM users WHERE email = :e',
            ['e' => strtolower(trim($email))]
        );
    }

    /**
     * Look up an account for authentication, by e-mail address or by user id,
     * including the password hash.
     *
     * find() deliberately omits password_hash so that a hash can never reach a
     * view or a log by accident; this is the one path that needs it.
     *
     * @return array<string,mixed>|null
     */
    public static function findForAuth(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return self::findByEmail($identifier);
        }

        return Database::one(
            'SELECT ' . self::COLUMNS . ', password_hash FROM users WHERE id = :id',
            ['id' => $identifier]
        );
    }

    /** Name only, safe for a missing id — used when rendering historical records. */
    public static function nameOf(?string $id): string
    {
        $user = self::find($id);
        return $user === null ? Helpers::EM_DASH : (string) $user['name'];
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM users';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        // Sort U1, U2 ... U11 numerically rather than lexicographically.
        $sql .= ' ORDER BY LENGTH(id), id';

        return Database::all($sql);
    }

    /** @return array<int,array<string,mixed>> */
    public static function byRole(string $role, bool $activeOnly = true): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM users WHERE role = :r';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY LENGTH(id), id';

        return Database::all($sql, ['r' => $role]);
    }

    /**
     * Accounts eligible to sign in to a portal.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forPortal(string $portal): array
    {
        $roles        = Domain::rolesForPortal($portal);
        $placeholders = [];
        $params       = [];
        foreach ($roles as $index => $role) {
            $placeholders[]        = ':r' . $index;
            $params['r' . $index] = $role;
        }

        return Database::all(
            'SELECT ' . self::COLUMNS . ' FROM users
             WHERE is_active = 1 AND role IN (' . implode(', ', $placeholders) . ')
             ORDER BY LENGTH(id), id',
            $params
        );
    }

    /** Direct reports of a manager. @return array<int,array<string,mixed>> */
    public static function teamOf(string $managerId): array
    {
        return Database::all(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE manager_id = :m ORDER BY LENGTH(id), id',
            ['m' => $managerId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function employees(bool $activeOnly = true): array
    {
        return self::byRole(Domain::ROLE_EMPLOYEE, $activeOnly);
    }

    /** Distinct department names, for the report filters. @return list<string> */
    public static function departments(): array
    {
        $rows = Database::all('SELECT DISTINCT dept FROM users ORDER BY dept');
        return array_map(static fn (array $r): string => (string) $r['dept'], $rows);
    }

    /** @return array<int,array<string,mixed>> */
    public static function searchEmployees(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        // LIKE with an escaped pattern; % and _ in the search term are literal.
        $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($term)) . '%';

        return Database::all(
            "SELECT " . self::COLUMNS . " FROM users
             WHERE role = :role AND is_active = 1
               AND (LOWER(name) LIKE :p ESCAPE '\\' OR LOWER(id) LIKE :p2 ESCAPE '\\')
             ORDER BY LENGTH(id), id",
            ['role' => Domain::ROLE_EMPLOYEE, 'p' => $pattern, 'p2' => $pattern]
        );
    }

    public static function emailExists(string $email, ?string $exceptId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE email = :e';
        $params = ['e' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql          .= ' AND id <> :id';
            $params['id']  = $exceptId;
        }
        return (int) Database::scalar($sql, $params) > 0;
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): string
    {
        $id = 'U' . Sequences::next('user');

        Database::insert('users', [
            'id'            => $id,
            'name'          => $data['name'],
            'email'         => strtolower(trim((string) $data['email'])),
            'dept'          => $data['dept'],
            'role'          => $data['role'],
            'manager_id'    => $data['manager_id'] ?? null,
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'is_active'     => 1,
            'created_at'    => Helpers::now(),
        ]);

        return $id;
    }

    public static function setRole(string $id, string $role): void
    {
        Database::update('users', ['role' => $role], 'id', $id);
    }

    public static function setActive(string $id, bool $active): void
    {
        Database::update('users', ['is_active' => $active ? 1 : 0], 'id', $id);
    }

    public static function setPassword(string $id, string $plain): void
    {
        Database::update('users', ['password_hash' => password_hash($plain, PASSWORD_DEFAULT)], 'id', $id);
    }

    public static function passwordHash(string $id): ?string
    {
        $hash = Database::scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        return is_string($hash) ? $hash : null;
    }

    /** "Mona Fathy (Employee)" — the actor label written into history rows. */
    public static function actorLabel(string $id, string $role): string
    {
        $user = self::find($id);
        $name = $user === null ? $id : (string) $user['name'];

        $roleWord = match ($role) {
            Domain::ROLE_EMPLOYEE => 'Employee',
            Domain::ROLE_MANAGER  => 'Manager',
            Domain::ROLE_MEDICAL  => 'Medical',
            Domain::ROLE_HR       => 'HR',
            Domain::ROLE_ADMIN    => 'Admin',
            default               => ucfirst($role),
        };

        return $name . ' (' . $roleWord . ')';
    }
}
