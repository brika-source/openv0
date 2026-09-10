<?php

declare(strict_types=1);

/**
 * Page sweep.
 *
 * Visits every screen as every role and asserts that each one returns 200,
 * renders the application shell, contains no PHP error text, and is
 * well-formed enough for a browser to parse (balanced div/table/form nesting).
 *
 * Complements tests/http.php, which drives the workflows; this one guarantees
 * that no screen can be reached in a state where it fails to render.
 *
 *   php -S 127.0.0.1:8111 -t public &
 *   php tests/sweep.php http://127.0.0.1:8111
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Database;
use App\Domain;
use App\Http\Tabs;
use App\Seeder;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8111', '/');

$passed   = 0;
$failed   = 0;
$failures = [];

$check = static function (bool $condition, string $description) use (&$passed, &$failed, &$failures): void {
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    $failures[] = $description;
    echo "  \033[31m✗ {$description}\033[0m\n";
};

/** Sign in and return a cookie jar path, or null. */
$signIn = static function (string $portal, string $userId) use ($baseUrl): ?string {
    $jar = sys_get_temp_dir() . '/slms-sweep-' . $userId . '-' . getmypid() . '.cookies';
    @unlink($jar);

    $loginPage = shell_exec(sprintf(
        'curl -sS -c %s -b %s %s 2>/dev/null',
        escapeshellarg($jar),
        escapeshellarg($jar),
        escapeshellarg($baseUrl . '/index.php?p=' . $portal)
    )) ?? '';

    if (preg_match('/name="_token" value="([a-f0-9]{64})"/', $loginPage, $m) !== 1) {
        return null;
    }

    shell_exec(sprintf(
        'curl -sS -L -c %s -b %s -d %s %s 2>/dev/null',
        escapeshellarg($jar),
        escapeshellarg($jar),
        escapeshellarg(http_build_query([
            '_token'     => $m[1],
            'p'          => $portal,
            'r'          => 'login',
            'identifier' => $userId,
            'password'   => Seeder::DEMO_PASSWORD,
        ])),
        escapeshellarg($baseUrl . '/index.php')
    ));

    return $jar;
};

/** @return array{status:int,body:string} */
$fetch = static function (string $jar, array $query) use ($baseUrl): array {
    $output = shell_exec(sprintf(
        'curl -sS -L -c %s -b %s -w "\n__STATUS__%%{http_code}" %s 2>/dev/null',
        escapeshellarg($jar),
        escapeshellarg($jar),
        escapeshellarg($baseUrl . '/index.php?' . http_build_query($query))
    )) ?? '';

    $marker = strrpos($output, "\n__STATUS__");
    $status = $marker === false ? 0 : (int) substr($output, $marker + 11);
    $body   = $marker === false ? $output : substr($output, 0, $marker);

    return ['status' => $status, 'body' => $body];
};

/** Count opening and closing tags of one element, ignoring self-closing forms. */
$balanced = static function (string $html, string $tag): bool {
    $open  = preg_match_all('/<' . $tag . '(\s[^>]*)?>/i', $html);
    $close = preg_match_all('#</' . $tag . '>#i', $html);

    return $open === $close;
};

$errorMarkers = [
    'Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:',
    'Uncaught', 'ErrorException', 'Undefined variable', 'Undefined array key',
    'Trying to access array offset', 'SQLSTATE',
];

echo "\033[1mPage sweep\033[0m\n";

$roles = [
    ['U1',  Domain::PORTAL_REQUESTER, Domain::ROLE_EMPLOYEE],
    ['U6',  Domain::PORTAL_REQUESTER, Domain::ROLE_MANAGER],
    ['U8',  Domain::PORTAL_APPROVER,  Domain::ROLE_MEDICAL],
    ['U10', Domain::PORTAL_APPROVER,  Domain::ROLE_HR],
    ['U11', Domain::PORTAL_APPROVER,  Domain::ROLE_ADMIN],
];

$logFile   = dirname(__DIR__) . '/storage/logs/php-error.log';
$logBefore = is_file($logFile) ? filesize($logFile) : 0;

foreach ($roles as [$userId, $portal, $role]) {
    echo "\n  \033[1m{$role}\033[0m ({$userId}, {$portal} portal)\n";

    $jar = $signIn($portal, $userId);
    $check($jar !== null, "{$role}: sign-in page yielded a token");
    if ($jar === null) {
        continue;
    }

    // ---- every tab this role has ----
    $pages = [];
    foreach (Tabs::forRole($role) as [$tab]) {
        $pages['tab:' . $tab] = ['p' => $portal, 'r' => 'tab', 't' => $tab];
    }

    // ---- the shared screens ----
    $pages['notifications'] = ['p' => $portal, 'r' => 'notifications'];
    $pages['account']       = ['p' => $portal, 'r' => 'account'];

    // ---- role-specific record screens ----
    if ($role === Domain::ROLE_EMPLOYEE) {
        $caseId = (string) Database::scalar(
            'SELECT case_id FROM sick_cases WHERE employee_id = :e ORDER BY case_id',
            ['e' => $userId]
        );
        $ftwId = (string) Database::scalar(
            'SELECT id FROM ftw_requests WHERE employee_id = :e ORDER BY id',
            ['e' => $userId]
        );
        if ($caseId !== '') {
            $pages['own case'] = ['p' => $portal, 'r' => 'case', 'id' => $caseId];
        }
        if ($ftwId !== '') {
            $pages['own ftw'] = ['p' => $portal, 'r' => 'ftw', 'id' => $ftwId];
        }
    }

    if ($role === Domain::ROLE_MANAGER) {
        $reportCase = (string) Database::scalar(
            'SELECT c.case_id FROM sick_cases c INNER JOIN users u ON u.id = c.employee_id
             WHERE u.manager_id = :m ORDER BY c.case_id',
            ['m' => $userId]
        );
        $reportFtw = (string) Database::scalar(
            'SELECT f.id FROM ftw_requests f INNER JOIN users u ON u.id = f.employee_id
             WHERE u.manager_id = :m ORDER BY f.id',
            ['m' => $userId]
        );
        if ($reportCase !== '') {
            $pages['report case'] = ['p' => $portal, 'r' => 'case', 'id' => $reportCase];
        }
        if ($reportFtw !== '') {
            $pages['report ftw'] = ['p' => $portal, 'r' => 'ftw', 'id' => $reportFtw];
        }
    }

    if (in_array($role, [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN], true)) {
        // One case in each status, so every branch of the detail template runs.
        foreach (Domain::statuses() as $status) {
            $caseId = (string) Database::scalar(
                'SELECT case_id FROM sick_cases WHERE status = :s ORDER BY case_id',
                ['s' => $status]
            );
            if ($caseId !== '') {
                $pages['case ' . $status] = ['p' => $portal, 'r' => 'case', 'id' => $caseId];
            }
        }

        // One FTW record in each status.
        foreach (Domain::ftwStatuses() as $status) {
            $ftwId = (string) Database::scalar(
                'SELECT id FROM ftw_requests WHERE status = :s ORDER BY id',
                ['s' => $status]
            );
            if ($ftwId !== '') {
                $pages['ftw ' . $status] = ['p' => $portal, 'r' => 'ftw', 'id' => $ftwId];
            }
        }

        $pages['patient profile'] = ['p' => $portal, 'r' => 'patient', 'id' => 'U5'];
        $pages['dashboard search'] = ['p' => $portal, 'r' => 'tab', 't' => 'mdash', 'q' => 'sam'];
    }

    if (in_array($role, [Domain::ROLE_MEDICAL, Domain::ROLE_HR, Domain::ROLE_ADMIN], true)) {
        $pages['reports filtered'] = [
            'p' => $portal, 'r' => 'tab', 't' => 'reports',
            'statuses' => [Domain::STATUS_CLOSED], 'specialties' => ['General'],
            'from' => '2000-01-01', 'to' => '2099-12-31',
        ];
    }

    foreach ($pages as $label => $query) {
        $response = $fetch($jar, $query);
        $body     = $response['body'];

        // A tab a role does not have falls back to its default tab, which is
        // still a valid 200 page; only a real failure is a failure here.
        $check($response['status'] === 200, "{$role}: {$label} returns 200 (got {$response['status']})");
        $check($body !== '', "{$role}: {$label} returns a body");

        foreach ($errorMarkers as $marker) {
            if (str_contains($body, $marker)) {
                $check(false, "{$role}: {$label} contains PHP error text \"{$marker}\"");
            }
        }

        $check(str_contains($body, '<nav class="tabs">'), "{$role}: {$label} renders the tab bar");
        $check(str_contains($body, '</html>'), "{$role}: {$label} is a complete document");
        $check($balanced($body, 'div'), "{$role}: {$label} has balanced <div> nesting");
        $check($balanced($body, 'table'), "{$role}: {$label} has balanced <table> nesting");
        $check($balanced($body, 'form'), "{$role}: {$label} has balanced <form> nesting");
        $check($balanced($body, 'dl'), "{$role}: {$label} has balanced <dl> nesting");
        $check($balanced($body, 'select'), "{$role}: {$label} has balanced <select> nesting");
    }

    echo "    " . count($pages) . " pages checked\n";
    @unlink($jar);
}

// The printable record has no app chrome, so it is checked on its own terms.
$jar = $signIn(Domain::PORTAL_APPROVER, 'U8');
if ($jar !== null) {
    $caseId = (string) Database::scalar('SELECT case_id FROM sick_cases ORDER BY case_id');
    $print  = $fetch($jar, ['p' => Domain::PORTAL_APPROVER, 'r' => 'case.print', 'id' => $caseId]);
    $check($print['status'] === 200, 'printable record returns 200');
    $check(str_contains($print['body'], '</html>'), 'printable record is a complete document');
    $check($balanced($print['body'], 'table'), 'printable record has balanced <table> nesting');
    foreach ($errorMarkers as $marker) {
        if (str_contains($print['body'], $marker)) {
            $check(false, "printable record contains PHP error text \"{$marker}\"");
        }
    }
    @unlink($jar);
}

// Nothing above may have written to the PHP error log.
clearstatcache();
$logAfter = is_file($logFile) ? filesize($logFile) : 0;
$check($logAfter === $logBefore, 'the sweep wrote nothing to storage/logs/php-error.log');

echo "\n" . str_repeat('─', 62) . "\n";
if ($failed === 0) {
    echo "\033[32m\033[1mAll {$passed} checks passed.\033[0m\n";
    exit(0);
}
echo "\033[31m\033[1m{$failed} of " . ($passed + $failed) . " checks failed.\033[0m\n";
exit(1);
