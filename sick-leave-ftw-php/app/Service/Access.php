<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain;

/**
 * Field- and record-level access rules, enforced on the server.
 *
 * The prototypes stated the confidentiality rule in a banner ("Diagnosis and
 * medical detail are confidential to Occupational Health. This view shows
 * status and dates only, per field-level access control") but every detail view
 * was a global JavaScript function, so a manager who followed a "view
 * restriction" link was shown the full patient story including diagnosis.
 *
 * Here the rule is actually applied: a manager gets a restriction-only view
 * containing the restriction text, its review date and the employee's status —
 * never a diagnosis, a medical history or a document.
 */
final class Access
{
    /** Roles that may see clinical detail: diagnosis, medical history, documents. */
    public static function seesClinicalDetail(string $role): bool
    {
        return in_array($role, [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN], true);
    }

    /** Only Occupational Health records decisions. */
    public static function canDecide(string $role): bool
    {
        return $role === Domain::ROLE_MEDICAL;
    }

    /** Who may pull the audit report and the CSV export. */
    public static function canReport(string $role): bool
    {
        return in_array($role, [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN], true);
    }

    /**
     * Whether a user may open a sick leave case at all (in whatever detail
     * their role allows).
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     */
    public static function canViewCase(array $user, array $case): bool
    {
        $role = (string) $user['role'];

        if (self::seesClinicalDetail($role)) {
            return true;
        }

        if ($role === Domain::ROLE_EMPLOYEE) {
            return (string) $case['employee_id'] === (string) $user['id'];
        }

        if ($role === Domain::ROLE_MANAGER) {
            return self::managesEmployee($user, (string) $case['employee_id']);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $record
     */
    public static function canViewFtw(array $user, array $record): bool
    {
        $role = (string) $user['role'];

        if (self::seesClinicalDetail($role)) {
            return true;
        }

        if ($role === Domain::ROLE_EMPLOYEE) {
            return (string) $record['employee_id'] === (string) $user['id'];
        }

        if ($role === Domain::ROLE_MANAGER) {
            return self::managesEmployee($user, (string) $record['employee_id'])
                // A manager who raised the request can see that it exists.
                || (string) ($record['requester_id'] ?? '') === (string) $user['id'];
        }

        return false;
    }

    /**
     * Whether a user may download an attachment. Clinical roles may; the
     * employee may download files on their own records; a manager never may,
     * because attachments are medical documents.
     *
     * @param array<string,mixed> $user
     */
    public static function canDownload(array $user, string $ownerEmployeeId): bool
    {
        $role = (string) $user['role'];

        if (self::seesClinicalDetail($role)) {
            return true;
        }

        return $role === Domain::ROLE_EMPLOYEE && $ownerEmployeeId === (string) $user['id'];
    }

    /** Whether an FTW request may be raised for a given employee by this user. */
    public static function canRequestFtwFor(array $user, array $employee): bool
    {
        $role = (string) $user['role'];

        return match ($role) {
            Domain::ROLE_EMPLOYEE => (string) $employee['id'] === (string) $user['id'],
            Domain::ROLE_MANAGER  => (string) ($employee['manager_id'] ?? '') === (string) $user['id'],
            Domain::ROLE_MEDICAL, Domain::ROLE_HR => true,
            default               => false,
        };
    }

    /** @param array<string,mixed> $user */
    public static function managesEmployee(array $user, string $employeeId): bool
    {
        if ((string) $user['role'] !== Domain::ROLE_MANAGER) {
            return false;
        }

        $employee = \App\Repo\Users::find($employeeId);

        return $employee !== null && (string) ($employee['manager_id'] ?? '') === (string) $user['id'];
    }

    /** Employees an FTW request may be raised for, given the requester's role. */
    public static function ftwCandidates(array $user): array
    {
        if ((string) $user['role'] === Domain::ROLE_MANAGER) {
            return array_values(array_filter(
                \App\Repo\Users::teamOf((string) $user['id']),
                static fn (array $u): bool => (string) $u['role'] === Domain::ROLE_EMPLOYEE
            ));
        }

        return \App\Repo\Users::employees();
    }
}
