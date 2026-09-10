<?php

declare(strict_types=1);

/**
 * Security tests.
 *
 * Pushes hostile input through the real HTTP stack and asserts that it is
 * stored intact but rendered inert, that SQL metacharacters never reach the
 * query planner, that CSRF is enforced, that session fixation is defeated, and
 * that the security headers are present.
 *
 *   php -S 127.0.0.1:8111 -t public &
 *   php tests/security.php http://127.0.0.1:8111
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Database;
use App\Seeder;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8111', '/');

$passed = 0;
$failed = 0;
$failures = [];

function section(string $name): void
{
    echo "\n\033[1m── {$name}\033[0m\n";
}

function ok(bool $condition, string $description): void
{
    global $passed, $failed, $failures;
    if ($condition) {
        $passed++;
        echo "  \033[32m✓\033[0m {$description}\n";
        return;
    }
    $failed++;
    $failures[] = $description;
    echo "  \033[31m✗ {$description}\033[0m\n";
}

/** Minimal cookie-jar browser. */
final class Client
{
    public string $body = '';
    public string $headers = '';
    public int $status = 0;
    private string $jar;

    public function __construct(private string $baseUrl, string $name)
    {
        $this->jar = sys_get_temp_dir() . '/slms-sec-' . $name . '-' . getmypid() . '.cookies';
        @unlink($this->jar);
    }

    public function get(array $query = []): string
    {
        return $this->run(['-L'], $this->baseUrl . '/index.php?' . http_build_query($query));
    }

    public function head(array $query = []): string
    {
        $this->run(['-D', '-', '-o', '/dev/null'], $this->baseUrl . '/index.php?' . http_build_query($query));
        return $this->body;
    }

    public function post(array $fields, bool $follow = true): string
    {
        $body = [];
        foreach ($fields as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $body[] = urlencode((string) $key) . '=' . urlencode((string) $item);
            }
        }

        $flags = ['-d', implode('&', $body)];
        if ($follow) {
            $flags[] = '-L';
        }

        return $this->run($flags, $this->baseUrl . '/index.php');
    }

    private function run(array $flags, string $url): string
    {
        $command = array_merge(
            ['curl', '-sS', '-w', "\n__STATUS__%{http_code}", '-c', $this->jar, '-b', $this->jar],
            $flags,
            [$url]
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open($command, $descriptors, $pipes);
        $output      = is_resource($process) ? (string) stream_get_contents($pipes[1]) : '';

        if (is_resource($process)) {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        $marker = strrpos($output, "\n__STATUS__");
        if ($marker !== false) {
            $this->status = (int) substr($output, $marker + 11);
            $output       = substr($output, 0, $marker);
        }

        return $this->body = $output;
    }

    public function token(): string
    {
        return preg_match('/name="_token" value="([a-f0-9]{64})"/', $this->body, $m) === 1 ? $m[1] : '';
    }

    public function sessionId(): string
    {
        $contents = @file_get_contents($this->jar) ?: '';
        return preg_match('/SLMSSESSID\s+(\S+)/', $contents, $m) === 1 ? $m[1] : '';
    }

    public function login(string $portal, string $identifier, string $password = Seeder::DEMO_PASSWORD): bool
    {
        $this->get(['p' => $portal]);
        $this->post([
            '_token'     => $this->token(),
            'p'          => $portal,
            'r'          => 'login',
            'identifier' => $identifier,
            'password'   => $password,
        ]);
        return str_contains($this->body, '<nav class="tabs">');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }
}

// =====================================================================
section('Security headers');
// =====================================================================
$probe   = new Client($baseUrl, 'probe');
$headers = $probe->head(['p' => 'requester']);

foreach ([
    'Content-Security-Policy'          => 'a Content-Security-Policy',
    'X-Content-Type-Options: nosniff'  => 'X-Content-Type-Options: nosniff',
    'X-Frame-Options: DENY'            => 'X-Frame-Options: DENY',
    'Referrer-Policy: same-origin'     => 'Referrer-Policy: same-origin',
    'Cache-Control: no-store'          => 'a no-store Cache-Control',
] as $needle => $label) {
    ok(str_contains($headers, $needle), "Responses carry {$label}");
}
ok(str_contains($headers, "script-src 'self'"), "The CSP restricts scripts to 'self'");
ok(str_contains($headers, "frame-ancestors 'none'"), 'The CSP forbids framing');
ok(str_contains($headers, 'HttpOnly'), 'The session cookie is HttpOnly');
ok(str_contains($headers, 'SameSite=Lax'), 'The session cookie is SameSite=Lax');

// =====================================================================
section('Cross-site scripting');
// =====================================================================
$employee = new Client($baseUrl, 'xss-employee');
ok($employee->login('requester', 'U1'), 'Employee signed in for the XSS test');

$payloads = [
    '<script>window.__pwned=1</script>',
    '"><img src=x onerror=alert(1)>',
    "'\"><svg/onload=alert(1)>",
    '<iframe src="javascript:alert(1)">',
    'javascript:alert(document.cookie)',
];

foreach ($payloads as $index => $payload) {
    $employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'submit']);
    $employee->post([
        '_token'    => $employee->token(),
        'p'         => 'requester',
        'r'         => 'case.submit',
        'diagnosis' => 'XSS probe ' . $index . ' ' . $payload,
        'from_date' => date('Y-m-d'),
        'to_date'   => date('Y-m-d'),
        'specialty' => 'General',
    ]);

    $caseId = (string) Database::scalar(
        'SELECT case_id FROM sick_cases WHERE diagnosis LIKE :d ORDER BY case_id DESC',
        ['d' => 'XSS probe ' . $index . '%']
    );
    ok($caseId !== '', "Payload {$index} was accepted and stored");

    // Stored verbatim: the application escapes on output, it does not mangle input.
    $stored = (string) Database::scalar(
        'SELECT diagnosis FROM sick_cases WHERE case_id = :c',
        ['c' => $caseId]
    );
    ok(str_contains($stored, $payload), "Payload {$index} is stored verbatim, not stripped");

    // Rendered inert everywhere it appears. The meaningful property is that the
    // payload survives only in escaped form: an escaped payload still contains
    // the substring "onerror=alert" as visible text, which is harmless, so the
    // assertions below test for executable markup rather than for substrings.
    $body = $employee->get(['p' => 'requester', 'r' => 'case', 'id' => $caseId]);

    ok(
        str_contains($body, htmlspecialchars($payload, ENT_QUOTES, 'UTF-8')),
        "Payload {$index} appears HTML-escaped"
    );

    // Any payload carrying HTML metacharacters must not appear verbatim.
    if ($payload !== strip_tags($payload) || str_contains($payload, '"') || str_contains($payload, '<')) {
        ok(!str_contains($body, $payload), "Payload {$index} is never echoed raw");
    }

    // No new executable node or handler may have entered the document.
    ok(!str_contains($body, '<script>window.__pwned'), "Payload {$index} injects no script element");
    ok(preg_match('/<img[^>]*onerror/i', $body) === 0, "Payload {$index} injects no img/onerror element");
    ok(preg_match('/<svg[^>]*onload/i', $body) === 0, "Payload {$index} injects no svg/onload element");
    ok(preg_match('/<iframe/i', $body) === 0, "Payload {$index} injects no iframe");
    // A javascript: URL must never end up in an href or src attribute.
    ok(preg_match('/(href|src)\s*=\s*["\']?\s*javascript:/i', $body) === 0, "Payload {$index} creates no javascript: URL");
}

// The same payloads through the medical queue and report screens.
$medical = new Client($baseUrl, 'xss-medical');
ok($medical->login('approver', 'U8'), 'Medical signed in for the XSS test');

foreach (['review', 'reports', 'mdash'] as $tab) {
    $body = $medical->get(['p' => 'approver', 'r' => 'tab', 't' => $tab]);
    ok(!str_contains($body, '<script>window.__pwned'), "The {$tab} screen renders no injected script");
    ok(preg_match('/<img[^>]*onerror/i', $body) === 0, "The {$tab} screen renders no injected img/onerror");
    ok(preg_match('/<svg[^>]*onload/i', $body) === 0, "The {$tab} screen renders no injected svg/onload");
    ok(preg_match('/<iframe/i', $body) === 0, "The {$tab} screen renders no injected iframe");
}

// And through the CSV export, where a formula injection would matter.
$csv = $medical->get(['p' => 'approver', 'r' => 'report.csv']);
ok(str_contains($csv, 'Case ID'), 'CSV export still renders with hostile data present');
ok(!str_contains($csv, "\n<script"), 'CSV export contains no raw script tag at line start');

// =====================================================================
section('SQL injection');
// =====================================================================
$injections = [
    "' OR '1'='1",
    "'; DROP TABLE sick_cases; --",
    "1' UNION SELECT password_hash FROM users --",
    "\\'; DELETE FROM users WHERE '1'='1",
    '%',
    '_',
];

$casesBefore = (int) Database::scalar('SELECT COUNT(*) FROM sick_cases');
$usersBefore = (int) Database::scalar('SELECT COUNT(*) FROM users');

foreach ($injections as $index => $injection) {
    // Through the patient search.
    $medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash', 'q' => $injection]);
    ok($medical->status === 200, "Search injection {$index} is handled without an error");

    // Through the report filters.
    $medical->get([
        'p' => 'approver', 'r' => 'tab', 't' => 'reports',
        'statuses' => [$injection], 'employees' => [$injection], 'from' => $injection,
    ]);
    ok($medical->status === 200, "Report filter injection {$index} is handled without an error");

    // Through a record id.
    $medical->get(['p' => 'approver', 'r' => 'case', 'id' => $injection]);
    ok(in_array($medical->status, [403, 404], true), "Case id injection {$index} yields a clean 403/404");

    // Through the login identifier.
    $attacker = new Client($baseUrl, 'sqli' . $index);
    ok(!$attacker->login('approver', $injection, 'anything'), "Login injection {$index} does not authenticate");
}

ok(
    (int) Database::scalar('SELECT COUNT(*) FROM sick_cases') === $casesBefore,
    'No case row was destroyed by the injection attempts'
);
ok(
    (int) Database::scalar('SELECT COUNT(*) FROM users') === $usersBefore,
    'No user row was destroyed by the injection attempts'
);
ok(
    (int) Database::scalar("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='sick_cases'") === 1,
    'The sick_cases table still exists'
);

// A wildcard search must be treated literally, not as "match everything".
// The dashboard also lists employee names in its activity feed, so the check is
// scoped to the search-results region.
$searchRegion = static function (string $html): string {
    // The results list carries id="searchResults"; the next card after it opens
    // the activity feed, which also lists employee names.
    $start = strpos($html, 'id="searchResults"');
    if ($start === false) {
        // No results block at all means no matches, which is what "empty" means.
        return '';
    }
    $end = strpos($html, '<div class="card">', $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
};

$wildcard = $searchRegion($medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash', 'q' => '%']));
ok(!str_contains($wildcard, 'Rania Samir'), 'A "%" search matches nobody (treated as a literal)');
ok(!str_contains($wildcard, 'Mona Fathy'), 'A "%" search does not match every employee');

$underscore = $searchRegion($medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash', 'q' => '_']));
ok(!str_contains($underscore, 'Rania Samir'), 'An "_" search matches nobody (treated as a literal)');

// A real search still works, proving the two checks above failed for the right reason.
$real = $searchRegion($medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash', 'q' => 'rania']));
ok(str_contains($real, 'Rania Samir'), 'A genuine name search still finds the employee');

// =====================================================================
section('CSRF');
// =====================================================================
$victim = new Client($baseUrl, 'csrf');
ok($victim->login('approver', 'U8'), 'Medical signed in for the CSRF test');

$target = (string) Database::scalar(
    "SELECT case_id FROM sick_cases WHERE status = 'Submitted' ORDER BY case_id"
);

if ($target !== '') {
    // No token at all.
    $victim->post([
        'p'            => 'approver',
        'r'            => 'case.approve',
        'id'           => $target,
        'rtw_required' => 'no',
        'note'         => 'forged',
    ]);
    ok(
        (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $target]) === 'Submitted',
        'An approval with no CSRF token changes nothing'
    );

    // A well-formed but wrong token.
    $victim->post([
        '_token'       => str_repeat('a', 64),
        'p'            => 'approver',
        'r'            => 'case.approve',
        'id'           => $target,
        'rtw_required' => 'no',
        'note'         => 'forged',
    ]);
    ok(
        (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $target]) === 'Submitted',
        'An approval with a wrong CSRF token changes nothing'
    );

    // Another user's valid token must not work in this session.
    $other = new Client($baseUrl, 'csrf-other');
    $other->login('approver', 'U9');
    $otherToken = $other->token();

    if ($otherToken !== '') {
        $victim->post([
            '_token'       => $otherToken,
            'p'            => 'approver',
            'r'            => 'case.approve',
            'id'           => $target,
            'rtw_required' => 'no',
            'note'         => 'forged',
        ]);
        ok(
            (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $target]) === 'Submitted',
            "Another session's CSRF token is rejected"
        );
    }

    // The real token works, proving the checks above failed for the right reason.
    $victim->get(['p' => 'approver', 'r' => 'case', 'id' => $target]);
    $victim->post([
        '_token'       => $victim->token(),
        'p'            => 'approver',
        'r'            => 'case.approve',
        'id'           => $target,
        'rtw_required' => 'no',
        'note'         => 'legitimate',
    ]);
    ok(
        (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $target]) !== 'Submitted',
        'The same request with a valid token does go through'
    );
}

// =====================================================================
section('Session handling');
// =====================================================================
$fixation = new Client($baseUrl, 'fixation');
$fixation->get(['p' => 'requester']);
$before = $fixation->sessionId();
ok($before !== '', 'A session id is issued before sign-in');

$fixation->post([
    '_token'     => $fixation->token(),
    'p'          => 'requester',
    'r'          => 'login',
    'identifier' => 'U2',
    'password'   => Seeder::DEMO_PASSWORD,
]);
$after = $fixation->sessionId();
ok($after !== '' && $after !== $before, 'The session id is regenerated on sign-in (fixation defeated)');

// Sign-out really ends access.
$fixation->get(['p' => 'requester', 'r' => 'tab', 't' => 'dash']);
ok(str_contains($fixation->body, 'Karim Adel'), 'The session works before sign-out');
$fixation->post(['_token' => $fixation->token(), 'p' => 'requester', 'r' => 'logout']);
$fixation->get(['p' => 'requester', 'r' => 'tab', 't' => 'mycases']);
ok(!str_contains($fixation->body, '<nav class="tabs">'), 'After sign-out the session no longer reaches a tab');

// =====================================================================
section('Direct file access');
// =====================================================================
// Nothing outside public/ may be fetched over HTTP.
// The PHP built-in server answers an unknown path with the document root's
// index.php, so the property to assert is not the status code but that no file
// content from outside public/ ever reaches the response body.
$leakMarkers = ['<?php', 'namespace App', 'SQLite format', 'password_hash', 'DB_PASSWORD'];

foreach ([
    '/../app/Config.php',
    '/../config/config.php',
    '/../storage/db/slms.sqlite',
    '/../.env',
    '/app/Config.php',
    '/app/Service/Auth.php',
    '/storage/db/slms.sqlite',
    '/storage/logs/php-error.log',
    '/config/config.php',
    '/resources/views/layout.php',
    '/bin/console.php',
] as $path) {
    $command = ['curl', '-sS', '-w', "\n__STATUS__%{http_code}", '--path-as-is', $baseUrl . $path];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output  = is_resource($process) ? (string) stream_get_contents($pipes[1]) : '';
    if (is_resource($process)) {
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    $marker = strrpos($output, "\n__STATUS__");
    $code   = $marker === false ? '000' : substr($output, $marker + 11);
    $body   = $marker === false ? $output : substr($output, 0, $marker);

    $disclosed = false;
    foreach ($leakMarkers as $leak) {
        if (str_contains($body, $leak)) {
            $disclosed = true;
            break;
        }
    }

    ok(!$disclosed, "{$path} discloses no file content (HTTP {$code})");
}

echo "\n" . str_repeat('─', 62) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[32m\033[1mAll {$total} security assertions passed.\033[0m\n";
    exit(0);
}
echo "\033[31m\033[1m{$failed} of {$total} security assertions failed:\033[0m\n";
foreach ($failures as $failure) {
    echo "  · {$failure}\n";
}
exit(1);
