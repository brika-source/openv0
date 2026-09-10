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
use App\I18n;
use App\Repo\Ftw;
use App\Repo\Users;
use App\Service\Access;
use App\Service\FtwService;
use App\Service\PatientStory;
use App\Service\Uploads;
use App\Session;

/**
 * Fit-to-work requests and assessments.
 */
final class FtwController
{
    /**
     * Screen B1 — the request form, as reached from the "Initiate FTW" tab by a
     * manager, HR or the medical team.
     *
     * @param array<string,mixed> $user
     */
    public static function initForm(array $user, string $tab): string
    {
        return Layout::page($user, $tab, 'ftw/request', [
            'candidates' => Access::ftwCandidates($user),
            'demands'    => Domain::jobDemands(),
            // Set when arriving from a patient profile's "Initiate FTW" button.
            'preselect'  => Request::query('emp'),
        ]);
    }

    /**
     * Role-aware assessment / detail screen.
     *
     * @param array<string,mixed> $user
     */
    public static function show(array $user): string
    {
        $record = self::findOrFail(Request::query('id'), $user);
        $role   = (string) $user['role'];

        if ($role === Domain::ROLE_MANAGER) {
            // Restriction and status only.
            return Layout::page($user, 'teamdash', 'manager/ftw-restriction', [
                'record'   => $record,
                'employee' => Users::find((string) $record['employee_id']),
            ]);
        }

        if ($role === Domain::ROLE_EMPLOYEE) {
            return Layout::page($user, 'myftw', 'employee/ftw-detail', [
                'record'   => $record,
                'employee' => $user,
            ]);
        }

        $canAssess = Access::canDecide($role)
            && in_array((string) $record['status'], [Domain::FTW_SUBMITTED, Domain::FTW_F2F], true);

        return Layout::page($user, $role === Domain::ROLE_MEDICAL ? 'ftwqueue' : Tabs::defaultFor($role), 'medical/ftw-assess', [
            'record'     => $record,
            'employee'   => Users::find((string) $record['employee_id']),
            'story'      => PatientStory::for((string) $record['employee_id']),
            'can_assess' => $canAssess,
            'outcome'    => Request::query('o'),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function request(array $user): never
    {
        $uploadErrors = [];
        $attachments  = Uploads::store('attachments', $uploadErrors);

        $ftwId = FtwService::request($user, [
            'employee_id'   => Request::post('employee_id'),
            'diagnosis'     => Request::post('diagnosis'),
            'med_history'   => Request::post('med_history'),
            'job_free_text' => Request::post('job_free_text'),
            'demands'       => Request::postArray('demands'),
            'attachments'   => $attachments,
        ]);

        foreach (array_unique($uploadErrors) as $error) {
            Session::flash($error, 'error');
        }

        $portal = (string) $user['portal'];

        // The medical team raising a request goes straight into the assessment,
        // which is what the prototype did.
        if ((string) $user['role'] === Domain::ROLE_MEDICAL) {
            Session::flash(I18n::topt('fast_assess_note'), 'success');
            Response::redirect(Url::to(['p' => $portal, 'r' => 'ftw', 'id' => $ftwId]));
        }

        Session::flash($ftwId . ' ' . I18n::topt('toast_ftw_routed'), 'success');

        Response::redirect(Url::tab(
            $portal,
            (string) $user['role'] === Domain::ROLE_EMPLOYEE ? 'myftw' : 'ftwinit'
        ));
    }

    /** @param array<string,mixed> $user */
    public static function recordOutcome(array $user): never
    {
        $record  = self::findOrFail(Request::post('id'), $user);
        $outcome = Request::post('outcome');

        FtwService::recordOutcome($user, $record, $outcome, [
            'restriction_details'     => Request::post('restriction_details'),
            'restriction_review_date' => Request::post('restriction_review_date'),
            'diagnostic_details'      => Request::post('diagnostic_details'),
        ]);

        Session::flash(
            I18n::topt('toast_ftw_recorded') . ' ' . I18n::ftwStatusLabel($outcome),
            'success'
        );

        Response::redirect(Url::tab((string) $user['portal'], 'ftwqueue'));
    }

    /**
     * @param  array<string,mixed> $user
     * @return array<string,mixed>
     */
    private static function findOrFail(string $ftwId, array $user): array
    {
        $record = $ftwId === '' ? null : Ftw::find($ftwId);

        if ($record === null) {
            Response::html(Router::errorPage($user, I18n::t('err_not_found')), 404);
        }

        if (!Access::canViewFtw($user, $record)) {
            Response::html(Router::errorPage($user, I18n::t('err_forbidden')), 403);
        }

        return $record;
    }
}
