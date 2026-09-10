<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\I18n;
use App\Repo\Audit;
use App\Repo\Cases;
use App\Repo\Ftw;
use App\Service\Access;
use App\Service\Uploads;

/**
 * Attachment downloads.
 *
 * Uploaded files live outside the web root under opaque names, so this is the
 * only way to reach one — and it checks first that the caller's role may see
 * that particular employee's medical documents.
 */
final class FileController
{
    /** @param array<string,mixed> $user */
    public static function download(array $user): never
    {
        $type = Request::query('type');
        $id   = Request::queryInt('id');

        [$record, $ownerId] = match ($type) {
            'case' => self::caseDocument($id),
            'ftw'  => self::ftwAttachment($id),
            default => [null, ''],
        };

        if ($record === null) {
            Response::html(Router::errorPage($user, I18n::t('err_not_found')), 404);
        }

        if (!Access::canDownload($user, $ownerId)) {
            Response::html(Router::errorPage($user, I18n::t('err_forbidden')), 403);
        }

        $path = Uploads::pathOf($record['stored_name'] ?? null);

        // Seeded demo records carry metadata only — there is no file to send.
        if ($path === null) {
            Response::html(Router::errorPage($user, I18n::t('err_not_found')), 404);
        }

        Audit::log($user, 'file.download', $type, (string) $id, (string) $record['name']);

        Response::file(
            $path,
            (string) $record['name'],
            (string) ($record['mime'] ?: 'application/octet-stream')
        );
    }

    /** @return array{0:array<string,mixed>|null,1:string} */
    private static function caseDocument(int $id): array
    {
        $document = Cases::findDocument($id);
        if ($document === null) {
            return [null, ''];
        }

        $case = Cases::find((string) $document['case_id']);

        return [$document, $case === null ? '' : (string) $case['employee_id']];
    }

    /** @return array{0:array<string,mixed>|null,1:string} */
    private static function ftwAttachment(int $id): array
    {
        $attachment = Ftw::findAttachment($id);
        if ($attachment === null) {
            return [null, ''];
        }

        $record = Ftw::find((string) $attachment['ftw_id']);

        return [$attachment, $record === null ? '' : (string) $record['employee_id']];
    }
}
