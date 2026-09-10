<?php

declare(strict_types=1);

namespace App\Http;

use App\Csrf;
use App\Domain;
use App\Http\Controller\AccountController;
use App\Http\Controller\AdminController;
use App\Http\Controller\AuthController;
use App\Http\Controller\CaseController;
use App\Http\Controller\FileController;
use App\Http\Controller\FtwController;
use App\Http\Controller\MedicalController;
use App\Http\Controller\NotificationController;
use App\Http\Controller\ReportController;
use App\I18n;
use App\Schema;
use App\Service\Auth;
use App\Session;
use App\ValidationException;

/**
 * Front controller dispatch.
 *
 * Access control happens here, in one place, before any handler runs:
 *   1. the portal in the URL must exist
 *   2. every POST must carry a valid CSRF token
 *   3. a route that needs a session must have one for that portal
 *   4. the signed-in role must be allowed the route it asked for
 *
 * A handler is therefore never reached by a caller who should not be there,
 * which is the part the client-side prototypes could not enforce at all.
 */
final class Router
{
    /**
     * Routes that require a signed-in session, mapped to the roles allowed to
     * use them. An empty role list means "any signed-in role".
     *
     * @var array<string,array{0:array{0:class-string,1:string},1:list<string>}>
     */
    private const ROUTES = [
        // ---- shared ----
        'tab'                => [[self::class, 'tab'], []],
        'logout'             => [[AuthController::class, 'logout'], []],
        'account'            => [[AccountController::class, 'form'], []],
        'account.password'   => [[AccountController::class, 'changePassword'], []],
        'notifications'      => [[NotificationController::class, 'index'], []],
        'notifications.read' => [[NotificationController::class, 'markRead'], []],

        // ---- sick leave cases ----
        'case'               => [[CaseController::class, 'show'], []],
        'case.submit'        => [[CaseController::class, 'submit'], [Domain::ROLE_EMPLOYEE]],
        'case.resubmit'      => [[CaseController::class, 'resubmit'], [Domain::ROLE_EMPLOYEE]],
        'case.approve'       => [[CaseController::class, 'approve'], [Domain::ROLE_MEDICAL]],
        'case.pending'       => [[CaseController::class, 'pending'], [Domain::ROLE_MEDICAL]],
        'case.comment'       => [[CaseController::class, 'comment'], [Domain::ROLE_MEDICAL]],
        'case.reject'        => [[CaseController::class, 'reject'], [Domain::ROLE_MEDICAL]],
        'case.rtw'           => [[CaseController::class, 'recordRtw'], [Domain::ROLE_MEDICAL]],
        'case.print'         => [[CaseController::class, 'printable'], [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN]],

        // ---- fit to work ----
        'ftw'                => [[FtwController::class, 'show'], []],
        'ftw.request'        => [[FtwController::class, 'request'], []],
        'ftw.outcome'        => [[FtwController::class, 'recordOutcome'], [Domain::ROLE_MEDICAL]],

        // ---- medical extras ----
        'patient'            => [[MedicalController::class, 'patient'], [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN]],

        // ---- reports ----
        'report.csv'         => [[ReportController::class, 'csv'], [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN]],

        // ---- admin ----
        'admin.role'         => [[AdminController::class, 'changeRole'], [Domain::ROLE_ADMIN]],
        'admin.active'       => [[AdminController::class, 'setActive'], [Domain::ROLE_ADMIN]],
        'admin.user.create'  => [[AdminController::class, 'createUser'], [Domain::ROLE_ADMIN]],
        'admin.settings'     => [[AdminController::class, 'saveSettings'], [Domain::ROLE_ADMIN]],
        'admin.reset'        => [[AdminController::class, 'resetDemoData'], [Domain::ROLE_ADMIN]],

        // ---- downloads ----
        'download'           => [[FileController::class, 'download'], []],
    ];

    /** Routes that may only be reached with POST. */
    private const POST_ONLY = [
        'logout', 'account.password', 'notifications.read',
        'case.submit', 'case.resubmit', 'case.approve', 'case.pending',
        'case.comment', 'case.reject', 'case.rtw',
        'ftw.request', 'ftw.outcome',
        'admin.role', 'admin.active', 'admin.user.create', 'admin.settings', 'admin.reset',
    ];

    public static function dispatch(): never
    {
        Session::start();

        // A brand-new deployment gets a clear instruction, not a stack trace.
        if (!Schema::exists()) {
            Response::html(View::render('errors/not-installed'), 503);
        }

        // Both the portal and the route arrive in the body on a POST (every form
        // posts to the bare script URL) and in the query string on a GET.
        $portal = Request::isPost() ? Request::post('p', Request::query('p')) : Request::query('p');
        $route  = Request::isPost() ? Request::post('r', Request::query('r')) : Request::query('r');

        // No portal in the URL: the landing page.
        if (!Domain::isPortal($portal)) {
            Response::html(Layout::bare('portals', [
                'active_portals' => Auth::activePortals(),
                'timed_out'      => (bool) Session::pull('_timed_out', false),
            ]));
        }

        // Every state change is a POST with a token.
        if (Request::isPost() && !Csrf::check(Request::post(Csrf::FIELD))) {
            Session::flash(I18n::t('err_csrf'), 'error');
            Response::redirect(Url::portal($portal));
        }

        if (in_array($route, self::POST_ONLY, true) && !Request::isPost()) {
            Response::redirect(Url::portal($portal));
        }

        // ---- unauthenticated area ----
        $user = Auth::user($portal);

        if ($user === null) {
            if ($route === 'login' && Request::isPost()) {
                AuthController::login($portal);
            }

            Response::html(AuthController::loginPage($portal));
        }

        // Signed in but asked for the login route again: go to the dashboard.
        if ($route === 'login' || $route === '') {
            Response::redirect(Url::tab($portal, Tabs::defaultFor((string) $user['role'])));
        }

        // ---- authenticated area ----
        $definition = self::ROUTES[$route] ?? null;

        if ($definition === null) {
            Response::html(self::errorPage($user, I18n::t('err_not_found')), 404);
        }

        [[$class, $method], $roles] = $definition;

        if ($roles !== [] && !in_array((string) $user['role'], $roles, true)) {
            Response::html(self::errorPage($user, I18n::t('err_forbidden')), 403);
        }

        try {
            /** @var string $output */
            $output = $class === self::class
                ? self::tab($user)
                : $class::$method($user);
        } catch (ValidationException $e) {
            // A rule was broken: say so and return to where the user was.
            Session::flash($e->getMessage(), 'error');
            Response::redirect(Request::returnTo(Url::tab($portal, Tabs::defaultFor((string) $user['role']))));
        }

        Response::html($output);
    }

    /**
     * Render a role's tab.
     *
     * @param array<string,mixed> $user
     */
    private static function tab(array $user): string
    {
        $role = (string) $user['role'];
        $tab  = Request::query('t');

        if ($tab === '' || !Tabs::allowed($role, $tab)) {
            $tab = Tabs::defaultFor($role);
        }

        $handler = Tabs::handler($tab);
        if ($handler === null) {
            return self::errorPage($user, I18n::t('err_not_found'));
        }

        [$class, $method] = $handler;

        return $class::$method($user, $tab);
    }

    /**
     * Chrome-wrapped error page, so a forbidden or missing route still shows the
     * user their header, tabs and a way back.
     *
     * @param array<string,mixed> $user
     */
    public static function errorPage(array $user, string $message): string
    {
        return Layout::page($user, Tabs::defaultFor((string) $user['role']), 'errors/message', [
            'message' => $message,
        ]);
    }

    /** Exposed for the smoke tests: the full route table. @return list<string> */
    public static function routeNames(): array
    {
        return array_keys(self::ROUTES);
    }

    /** Whether a role may reach a route — used by the access-control tests. */
    public static function roleMayReach(string $role, string $route): bool
    {
        $definition = self::ROUTES[$route] ?? null;
        if ($definition === null) {
            return false;
        }

        [, $roles] = $definition;

        return $roles === [] || in_array($role, $roles, true);
    }
}
