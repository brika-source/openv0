<?php

declare(strict_types=1);

namespace App\Service;

use App\Config;
use App\Helpers;
use App\I18n;
use RuntimeException;

/**
 * File upload handling.
 *
 * Files are written to storage/uploads, which sits outside the web root, under
 * a random opaque name. The original filename is kept only as metadata, and
 * downloads go through a controller that checks the caller's role first — so an
 * uploaded medical report is never reachable by guessing a URL.
 */
final class Uploads
{
    /**
     * Normalise PHP's $_FILES structure (which differs between a single input
     * and a `multiple` input) into a flat list of per-file arrays.
     *
     * @return array<int,array{name:string,tmp_name:string,size:int,error:int,type:string}>
     */
    public static function normalise(string $field): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [];
        }

        $raw   = $_FILES[$field];
        $files = [];

        if (is_array($raw['name'])) {
            $count = count($raw['name']);
            for ($i = 0; $i < $count; $i++) {
                $files[] = [
                    'name'     => (string) $raw['name'][$i],
                    'tmp_name' => (string) $raw['tmp_name'][$i],
                    'size'     => (int) $raw['size'][$i],
                    'error'    => (int) $raw['error'][$i],
                    'type'     => (string) ($raw['type'][$i] ?? ''),
                ];
            }
        } else {
            $files[] = [
                'name'     => (string) $raw['name'],
                'tmp_name' => (string) $raw['tmp_name'],
                'size'     => (int) $raw['size'],
                'error'    => (int) $raw['error'],
                'type'     => (string) ($raw['type'] ?? ''),
            ];
        }

        // Drop the empty slot a file input submits when nothing was chosen.
        return array_values(array_filter(
            $files,
            static fn (array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE && $f['name'] !== ''
        ));
    }

    /**
     * Validate and store every file in a field.
     *
     * @param  list<string> $errors  collects translated messages for rejected files
     * @return array<int,array{name:string,stored_name:string,size_bytes:int,mime:string}>
     */
    public static function store(string $field, array &$errors = []): array
    {
        $files = self::normalise($field);
        if ($files === []) {
            return [];
        }

        $maxFiles = (int) Config::get('uploads.max_files', 10);
        if (count($files) > $maxFiles) {
            $errors[] = I18n::t('err_upload_count');
            $files    = array_slice($files, 0, $maxFiles);
        }

        $maxBytes = (int) Config::get('uploads.max_bytes', 10485760);
        $allowExt = (array) Config::get('uploads.allowed_ext', []);
        $dir      = self::directory();
        $stored   = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                    ? I18n::t('err_upload_too_large')
                    : I18n::t('err_upload_failed');
                continue;
            }

            if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
                $errors[] = I18n::t('err_upload_too_large');
                continue;
            }

            // Reject anything not actually uploaded through this request.
            if (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = I18n::t('err_upload_failed');
                continue;
            }

            $original  = Helpers::safeFilename($file['name']);
            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

            if ($extension === '' || !in_array($extension, $allowExt, true)) {
                $errors[] = I18n::t('err_upload_type');
                continue;
            }

            // Trust the sniffed type, not the browser-supplied one.
            $mime = self::detectMime($file['tmp_name']) ?? 'application/octet-stream';
            if (!self::mimeAllowed($mime, $extension)) {
                $errors[] = I18n::t('err_upload_type');
                continue;
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $target     = $dir . '/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $errors[] = I18n::t('err_upload_failed');
                continue;
            }

            @chmod($target, 0640);

            $stored[] = [
                'name'        => $original,
                'stored_name' => $storedName,
                'size_bytes'  => $file['size'],
                'mime'        => $mime,
            ];
        }

        return $stored;
    }

    private static function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    /**
     * Office formats are frequently sniffed as generic ZIP or CFB containers,
     * and plain text as one of several text/* types, so those extensions are
     * accepted when the sniffed type is one of the known container types.
     */
    private static function mimeAllowed(string $mime, string $extension): bool
    {
        $allowed = (array) Config::get('uploads.allowed_mimes', []);
        if (in_array($mime, $allowed, true)) {
            return true;
        }

        $containerFallbacks = [
            'docx' => ['application/zip', 'application/octet-stream'],
            'xlsx' => ['application/zip', 'application/octet-stream'],
            'doc'  => ['application/x-ole-storage', 'application/vnd.ms-office', 'application/octet-stream'],
            'xls'  => ['application/x-ole-storage', 'application/vnd.ms-office', 'application/octet-stream'],
            'csv'  => ['text/plain', 'text/csv', 'application/csv'],
            'txt'  => ['text/plain'],
        ];

        return in_array($mime, $containerFallbacks[$extension] ?? [], true);
    }

    public static function directory(): string
    {
        $dir = (string) Config::get('uploads.path');

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload directory is not writable: ' . $dir);
        }

        return rtrim($dir, '/');
    }

    /** Absolute path of a stored file, or null when it is missing from disk. */
    public static function pathOf(?string $storedName): ?string
    {
        if ($storedName === null || $storedName === '') {
            return null;
        }

        // Defence in depth: a stored name is always a hex string plus extension.
        if (!preg_match('/^[a-f0-9]{32}\.[A-Za-z0-9]{1,10}$/', $storedName)) {
            return null;
        }

        $path = self::directory() . '/' . $storedName;

        return is_file($path) ? $path : null;
    }

    public static function delete(?string $storedName): void
    {
        $path = self::pathOf($storedName);
        if ($path !== null) {
            @unlink($path);
        }
    }

    /** Human-readable maximum file size, for the upload hint in the UI. */
    public static function maxSizeLabel(): string
    {
        return Helpers::humanBytes((int) Config::get('uploads.max_bytes', 10485760));
    }
}
