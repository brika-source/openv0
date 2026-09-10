<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Tabs;
use App\Http\Url;
use App\I18n;
use App\Service\Auth;
use App\Session;

/**
 * The signed-in user's own account: password changes.
 */
final class AccountController
{
    /** @param array<string,mixed> $user */
    public static function form(array $user): string
    {
        return Layout::page($user, Tabs::defaultFor((string) $user['role']), 'account', [
            'title' => I18n::t('title_change_password'),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function changePassword(array $user): never
    {
        $error = Auth::changePassword(
            $user,
            Request::postRaw('current_password'),
            Request::postRaw('new_password'),
            Request::postRaw('confirm_password')
        );

        if ($error !== null) {
            Session::flash($error, 'error');
        } else {
            Session::flash(I18n::topt('toast_password_changed'), 'success');
        }

        Response::redirect(Url::to(['p' => $user['portal'], 'r' => 'account']));
    }
}
