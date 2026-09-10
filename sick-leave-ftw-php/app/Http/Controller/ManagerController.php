<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Helpers;
use App\Http\Layout;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Repo\Notifications;
use App\Repo\Users;

/**
 * Manager screens: team dashboard and the decisions feed.
 *
 * The team dashboard deliberately carries no diagnosis, no medical history and
 * no documents — only status, dates, whether a return is overdue and whether the
 * person is on restricted duty. That is the field-level access rule the
 * prototype's banner described.
 */
final class ManagerController
{
    /** @param array<string,mixed> $user */
    public static function teamDashboard(array $user, string $tab): string
    {
        $rows = [];

        foreach (Users::teamOf((string) $user['id']) as $member) {
            $memberId = (string) $member['id'];
            $cases    = Cases::forEmployee($memberId);

            $active = null;
            foreach ($cases as $case) {
                if (!in_array((string) $case['status'], [
                    Domain::STATUS_CLOSED,
                    Domain::STATUS_REJECTED,
                    Domain::STATUS_RETURNED_RESTRICTED,
                ], true)) {
                    $active = $case;
                    break;
                }
            }

            $restrictedCase = null;
            foreach ($cases as $case) {
                if ((string) $case['status'] === Domain::STATUS_RETURNED_RESTRICTED) {
                    $restrictedCase = $case;
                    break;
                }
            }

            $ftwRestriction = Ftw::activeRestrictionFor($memberId);

            $rows[] = [
                'member'          => $member,
                'active'          => $active,
                'rtw_overdue'     => $active !== null
                                     && (string) $active['status'] === Domain::STATUS_RTW_PENDING
                                     && Helpers::isPast((string) $active['to_date']),
                'ftw_restriction' => $ftwRestriction,
                'restricted_case' => $restrictedCase,
                'restricted'      => $ftwRestriction !== null || $restrictedCase !== null,
            ];
        }

        return Layout::page($user, $tab, 'manager/team', ['rows' => $rows]);
    }

    /** @param array<string,mixed> $user */
    public static function feed(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'manager/feed', [
            'notifications' => Notifications::forUser((string) $user['id'], (string) $user['role'], 100),
        ]);
    }
}
