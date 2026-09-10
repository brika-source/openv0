<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain;
use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Tabs;
use App\Http\Url;
use App\Repo\Users;
use App\Seeder;
use App\Service\Auth;
use App\Session;

/**
 * Sign-in and sign-out.
 */
final class AuthController
{
    /** The login screen for a portal. */
    public static function loginPage(string $portal): string
    {
        return Layout::bare('login', [
            'portal'         => $portal,
            'eligible'       => Users::forPortal($portal),
            'brand_title'    => $portal === Domain::PORTAL_REQUESTER ? 'brand_title_requester' : 'brand_title_approver',
            'brand_tag'      => $portal === Domain::PORTAL_REQUESTER ? 'brand_tag_requester' : 'brand_tag_approver',
            'note_key'       => $portal === Domain::PORTAL_REQUESTER ? 'note_requester' : 'note_approver',
            'demo_password'  => Seeder::DEMO_PASSWORD,
            'timed_out'      => (bool) Session::pull('_timed_out', false),
            'old_identifier' => (string) (Session::pull('_login_identifier') ?? ''),
        ]);
    }

    /** Handle a sign-in attempt. */
    public static function login(string $portal): never
    {
        $identifier = Request::post('identifier');
        $password   = Request::postRaw('password');

        $error = Auth::attempt($portal, $identifier, $password);

        if ($error !== null) {
            Session::flash($error, 'error');
            // Keep the chosen account selected so only the password is retyped.
            Session::set('_login_identifier', $identifier);
            Response::redirect(Url::portal($portal));
        }

        $user = Auth::user($portal);
        $tab  = $user === null ? 'dash' : Tabs::defaultFor((string) $user['role']);

        Response::redirect(Url::tab($portal, $tab));
    }

    /** @param array<string,mixed> $user */
    public static function logout(array $user): never
    {
        $portal = (string) $user['portal'];
        Auth::logout($portal);

        Response::redirect(Url::portal($portal));
    }
}
