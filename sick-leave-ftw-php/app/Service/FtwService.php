<?php

declare(strict_types=1);

namespace App\Service;

use App\Database;
use App\Domain;
use App\Helpers;
use App\I18n;
use App\Repo\Audit;
use App\Repo\Ftw;
use App\Repo\Notifications;
use App\Repo\Users;
use App\ValidationException;

/**
 * Fit-to-work workflow — Module B.
 *
 * A request (Screen B1) can be raised by the employee themselves, by their
 * manager, by HR or by the medical team; the assessment (Screen B2) is always
 * recorded by the medical team.
 */
final class FtwService
{
    /**
     * Screen B1 — raise an FTW request.
     *
     * @param  array<string,mixed> $user      the requester
     * @param  array<string,mixed> $input     employee_id, diagnosis, med_history, demands, job_free_text, attachments
     * @return string the new FTW id
     */
    public static function request(array $user, array $input): string
    {
        $role = (string) $user['role'];

        // An employee may only ever raise a request about themselves.
        $employeeId = $role === Domain::ROLE_EMPLOYEE
            ? (string) $user['id']
            : trim((string) ($input['employee_id'] ?? ''));

        if ($employeeId === '') {
            throw new ValidationException(I18n::t('err_no_employee'));
        }

        $employee = Users::find($employeeId);
        if ($employee === null || (string) $employee['role'] !== Domain::ROLE_EMPLOYEE) {
            throw new ValidationException(I18n::t('err_no_employee'));
        }

        // A manager may only raise requests for their own direct reports.
        if ($role === Domain::ROLE_MANAGER && (string) ($employee['manager_id'] ?? '') !== (string) $user['id']) {
            throw new ValidationException(I18n::t('err_forbidden'));
        }

        $diagnosis = trim((string) ($input['diagnosis'] ?? ''));
        if ($diagnosis === '') {
            throw new ValidationException(I18n::topt('toast_need_diag'));
        }

        $requesterLabel = Auth::actorLabel($user);
        $medHistory     = trim((string) ($input['med_history'] ?? ''));
        $jobFreeText    = trim((string) ($input['job_free_text'] ?? ''));
        $demands        = array_values(array_filter(
            (array) ($input['demands'] ?? []),
            static fn (mixed $d): bool => is_string($d) && in_array($d, Domain::jobDemands(), true)
        ));
        $attachments    = (array) ($input['attachments'] ?? []);

        return Database::transaction(static function () use (
            $user, $employee, $employeeId, $diagnosis, $medHistory, $jobFreeText,
            $demands, $attachments, $requesterLabel
        ): string {
            $ftwId = Ftw::create([
                'employee_id'     => $employeeId,
                'requester_id'    => (string) $user['id'],
                'requester_label' => $requesterLabel,
                'diagnosis'       => $diagnosis,
                'med_history'     => $medHistory,
                'job_free_text'   => $jobFreeText,
                'demands'         => $demands,
            ]);

            foreach ($attachments as $attachment) {
                Ftw::addAttachment($ftwId, $attachment + ['uploaded_by' => $user['id']]);
            }

            Ftw::addHistory($ftwId, $requesterLabel, 'FTW Requested', '');

            Notifications::push(
                ['role:' . Domain::ROLE_MEDICAL],
                'FTW request submitted',
                'FTW request submitted for ' . $employee['name'] . ' by ' . $requesterLabel . '.',
                $ftwId
            );

            Audit::log($user, 'ftw.request', 'ftw', $ftwId, 'for ' . $employeeId . ' | ' . $diagnosis);

            return $ftwId;
        });
    }

    /**
     * Screen B2 — record the medical outcome of an assessment.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $record
     * @param array<string,mixed> $input  restriction_details, restriction_review_date, diagnostic_details
     */
    public static function recordOutcome(array $user, array $record, string $outcome, array $input): void
    {
        if (!in_array($outcome, Domain::ftwStatuses(), true) || $outcome === Domain::FTW_SUBMITTED) {
            throw new ValidationException(I18n::t('err_generic'));
        }

        $ftwId    = (string) $record['id'];
        $employee = Users::find((string) $record['employee_id']);
        $actor    = Auth::actorLabel($user);

        // Fields set by the previous outcome are cleared unless this outcome
        // sets them again, so a record never shows stale restrictions.
        $fields = [
            'status'                  => $outcome,
            'restriction_details'     => null,
            'restriction_review_date' => null,
        ];
        $comment = '';

        if ($outcome === Domain::FTW_RESTRICT) {
            $details    = trim((string) ($input['restriction_details'] ?? ''));
            $reviewDate = trim((string) ($input['restriction_review_date'] ?? ''));

            if ($details === '' || $reviewDate === '') {
                throw new ValidationException(I18n::topt('toast_need_restriction'));
            }
            if (!Helpers::isValidDate($reviewDate)) {
                throw new ValidationException(I18n::t('err_date_invalid'));
            }

            $fields['restriction_details']     = $details;
            $fields['restriction_review_date'] = $reviewDate;
            $comment                           = $details . ' Review: ' . Helpers::fmtDate($reviewDate);
        } elseif ($outcome === Domain::FTW_DIAGTEST) {
            $details = trim((string) ($input['diagnostic_details'] ?? ''));

            if ($details === '') {
                throw new ValidationException(I18n::topt('toast_need_diagtest'));
            }

            $fields['diagnostic_details'] = $details;
            $fields['diagnostic_at']      = Helpers::now();
            $comment                      = $details;
        }

        Database::transaction(static function () use ($user, $record, $ftwId, $employee, $outcome, $fields, $comment, $actor): void {
            Ftw::update($ftwId, $fields);
            Ftw::addHistory($ftwId, $actor, $outcome, $comment);

            Notifications::push(
                [
                    (string) $record['employee_id'],
                    'role:manager:' . ($employee['manager_id'] ?? ''),
                    'role:' . Domain::ROLE_HR,
                    (string) ($record['requester_id'] ?? ''),
                ],
                'FTW outcome',
                ($employee['name'] ?? '') . ' — ' . $outcome . '.',
                $ftwId
            );

            Audit::log($user, 'ftw.outcome', 'ftw', $ftwId, $outcome . ($comment !== '' ? ' | ' . $comment : ''));
        });
    }
}
