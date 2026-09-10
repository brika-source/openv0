<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Tabs;
use App\Http\Url;
use App\Http\View;
use App\I18n;
use App\Repo\Cases;
use App\Repo\Users;
use App\Service\Access;
use App\Service\PatientStory;
use App\Service\SickLeave;
use App\Service\Uploads;
use App\Session;

/**
 * Sick leave cases: the detail screen and every decision that acts on one.
 *
 * The prototype rendered case detail in a JavaScript modal whose contents were
 * identical for every role. Here the detail screen is chosen by role, so a
 * manager physically cannot be served the diagnosis.
 */
final class CaseController
{
    /**
     * Role-aware case detail.
     *
     * @param array<string,mixed> $user
     */
    public static function show(array $user): string
    {
        $case = self::findOrFail(Request::query('id'), $user);
        $role = (string) $user['role'];

        // A manager sees restriction and status only — never clinical detail.
        if ($role === Domain::ROLE_MANAGER) {
            return Layout::page($user, 'teamdash', 'manager/case-restriction', [
                'case'     => $case,
                'employee' => Users::find((string) $case['employee_id']),
            ]);
        }

        if ($role === Domain::ROLE_EMPLOYEE) {
            return Layout::page($user, 'mycases', 'employee/case-detail', [
                'case'     => $case,
                'employee' => $user,
            ]);
        }

        // Medical, HR and Admin: the full record. Only Medical gets the
        // decision controls.
        $status      = (string) $case['status'];
        $canDecide   = Access::canDecide($role) && in_array($status, Domain::slaStatuses(), true);
        $canRecordRtw = Access::canDecide($role) && $status === Domain::STATUS_RTW_PENDING;

        return Layout::page($user, self::tabForCase($role, $status), 'medical/case-review', [
            'case'           => $case,
            'employee'       => Users::find((string) $case['employee_id']),
            'story'          => PatientStory::for((string) $case['employee_id']),
            'can_decide'     => $canDecide,
            'can_record_rtw' => $canRecordRtw,
            'decision'       => Request::query('d'),
        ]);
    }

    private static function tabForCase(string $role, string $status): string
    {
        if ($role !== Domain::ROLE_MEDICAL) {
            return Tabs::defaultFor($role);
        }

        return $status === Domain::STATUS_RTW_PENDING ? 'rtw' : 'review';
    }

    // -----------------------------------------------------------------
    // Employee actions
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $user */
    public static function submit(array $user): never
    {
        $uploadErrors = [];
        $documents    = Uploads::store('documents', $uploadErrors);

        $caseId = SickLeave::submit($user, [
            'diagnosis' => Request::post('diagnosis'),
            'from_date' => Request::post('from_date'),
            'to_date'   => Request::post('to_date'),
            'specialty' => Request::post('specialty'),
            'documents' => $documents,
        ]);

        self::flashUploadErrors($uploadErrors);
        Session::flash($caseId . ' ' . I18n::topt('toast_submitted_to_medical'), 'success');

        Response::redirect(Url::tab((string) $user['portal'], 'mycases'));
    }

    /** @param array<string,mixed> $user */
    public static function resubmit(array $user): never
    {
        $case = self::findOrFail(Request::post('id'), $user);

        $uploadErrors = [];
        $documents    = Uploads::store('documents', $uploadErrors);

        SickLeave::resubmit($user, $case, Request::post('comment'), $documents);

        self::flashUploadErrors($uploadErrors);
        Session::flash(I18n::topt('toast_resubmitted'), 'success');

        Response::redirect(Url::tab((string) $user['portal'], 'mycases'));
    }

    // -----------------------------------------------------------------
    // Medical decisions
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $user */
    public static function approve(array $user): never
    {
        $case = self::findOrFail(Request::post('id'), $user);

        SickLeave::approve($user, $case, Request::post('rtw_required') === 'yes', Request::post('note'));

        Session::flash(I18n::topt('toast_case_approved'), 'success');
        Response::redirect(Url::tab((string) $user['portal'], 'review'));
    }

    /** @param array<string,mixed> $user */
    public static function pending(array $user): never
    {
        $case = self::findOrFail(Request::post('id'), $user);

        SickLeave::markPending($user, $case, Request::post('comment'));

        Session::flash(I18n::topt('toast_sent_pending'), 'success');
        Response::redirect(Url::tab((string) $user['portal'], 'review'));
    }

    /** @param array<string,mixed> $user */
    public static function comment(array $user): never
    {
        $case = self::findOrFail(Request::post('id'), $user);

        SickLeave::logComment($user, $case, Request::post('comment'));

        Session::flash(I18n::topt('toast_comment_logged'), 'success');
        Response::redirect(Url::tab((string) $user['portal'], 'review'));
    }

    /** @param array<string,mixed> $user */
    public static function reject(array $user): never
    {
        $case = self::findOrFail(Request::post('id'), $user);

        SickLeave::reject($user, $case, Request::post('comment'));

        Session::flash(I18n::topt('toast_case_rejected'), 'success');
        Response::redirect(Url::tab((string) $user['portal'], 'review'));
    }

    /** @param array<string,mixed> $user */
    public static function recordRtw(array $user): never
    {
        $case    = self::findOrFail(Request::post('id'), $user);
        $outcome = Request::post('outcome');

        $uploadErrors = [];
        $documents    = Uploads::store('documents', $uploadErrors);

        SickLeave::recordRtw($user, $case, $outcome, [
            'notes'                   => Request::post('notes'),
            'documents'               => $documents,
            'restriction_details'     => Request::post('restriction_details'),
            'restriction_review_date' => Request::post('restriction_review_date'),
            'extend_to'               => Request::post('extend_to'),
        ]);

        self::flashUploadErrors($uploadErrors);

        Session::flash(match ($outcome) {
            'fit'        => I18n::topt('toast_cleared_fit'),
            'restricted' => I18n::topt('toast_ftw_recorded') . ' ' . I18n::topt('btn_rtw_restricted'),
            default      => I18n::topt('toast_leave_extended'),
        }, 'success');

        Response::redirect(Url::tab((string) $user['portal'], 'rtw'));
    }

    // -----------------------------------------------------------------
    // Printable record
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $user */
    public static function printable(array $user): never
    {
        $case = self::findOrFail(Request::query('id'), $user);

        Response::printable(View::render('print/case', [
            'case'      => $case,
            'employee'  => Users::find((string) $case['employee_id']),
            'generator' => $user,
        ]));
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Load a case and stop the request unless this user may see it.
     *
     * @param  array<string,mixed> $user
     * @return array<string,mixed>
     */
    private static function findOrFail(string $caseId, array $user): array
    {
        $case = $caseId === '' ? null : Cases::find($caseId);

        if ($case === null) {
            Response::html(Router::errorPage($user, I18n::t('err_not_found')), 404);
        }

        if (!Access::canViewCase($user, $case)) {
            Response::html(Router::errorPage($user, I18n::t('err_forbidden')), 403);
        }

        return $case;
    }

    /** @param list<string> $errors */
    private static function flashUploadErrors(array $errors): void
    {
        foreach (array_unique($errors) as $error) {
            Session::flash($error, 'error');
        }
    }
}
