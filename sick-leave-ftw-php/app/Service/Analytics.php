<?php

declare(strict_types=1);

namespace App\Service;

use App\Config;
use App\Domain;
use App\Helpers;
use App\I18n;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Repo\Settings;
use App\Repo\Users;

/**
 * Derived numbers: SLA state, entitlement balance, pattern flags, expiring
 * restrictions and the approver dashboard KPIs.
 *
 * Every rule here is a direct port of the prototype's logic, with the
 * thresholds read from the editable settings table instead of constants.
 */
final class Analytics
{
    // -----------------------------------------------------------------
    // SLA
    // -----------------------------------------------------------------

    /**
     * SLA state for a case awaiting medical action, or null when the case is
     * not in a waiting state (the clock only runs on Submitted / Under Medical
     * Review / Pending, exactly as in the prototype).
     *
     * @return array{level:string,hours:float,label:string}|null
     */
    public static function sla(array $case): ?array
    {
        if (!in_array((string) $case['status'], Domain::slaStatuses(), true)) {
            return null;
        }

        $hours = Helpers::hoursSince(Cases::lastActivityTs($case));
        $amber = Settings::slaAmberHours();
        $red   = Settings::slaRedHours();
        $shown = number_format($hours, 0, '.', '');

        // English writes "52h unactioned", Arabic "52 ساعة بدون إجراء", so the
        // number's trailing space differs between the two renderings.
        $count = ['en' => ' ' . $shown, 'ar' => ' ' . $shown . ' '];

        if ($hours >= $red) {
            return [
                'level' => 'red',
                'hours' => $hours,
                'label' => I18n::sentence([['key' => 'sla_escalated'], $count, ['key' => 'sla_unactioned']]),
            ];
        }

        if ($hours >= $amber) {
            return [
                'level' => 'amber',
                'hours' => $hours,
                'label' => I18n::sentence([['key' => 'sla_warning'], $count, ['key' => 'sla_unactioned2']]),
            ];
        }

        return [
            'level' => 'ok',
            'hours' => $hours,
            'label' => I18n::sentence([['key' => 'sla_within'], $shown, ['key' => 'sla_h']]),
        ];
    }

    /** How many open cases have breached the escalation threshold. */
    public static function slaBreachCount(): int
    {
        $count = 0;
        foreach (Cases::byStatuses(Domain::slaStatuses()) as $case) {
            $sla = self::sla($case);
            if ($sla !== null && $sla['level'] === 'red') {
                $count++;
            }
        }
        return $count;
    }

    // -----------------------------------------------------------------
    // Entitlement balance
    // -----------------------------------------------------------------

    /**
     * Sick days consumed this calendar year, and what remains of the annual
     * entitlement. Only approved/closed/extended cases count, and each case's
     * extensions are added on top — the prototype's balanceOf().
     *
     * @return array{used:int,entitlement:int,remaining:int}
     */
    public static function balance(string $employeeId): array
    {
        $yearStart   = date('Y') . '-01-01';
        $entitlement = Settings::entitlementDays();
        $counted     = Domain::entitlementStatuses();
        $used        = 0;

        foreach (Cases::forEmployee($employeeId) as $case) {
            if (!in_array((string) $case['status'], $counted, true)) {
                continue;
            }
            if ((string) $case['from_date'] < $yearStart) {
                continue;
            }
            $used += Cases::totalDays($case);
        }

        return [
            'used'        => $used,
            'entitlement' => $entitlement,
            'remaining'   => max(0, $entitlement - $used),
        ];
    }

    // -----------------------------------------------------------------
    // Pattern / fraud flags — informational only, never blocking
    // -----------------------------------------------------------------

    /**
     * Flags an employee when their recent history shows either several
     * single-day absences or repeated Monday/Friday starts.
     *
     * @return array{flag:bool,reason:string}
     */
    public static function patternFlag(string $employeeId): array
    {
        $windowDays  = (int) Config::get('business.flag_window_days', 90);
        $shortNeeded = (int) Config::get('business.flag_short_count', 3);
        $mfNeeded    = (int) Config::get('business.flag_monfri_count', 2);

        $cutoff = Helpers::dayOffset(-$windowDays);
        $recent = [];

        foreach (Cases::forEmployee($employeeId) as $case) {
            // The prototype measured the window from the case's submission time.
            if (substr((string) $case['created_at'], 0, 10) >= $cutoff) {
                $recent[] = $case;
            }
        }

        $shortOnes = 0;
        $monFri    = 0;

        foreach ($recent as $case) {
            if (Helpers::daysBetween((string) $case['from_date'], (string) $case['to_date']) <= 1) {
                $shortOnes++;
            }
            // 1 = Monday, 5 = Friday
            $dayOfWeek = (int) date('N', (int) strtotime((string) $case['from_date']));
            if ($dayOfWeek === 1 || $dayOfWeek === 5) {
                $monFri++;
            }
        }

        if ($shortOnes >= $shortNeeded) {
            return ['flag' => true, 'reason' => $shortOnes . ' ' . I18n::t('reason_shortones')];
        }

        if ($monFri >= $mfNeeded && count($recent) >= 2) {
            return ['flag' => true, 'reason' => $monFri . ' ' . I18n::t('reason_monfri')];
        }

        return ['flag' => false, 'reason' => ''];
    }

    /** @return array<int,array{user:array<string,mixed>,flag:array{flag:bool,reason:string}}> */
    public static function flaggedEmployees(): array
    {
        $flagged = [];

        foreach (Users::employees() as $employee) {
            $flag = self::patternFlag((string) $employee['id']);
            if ($flag['flag']) {
                $flagged[] = ['user' => $employee, 'flag' => $flag];
            }
        }

        return $flagged;
    }

    // -----------------------------------------------------------------
    // Restriction expiry
    // -----------------------------------------------------------------

    /**
     * Restrictions — from both FTW assessments and restricted returns to work —
     * whose review date falls inside the expiry window (or has already passed).
     *
     * @return array<int,array{kind:string,ref_id:string,employee_id:string,details:string,review_date:string}>
     */
    public static function expiringRestrictions(): array
    {
        $horizon = Helpers::dayOffset((int) Config::get('business.expiry_window_days', 7));
        $items   = [];

        foreach (Ftw::restricted() as $record) {
            if ((string) $record['restriction_review_date'] <= $horizon) {
                $items[] = [
                    'kind'        => 'ftw',
                    'ref_id'      => (string) $record['id'],
                    'employee_id' => (string) $record['employee_id'],
                    'details'     => (string) $record['restriction_details'],
                    'review_date' => (string) $record['restriction_review_date'],
                ];
            }
        }

        foreach (Cases::restricted() as $case) {
            if ((string) $case['restriction_review_date'] <= $horizon) {
                $items[] = [
                    'kind'        => 'case',
                    'ref_id'      => (string) $case['case_id'],
                    'employee_id' => (string) $case['employee_id'],
                    'details'     => (string) $case['restriction_details'],
                    'review_date' => (string) $case['restriction_review_date'],
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['review_date'], $b['review_date']));

        return $items;
    }

    // -----------------------------------------------------------------
    // Approver dashboard KPIs
    // -----------------------------------------------------------------

    /** @return array<string,int> */
    public static function kpis(): array
    {
        return [
            'pending_review' => Cases::countByStatuses(Domain::slaStatuses()),
            'rtw_pending'    => Cases::countByStatuses([Domain::STATUS_RTW_PENDING]),
            'rtw_done'       => Cases::countRtwCompleted(),
            'ftw_pending'    => Ftw::countByStatuses([Domain::FTW_SUBMITTED, Domain::FTW_F2F]),
            'ftw_done'       => Ftw::countByStatuses([
                Domain::FTW_FIT,
                Domain::FTW_NOTFIT,
                Domain::FTW_RESTRICT,
                Domain::FTW_DIAGTEST,
            ]),
            'expiring_soon'  => count(self::expiringRestrictions()),
            'flags'          => count(self::flaggedEmployees()),
            'sla_breach'     => self::slaBreachCount(),
        ];
    }

    /**
     * Combined newest-first activity stream across every case and assessment.
     *
     * @return array<int,array{ts:string,text:string}>
     */
    public static function activityFeed(int $limit = 12): array
    {
        $items = [];

        foreach (Cases::all() as $case) {
            $name = Users::nameOf((string) $case['employee_id']);
            foreach ($case['history'] as $entry) {
                $comment = (string) ($entry['comment'] ?? '');
                $items[] = [
                    'ts'   => (string) $entry['ts'],
                    'text' => $case['case_id'] . ' · ' . $name . ' — ' . $entry['action']
                              . ($comment !== '' ? ': ' . $comment : ''),
                ];
            }
        }

        foreach (Ftw::all() as $record) {
            $name = Users::nameOf((string) $record['employee_id']);
            foreach ($record['history'] as $entry) {
                $items[] = [
                    'ts'   => (string) $entry['ts'],
                    'text' => $record['id'] . ' · ' . $name . ' — ' . $entry['action'],
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['ts'], $a['ts']));

        return array_slice($items, 0, max(1, $limit));
    }
}
