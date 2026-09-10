<?php

declare(strict_types=1);

/**
 * Command line tool.
 *
 *   php bin/console.php install     Create the schema and load demo data
 *   php bin/console.php migrate     Create any missing tables (keeps data)
 *   php bin/console.php seed        Load demo data into an empty schema
 *   php bin/console.php reset       Drop everything, recreate and reseed
 *   php bin/console.php status      Show the driver, table sizes and settings
 *   php bin/console.php passwd <email> <password>   Set a user's password
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

require __DIR__ . '/../app/bootstrap.php';

use App\Config;
use App\Database;
use App\Domain;
use App\Helpers;
use App\Schema;
use App\Seeder;

$command = $argv[1] ?? 'help';

// @-suppressed: piping this script into head/less closes stdout early, and a
// broken pipe is not an error worth reporting.
$out = static function (string $line = ''): void {
    @fwrite(STDOUT, $line . PHP_EOL);
};
$fail = static function (string $line): never {
    fwrite(STDERR, 'ERROR: ' . $line . PHP_EOL);
    exit(1);
};

try {
    switch ($command) {
        case 'install':
            $out('Driver: ' . Database::driver());
            if (Schema::exists() && (int) Database::scalar('SELECT COUNT(*) FROM users') > 0) {
                $out('Schema already installed and populated — nothing to do.');
                $out('Use "php bin/console.php reset" to wipe and reinstall.');
                break;
            }
            Schema::create();
            $out('Schema created.');
            Seeder::run();
            $out('Demo data loaded.');
            $out('');
            $out('Demo accounts (password for all: ' . Seeder::DEMO_PASSWORD . ')');
            foreach (Database::all('SELECT id, name, email, role FROM users ORDER BY LENGTH(id), id') as $u) {
                $out(sprintf('  %-4s %-18s %-28s %s', $u['id'], $u['name'], $u['email'], $u['role']));
            }
            break;

        case 'migrate':
            Schema::create();
            $out('Schema up to date.');
            break;

        case 'seed':
            if ((int) Database::scalar('SELECT COUNT(*) FROM users') > 0) {
                $fail('Users table is not empty. Use "reset" to wipe and reseed.');
            }
            Seeder::run();
            $out('Demo data loaded.');
            break;

        case 'reset':
            Schema::drop();
            $out('Tables dropped.');
            Schema::create();
            $out('Schema created.');
            Seeder::run();
            $out('Demo data loaded.');
            // Clear stored uploads so the filesystem matches the database.
            $uploadDir = (string) Config::get('uploads.path');
            foreach (glob($uploadDir . '/*') ?: [] as $file) {
                if (is_file($file) && basename($file) !== '.gitignore') {
                    @unlink($file);
                }
            }
            $out('Uploads cleared.');
            break;

        case 'status':
            $out('Driver:   ' . Database::driver());
            if (Database::driver() === 'sqlite') {
                $out('Database: ' . Config::get('db.sqlite.path'));
            }
            $out('Timezone: ' . date_default_timezone_get() . '  (now ' . Helpers::now() . ')');
            $out('');
            if (!Schema::exists()) {
                $out('Schema not installed. Run: php bin/console.php install');
                break;
            }
            $out('Table row counts:');
            foreach (array_reverse(Schema::tables()) as $table) {
                $count = (int) Database::scalar('SELECT COUNT(*) FROM ' . $table);
                $out(sprintf('  %-22s %d', $table, $count));
            }
            $out('');
            $out('Settings:');
            foreach (Database::all('SELECT setting_key, setting_value FROM settings ORDER BY setting_key') as $s) {
                $out(sprintf('  %-18s %s', $s['setting_key'], $s['setting_value']));
            }
            break;

        case 'passwd':
            $email    = $argv[2] ?? '';
            $password = $argv[3] ?? '';
            if ($email === '' || $password === '') {
                $fail('Usage: php bin/console.php passwd <email> <password>');
            }
            if (strlen($password) < 8) {
                $fail('Password must be at least 8 characters.');
            }
            $user = Database::one('SELECT id, name FROM users WHERE email = :e', ['e' => strtolower($email)]);
            if ($user === null) {
                $fail('No user with e-mail ' . $email);
            }
            Database::update(
                'users',
                ['password_hash' => password_hash($password, PASSWORD_DEFAULT)],
                'id',
                $user['id']
            );
            $out('Password updated for ' . $user['name'] . ' (' . $user['id'] . ').');
            break;

        case 'schema':
            $target = $argv[2] ?? Database::driver();
            if (!in_array($target, ['sqlite', 'mysql', 'pgsql'], true)) {
                $fail('Usage: php bin/console.php schema [sqlite|mysql|pgsql]');
            }
            $out('-- Corporate Sick Leave & Fit-to-Work Management System');
            $out('-- Reference schema for ' . $target . ', generated from app/Schema.php.');
            $out('-- Generated ' . date('Y-m-d') . '. Do not edit by hand: the application');
            $out('-- creates these tables itself via "php bin/console.php install".');
            $out('');
            foreach (Schema::statements($target) as $sql) {
                // The DDL is indented inside a PHP heredoc; re-indent the
                // continuation lines so the dumped file reads cleanly.
                $lines  = preg_split('/\R/', trim($sql)) ?: [];
                $body   = array_slice($lines, 1);
                $indent = null;
                foreach ($body as $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $lead   = strlen($line) - strlen(ltrim($line));
                    $indent = $indent === null ? $lead : min($indent, $lead);
                }
                $formatted = [$lines[0] ?? ''];
                foreach ($body as $line) {
                    $formatted[] = '    ' . substr($line, $indent ?? 0);
                }
                $out(implode(PHP_EOL, $formatted) . ';');
                $out('');
            }
            foreach (Schema::indexes() as $index) {
                $unique = $index['unique'] ? 'UNIQUE ' : '';
                $guard  = $target === 'mysql' ? '' : 'IF NOT EXISTS ';
                $out(sprintf(
                    'CREATE %sINDEX %s%s ON %s (%s);',
                    $unique,
                    $guard,
                    $index['name'],
                    $index['table'],
                    $index['columns']
                ));
            }
            break;

        case 'roles':
            $out('Roles: ' . implode(', ', Domain::roles()));
            $out('Requester portal: ' . implode(', ', Domain::rolesForPortal(Domain::PORTAL_REQUESTER)));
            $out('Approver portal:  ' . implode(', ', Domain::rolesForPortal(Domain::PORTAL_APPROVER)));
            break;

        default:
            $out('Corporate Sick Leave & Fit-to-Work Management System — console');
            $out('');
            $out('Usage: php bin/console.php <command>');
            $out('');
            $out('  install                    Create schema + load demo data (safe to re-run)');
            $out('  migrate                    Create any missing tables, keep existing data');
            $out('  seed                       Load demo data into an empty schema');
            $out('  reset                      Drop, recreate and reseed everything');
            $out('  status                     Show driver, row counts and settings');
            $out('  passwd <email> <password>  Set a user password');
            $out('  schema [driver]            Print the reference DDL for a driver');
            $out('  roles                      Show the role/portal matrix');
            break;
    }
} catch (Throwable $e) {
    $fail($e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine());
}
