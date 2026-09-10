<?php

declare(strict_types=1);

namespace App;

/**
 * Domain vocabulary. The string values are the canonical ones stored in the
 * database and used as i18n lookup keys, so they must not be renamed without
 * a matching data migration.
 */
final class Domain
{
    // -----------------------------------------------------------------
    // Sick leave case statuses
    // -----------------------------------------------------------------
    public const STATUS_SUBMITTED           = 'Submitted';
    public const STATUS_REVIEW              = 'Under Medical Review';
    public const STATUS_PENDING             = 'Pending – Info Requested';
    public const STATUS_APPROVED            = 'Approved';
    public const STATUS_RTW_PENDING         = 'RTW Pending';
    public const STATUS_CLOSED              = 'Closed – Fit to Return';
    public const STATUS_EXTENDED            = 'Extended – Not Fit';
    public const STATUS_REJECTED            = 'Rejected';
    public const STATUS_RETURNED_RESTRICTED = 'Returned – With Restrictions';

    // -----------------------------------------------------------------
    // Fit-to-work assessment statuses
    // -----------------------------------------------------------------
    public const FTW_SUBMITTED = 'Submitted';
    public const FTW_FIT       = 'Fit to Work';
    public const FTW_NOTFIT    = 'Not Fit to Work';
    public const FTW_RESTRICT  = 'Fit with Restrictions';
    public const FTW_F2F       = 'Needs F2F Consultation';
    public const FTW_DIAGTEST  = 'Needs Further Diagnostic Tests';

    // -----------------------------------------------------------------
    // Roles and portals
    // -----------------------------------------------------------------
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_MANAGER  = 'manager';
    public const ROLE_MEDICAL  = 'medical';
    public const ROLE_HR       = 'hr';
    public const ROLE_ADMIN    = 'admin';

    public const PORTAL_REQUESTER = 'requester';
    public const PORTAL_APPROVER  = 'approver';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_REVIEW,
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_RTW_PENDING,
            self::STATUS_CLOSED,
            self::STATUS_EXTENDED,
            self::STATUS_REJECTED,
            self::STATUS_RETURNED_RESTRICTED,
        ];
    }

    /** @return list<string> */
    public static function ftwStatuses(): array
    {
        return [
            self::FTW_SUBMITTED,
            self::FTW_FIT,
            self::FTW_NOTFIT,
            self::FTW_RESTRICT,
            self::FTW_F2F,
            self::FTW_DIAGTEST,
        ];
    }

    /** @return list<string> */
    public static function specialties(): array
    {
        return ['General', 'Orthopedic', 'ENT', 'Cardiology', 'Dermatology', 'Ophthalmology', 'Gastroenterology'];
    }

    /** @return list<string> */
    public static function jobDemands(): array
    {
        return [
            'Lifting/carrying weight',
            'Prolonged standing',
            'Repetitive motion',
            'Driving',
            'Working at heights',
            'Exposure to noise',
            'Exposure to chemicals',
            'Shift work',
        ];
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return [self::ROLE_EMPLOYEE, self::ROLE_MANAGER, self::ROLE_MEDICAL, self::ROLE_HR, self::ROLE_ADMIN];
    }

    /**
     * Which roles may sign in to which portal. Enforced on login and on every
     * subsequent request, so a requester-portal session can never reach an
     * approver-only route.
     *
     * @return list<string>
     */
    public static function rolesForPortal(string $portal): array
    {
        return $portal === self::PORTAL_REQUESTER
            ? [self::ROLE_EMPLOYEE, self::ROLE_MANAGER]
            : [self::ROLE_MEDICAL, self::ROLE_HR, self::ROLE_ADMIN];
    }

    public static function portalForRole(string $role): string
    {
        return in_array($role, [self::ROLE_EMPLOYEE, self::ROLE_MANAGER], true)
            ? self::PORTAL_REQUESTER
            : self::PORTAL_APPROVER;
    }

    public static function isPortal(string $portal): bool
    {
        return in_array($portal, [self::PORTAL_REQUESTER, self::PORTAL_APPROVER], true);
    }

    /** Statuses that still count as an "open" case for the employee/manager views. */
    public static function openStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_REVIEW,
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_RTW_PENDING,
            self::STATUS_EXTENDED,
        ];
    }

    /** Statuses awaiting a medical action — these are the ones the SLA clock runs on. */
    public static function slaStatuses(): array
    {
        return [self::STATUS_SUBMITTED, self::STATUS_REVIEW, self::STATUS_PENDING];
    }

    /** Statuses whose days consume the employee's annual entitlement. */
    public static function entitlementStatuses(): array
    {
        return [
            self::STATUS_APPROVED,
            self::STATUS_RTW_PENDING,
            self::STATUS_CLOSED,
            self::STATUS_EXTENDED,
        ];
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED           => 'b-submitted',
            self::STATUS_REVIEW              => 'b-review',
            self::STATUS_PENDING             => 'b-pending',
            self::STATUS_APPROVED            => 'b-approved',
            self::STATUS_RTW_PENDING         => 'b-rtwpending',
            self::STATUS_CLOSED              => 'b-closed',
            self::STATUS_EXTENDED            => 'b-extended',
            self::STATUS_REJECTED            => 'b-rejected',
            self::STATUS_RETURNED_RESTRICTED => 'b-restrict',
            default                          => 'b-submitted',
        };
    }

    public static function ftwBadgeClass(string $status): string
    {
        return match ($status) {
            self::FTW_SUBMITTED => 'b-submitted',
            self::FTW_FIT       => 'b-fit',
            self::FTW_NOTFIT    => 'b-notfit',
            self::FTW_RESTRICT  => 'b-restrict',
            self::FTW_F2F       => 'b-f2f',
            self::FTW_DIAGTEST  => 'b-diagtest',
            default             => 'b-submitted',
        };
    }
}
