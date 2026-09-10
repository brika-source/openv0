<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Layout;
use App\Http\Request;
use App\Http\Response;
use App\Http\Tabs;
use App\Http\Url;
use App\Repo\Notifications;

/**
 * The notification inbox.
 */
final class NotificationController
{
    /** @param array<string,mixed> $user */
    public static function index(array $user): string
    {
        return Layout::page($user, Tabs::defaultFor((string) $user['role']), 'notifications', [
            'notifications' => Notifications::forUser((string) $user['id'], (string) $user['role'], 200),
        ]);
    }

    /** @param array<string,mixed> $user */
    public static function markRead(array $user): never
    {
        Notifications::markAllRead((string) $user['id'], (string) $user['role']);

        Response::redirect(Request::returnTo(Url::to(['p' => $user['portal'], 'r' => 'notifications'])));
    }
}
