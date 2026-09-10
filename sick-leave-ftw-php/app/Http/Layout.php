<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;
use App\Domain;
use App\I18n;
use App\Repo\Notifications;
use App\Session;

/**
 * Assembles the application chrome — header, tab bar, toasts, footer — around a
 * page template, so a controller only has to produce its own content.
 */
final class Layout
{
    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public static function page(array $user, string $activeTab, string $template, array $data = []): string
    {
        $portal = (string) $user['portal'];
        $role   = (string) $user['role'];

        $chrome = [
            'user'         => $user,
            'portal'       => $portal,
            'role'         => $role,
            'active_tab'   => $activeTab,
            'tabs'         => Tabs::forRole($role),
            'unread'       => Notifications::unreadCount((string) $user['id'], $role),
            'flashes'      => Session::takeFlashes(),
            'page_title'   => self::pageTitle($portal),
            'brand_title'  => I18n::t($portal === Domain::PORTAL_REQUESTER ? 'brand_title_requester' : 'brand_title_approver'),
            'brand_tag'    => I18n::t($portal === Domain::PORTAL_REQUESTER ? 'brand_tag_requester' : 'brand_tag_approver'),
            'app_version'  => (string) Config::get('app.version', '1.0.0'),
            'demo_mode'    => (bool) Config::get('app.demo_mode', true),
            'other_portal' => $portal === Domain::PORTAL_REQUESTER ? Domain::PORTAL_APPROVER : Domain::PORTAL_REQUESTER,
            'content_view' => $template,
        ];

        return View::render('layout', array_merge($chrome, $data, [
            'content' => View::render($template, array_merge($chrome, $data)),
        ]));
    }

    /** A bare page with no signed-in chrome: the login screen and the landing page. */
    public static function bare(string $template, array $data = []): string
    {
        return View::render($template, array_merge([
            'flashes'    => Session::takeFlashes(),
            'page_title' => self::pageTitle(
                Request::isPost() ? Request::post('p', Request::query('p')) : Request::query('p')
            ),
            'demo_mode'  => (bool) Config::get('app.demo_mode', true),
        ], $data));
    }

    public static function pageTitle(string $portal): string
    {
        if ($portal === Domain::PORTAL_REQUESTER) {
            return I18n::en('page_title_requester');
        }
        if ($portal === Domain::PORTAL_APPROVER) {
            return I18n::en('page_title_approver');
        }

        return (string) Config::get('app.name', 'Sick Leave & FTW System');
    }
}
