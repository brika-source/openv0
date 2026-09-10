<?php

declare(strict_types=1);

namespace App\Service;

use App\Database;
use App\Domain;
use App\Helpers;
use App\I18n;
use App\Repo\Audit;
use App\Repo\Cases;
use App\Repo\Notifications;
use App\Repo\Users;
use App\ValidationException;

/**
 * Sick leave workflow — Module A.
 *
 * Every transition is a single database transaction covering the case row, its
 * history entry, any documents and the notification fan-out, so a failure part
 * way through can never leave a case with a new status but no audit trail.
 *
 * Each method re-checks the case's current status before acting, which the
 * client-side prototype could not do: two reviewers opening the same case now
 * get a clear "already actioned" error instead of silently overwriting one
 * another's decision.
 */
final class SickLeave
{
    // -----------------------------------------------------------------
    // Employee: submission and resubmission
    // -----------------------------------------------------------------

    /**
     * Screen A2 — an employee submits a sick leave request for themselves.
     *
     * @param  array<string,mixed> $user
     * @param  array<string,mixed> $input
     * @return string the new case id
     */
    public static function submit(array $user, array $input): string
    {
        $diagnosis = trim((string) ($input['diagnosis'] ?? ''));
        $from      = trim((string) ($input['from_date'] ?? ''));
        $to        = trim((string) ($input['to_date'] ?? ''));
        $specialty = (string) ($input['specialty'] ?? '');

        if ($diagnosis === '' || $from === '' || $to === '') {
            throw new ValidationException(I18n::topt('toast_need_diag_dates'));
        }
        if (!Helpers::isValidDate($from) || !Helpers::isValidDate($to)) {
            throw new ValidationException(I18n::t('err_date_invalid'));
        }
        if ($to < $from) {
            throw new ValidationException(I18n::t('err_date_order'));
        }
        // A year out is generous for a sick note and still catches typos
        // such as 2206 instead of 2026.
        if ($from > Helpers::dayOffset(365) || $to > Helpers::dayOffset(365)) {
            throw new ValidationException(I18n::t('err_date_far_future'));
        }
        if (!in_array($specialty, Domain::specialties(), true)) {
            $specialty = 'General';
        }

        $documents = (array) ($input['documents'] ?? []);

        return Database::transaction(static function () use ($user, $diagnosis, $specialty, $from, $to, $documents): string {
            $caseId = Cases::create([
                'employee_id' => $user['id'],
                'diagnosis'   => $diagnosis,
                'specialty'   => $specialty,
                'from_date'   => $from,
                'to_date'     => $to,
            ]);

            Cases::addHistory($caseId, Auth::actorLabel($user), 'Submitted', '');

            foreach ($documents as $document) {
                Cases::addDocument($caseId, $document + ['uploaded_by' => $user['id']]);
            }

            Notifications::push(
                ['role:' . Domain::ROLE_MEDICAL],
                'Sick leave submitted',
                $user['name'] . ' submitted a new sick leave request (' . $caseId . ').',
                $caseId
            );

            Audit::log($user, 'case.submit', 'case', $caseId, $diagnosis . ' | ' . $from . ' to ' . $to);

            return $caseId;
        });
    }

    /**
     * The employee answers a "Pending – Info Requested" case, which sends it
     * back into medical review.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     * @param array<int,array<string,mixed>> $documents
     */
    public static function resubmit(array $user, array $case, string $comment, array $documents = []): void
    {
        if ((string) $case['employee_id'] !== (string) $user['id']) {
            throw new ValidationException(I18n::t('err_forbidden'));
        }
        if ((string) $case['status'] !== Domain::STATUS_PENDING) {
            throw new ValidationException(I18n::t('err_case_not_open'));
        }

        $caseId = (string) $case['case_id'];

        Database::transaction(static function () use ($user, $caseId, $comment, $documents): void {
            foreach ($documents as $document) {
                Cases::addDocument($caseId, $document + ['uploaded_by' => $user['id']]);
            }

            Cases::update($caseId, ['status' => Domain::STATUS_REVIEW]);
            Cases::addHistory($caseId, Auth::actorLabel($user), 'Resubmitted', $comment);

            Notifications::push(
                ['role:' . Domain::ROLE_MEDICAL],
                'Case resubmitted',
                $user['name'] . ' resubmitted ' . $caseId . ' with additional information.',
                $caseId
            );

            Audit::log($user, 'case.resubmit', 'case', $caseId, $comment);
        });
    }

    // -----------------------------------------------------------------
    // Medical: review decisions (Screen A3)
    // -----------------------------------------------------------------

    /**
     * Approve a case. When a return-to-work clearance is required the case moves
     * to "RTW Pending"; when it is not, the case closes on its To-date and is
     * marked closed immediately, exactly as in the prototype.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     */
    public static function approve(array $user, array $case, bool $rtwRequired, string $note): void
    {
        self::assertReviewable($case);

        $caseId   = (string) $case['case_id'];
        $employee = Users::find((string) $case['employee_id']);

        Database::transaction(static function () use ($user, $case, $caseId, $employee, $rtwRequired, $note): void {
            $comment = ($rtwRequired ? 'RTW assessment required. ' : 'RTW not required — auto-return. ') . $note;

            Cases::update($caseId, [
                'rtw_required' => $rtwRequired ? 1 : 0,
                // No clearance needed means the case is finished here.
                'status'       => $rtwRequired ? Domain::STATUS_RTW_PENDING : Domain::STATUS_CLOSED,
            ]);
            Cases::addHistory($caseId, Auth::actorLabel($user), 'Approved', $comment);

            Notifications::push(
                [(string) $case['employee_id'], 'role:manager:' . ($employee['manager_id'] ?? '')],
                'Decision: Approved',
                ($employee['name'] ?? '') . ' — sick leave approved'
                . ($rtwRequired ? ' (RTW clearance required before return)' : '') . '.',
                $caseId
            );

            Audit::log($user, 'case.approve', 'case', $caseId, 'rtw_required=' . ($rtwRequired ? 'yes' : 'no') . ' | ' . $note);
        });
    }

    /**
     * Send the case back to the employee asking for more information.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     */
    public static function markPending(array $user, array $case, string $comment): void
    {
        self::assertReviewable($case);

        if (trim($comment) === '') {
            throw new ValidationException(I18n::topt('toast_need_employee_comment'));
        }

        $caseId = (string) $case['case_id'];

        Database::transaction(static function () use ($user, $case, $caseId, $comment): void {
            Cases::update($caseId, ['status' => Domain::STATUS_PENDING]);
            Cases::addHistory($caseId, Auth::actorLabel($user), 'Pending', $comment);

            Notifications::push(
                [(string) $case['employee_id']],
                'Decision: Pending',
                'Your case ' . $caseId . ' is pending — ' . $comment,
                $caseId
            );

            Audit::log($user, 'case.pending', 'case', $caseId, $comment);
        });
    }

    /**
     * Log a request for clarification. Auditable, and not an approval.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     */
    public static function logComment(array $user, array $case, string $comment): void
    {
        self::assertReviewable($case);

        if (trim($comment) === '') {
            throw new ValidationException(I18n::topt('toast_need_comment'));
        }

        $caseId = (string) $case['case_id'];

        Database::transaction(static function () use ($user, $case, $caseId, $comment): void {
            Cases::update($caseId, ['status' => Domain::STATUS_PENDING]);
            Cases::addHistory($caseId, Auth::actorLabel($user), 'Comment / Request More Info', $comment);

            Notifications::push(
                [(string) $case['employee_id']],
                'Decision: Comment',
                'Clarification requested on ' . $caseId . ' — ' . $comment,
                $caseId
            );

            Audit::log($user, 'case.comment', 'case', $caseId, $comment);
        });
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     */
    public static function reject(array $user, array $case, string $comment): void
    {
        self::assertReviewable($case);

        if (trim($comment) === '') {
            throw new ValidationException(I18n::topt('toast_need_reject_reason'));
        }

        $caseId   = (string) $case['case_id'];
        $employee = Users::find((string) $case['employee_id']);

        Database::transaction(static function () use ($user, $case, $caseId, $employee, $comment): void {
            Cases::update($caseId, ['status' => Domain::STATUS_REJECTED]);
            Cases::addHistory($caseId, Auth::actorLabel($user), 'Rejected', $comment);

            Notifications::push(
                [(string) $case['employee_id'], 'role:manager:' . ($employee['manager_id'] ?? '')],
                'Decision: Rejected',
                ($employee['name'] ?? '') . ' — sick leave request rejected. ' . $comment,
                $caseId
            );

            Audit::log($user, 'case.reject', 'case', $caseId, $comment);
        });
    }

    // -----------------------------------------------------------------
    // Medical: return-to-work assessment (Screen A4)
    // -----------------------------------------------------------------

    /**
     * Record an RTW outcome.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $case
     * @param array<string,mixed> $input   notes, documents, and the fields the
     *                                     chosen outcome needs
     */
    public static function recordRtw(array $user, array $case, string $outcome, array $input): void
    {
        if ((string) $case['status'] !== Domain::STATUS_RTW_PENDING) {
            throw new ValidationException(I18n::t('err_case_not_open'));
        }

        $caseId    = (string) $case['case_id'];
        $employee  = Users::find((string) $case['employee_id']);
        $notes     = trim((string) ($input['notes'] ?? ''));
        $documents = (array) ($input['documents'] ?? []);
        $actor     = Auth::actorLabel($user);

        switch ($outcome) {
            case 'fit':
                Database::transaction(static function () use ($user, $case, $caseId, $employee, $notes, $documents, $actor): void {
                    self::attachRtwDocuments($caseId, $documents, (string) $user['id']);

                    Cases::update($caseId, [
                        'status'      => Domain::STATUS_CLOSED,
                        'rtw_outcome' => 'Fit to Return',
                        'rtw_notes'   => $notes,
                        'rtw_by'      => (string) $user['name'],
                        'rtw_at'      => Helpers::now(),
                    ]);
                    Cases::addHistory($caseId, $actor, 'RTW: Fit to Return', $notes);

                    Notifications::push(
                        [(string) $case['employee_id'], 'role:manager:' . ($employee['manager_id'] ?? '')],
                        'RTW outcome',
                        ($employee['name'] ?? '') . ' assessed Fit to Return — case ' . $caseId . ' closed.',
                        $caseId
                    );

                    Audit::log($user, 'case.rtw.fit', 'case', $caseId, $notes);
                });
                break;

            case 'restricted':
                $details    = trim((string) ($input['restriction_details'] ?? ''));
                $reviewDate = trim((string) ($input['restriction_review_date'] ?? ''));

                if ($details === '' || $reviewDate === '') {
                    throw new ValidationException(I18n::topt('toast_need_restriction'));
                }
                if (!Helpers::isValidDate($reviewDate)) {
                    throw new ValidationException(I18n::t('err_date_invalid'));
                }

                Database::transaction(static function () use ($user, $case, $caseId, $employee, $notes, $documents, $actor, $details, $reviewDate): void {
                    self::attachRtwDocuments($caseId, $documents, (string) $user['id']);

                    Cases::update($caseId, [
                        'status'                  => Domain::STATUS_RETURNED_RESTRICTED,
                        'restriction_details'     => $details,
                        'restriction_review_date' => $reviewDate,
                        'rtw_outcome'             => Domain::STATUS_RETURNED_RESTRICTED,
                        'rtw_notes'               => $notes,
                        'rtw_by'                  => (string) $user['name'],
                        'rtw_at'                  => Helpers::now(),
                    ]);
                    Cases::addHistory(
                        $caseId,
                        $actor,
                        'RTW: Returned With Restrictions',
                        $details . ' Review: ' . Helpers::fmtDate($reviewDate) . '. ' . $notes
                    );

                    Notifications::push(
                        [(string) $case['employee_id'], 'role:manager:' . ($employee['manager_id'] ?? '')],
                        'RTW outcome',
                        ($employee['name'] ?? '') . ' returned to work with restrictions — case ' . $caseId . '.',
                        $caseId
                    );

                    Audit::log($user, 'case.rtw.restricted', 'case', $caseId, $details . ' | review ' . $reviewDate);
                });
                break;

            case 'notfit':
                $newTo = trim((string) ($input['extend_to'] ?? ''));

                if ($newTo === '') {
                    throw new ValidationException(I18n::topt('toast_need_ext_date'));
                }
                if (!Helpers::isValidDate($newTo)) {
                    throw new ValidationException(I18n::t('err_date_invalid'));
                }
                if ($newTo <= (string) $case['to_date']) {
                    throw new ValidationException(I18n::t('err_date_order'));
                }
                if ($newTo > Helpers::dayOffset(365)) {
                    throw new ValidationException(I18n::t('err_date_far_future'));
                }

                Database::transaction(static function () use ($user, $case, $caseId, $employee, $notes, $documents, $actor, $newTo): void {
                    self::attachRtwDocuments($caseId, $documents, (string) $user['id']);

                    // The extension keeps the same case id, so the record stays
                    // one continuous absence rather than two.
                    Cases::addExtension($caseId, (string) $case['to_date'], $newTo);
                    Cases::update($caseId, [
                        'to_date'     => $newTo,
                        'status'      => Domain::STATUS_RTW_PENDING,
                        'rtw_outcome' => null,
                        'rtw_notes'   => null,
                        'rtw_by'      => null,
                        'rtw_at'      => null,
                    ]);
                    Cases::addHistory(
                        $caseId,
                        $actor,
                        'RTW: Not Fit — Extended',
                        $notes . ' New return date: ' . Helpers::fmtDate($newTo)
                    );

                    Notifications::push(
                        [(string) $case['employee_id'], 'role:manager:' . ($employee['manager_id'] ?? '')],
                        'Sick leave extended',
                        ($employee['name'] ?? '') . "'s leave extended to " . Helpers::fmtDate($newTo)
                        . ' (same case ' . $caseId . ').',
                        $caseId
                    );

                    Audit::log($user, 'case.rtw.extended', 'case', $caseId, 'new to_date=' . $newTo . ' | ' . $notes);
                });
                break;

            default:
                throw new ValidationException(I18n::t('err_generic'));
        }
    }

    /** @param array<int,array<string,mixed>> $documents */
    private static function attachRtwDocuments(string $caseId, array $documents, string $uploaderId): void
    {
        foreach ($documents as $document) {
            Cases::addDocument($caseId, $document + ['uploaded_by' => $uploaderId]);
        }
    }

    /** A case can only take a review decision while it is still awaiting one. */
    private static function assertReviewable(array $case): void
    {
        if (!in_array((string) $case['status'], Domain::slaStatuses(), true)) {
            throw new ValidationException(I18n::t('err_already_actioned'));
        }
    }
}
