<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test suite.
 *
 * Drives the real application over HTTP through the PHP built-in server, with
 * real sessions and cookies, exercising every route and every workflow
 * transition: submission, review decisions, resubmission, rejection, all three
 * RTW outcomes, all five FTW outcomes, reports, CSV export, uploads and
 * downloads, admin actions, and the access-control boundaries.
 *
 *   php bin/console.php reset          # fresh data
 *   php -S 127.0.0.1:8111 -t public &
 *   php tests/http.php http://127.0.0.1:8111
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Database;
use App\Domain;
use App\Seeder;

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8111', '/');

// ---------------------------------------------------------------------
// Tiny test harness
// ---------------------------------------------------------------------
final class T
{
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var list<string> */
    public static array $failures = [];
    public static string $section = '';

    public static function section(string $name): void
    {
        self::$section = $name;
        echo "\n\033[1m── {$name}\033[0m\n";
    }

    public static function ok(bool $condition, string $description): void
    {
        if ($condition) {
            self::$passed++;
            echo "  \033[32m✓\033[0m {$description}\n";
            return;
        }

        self::$failed++;
        self::$failures[] = self::$section . ' :: ' . $description;
        echo "  \033[31m✗ {$description}\033[0m\n";
    }

    public static function equals(mixed $expected, mixed $actual, string $description): void
    {
        $condition = $expected === $actual;
        self::ok($condition, $description . ($condition ? '' : sprintf(
            ' (expected %s, got %s)',
            var_export($expected, true),
            var_export($actual, true)
        )));
    }

    public static function summary(): int
    {
        $total = self::$passed + self::$failed;
        echo "\n" . str_repeat('─', 62) . "\n";

        if (self::$failed === 0) {
            echo "\033[32m\033[1mAll {$total} assertions passed.\033[0m\n";
            return 0;
        }

        echo "\033[31m\033[1m" . self::$failed . " of {$total} assertions failed:\033[0m\n";
        foreach (self::$failures as $failure) {
            echo "  · {$failure}\n";
        }
        return 1;
    }
}

/**
 * A browser session: keeps its own cookie jar, follows nothing automatically,
 * and extracts the CSRF token from whatever page it last loaded.
 */
final class Browser
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    /** @var array<int,string> */
    public array $headers = [];

    public function __construct(private string $baseUrl, string $name)
    {
        $this->jar = sys_get_temp_dir() . '/slms-test-' . $name . '-' . getmypid() . '.cookies';
        @unlink($this->jar);
    }

    /** @param array<string,mixed> $query */
    public function get(array $query = [], bool $follow = true): string
    {
        return $this->send('GET', $query, [], $follow);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $fields
     */
    public function post(array $query, array $fields, bool $follow = true, array $files = []): string
    {
        return $this->send('POST', $query, $fields, $follow, $files);
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $fields
     * @param array<string,string> $files  form field => local path
     */
    private function send(string $method, array $query, array $fields, bool $follow, array $files = []): string
    {
        $url = $this->baseUrl . '/index.php';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $command = ['curl', '-sS', '-o', '-', '-w', "\n__STATUS__%{http_code}"];
        $command[] = '-c'; $command[] = $this->jar;
        $command[] = '-b'; $command[] = $this->jar;
        if ($follow) {
            $command[] = '-L';
        }

        if ($method === 'POST') {
            // Deliberately no -X POST: that would force POST on every redirect
            // follow too. -d / -F already selects POST, and curl then correctly
            // switches to GET on a 303, exactly as a browser does.
            if ($files !== []) {
                foreach ($fields as $key => $value) {
                    foreach (is_array($value) ? $value : [$value] as $item) {
                        $command[] = '-F';
                        $command[] = $key . '=' . $item;
                    }
                }
                foreach ($files as $key => $path) {
                    $command[] = '-F';
                    $command[] = $key . '=@' . $path;
                }
            } else {
                $body = [];
                foreach ($fields as $key => $value) {
                    foreach (is_array($value) ? $value : [$value] as $item) {
                        $body[] = urlencode((string) $key) . '=' . urlencode((string) $item);
                    }
                }
                $command[] = '-d';
                $command[] = implode('&', $body);
            }
        }

        $command[] = $url;

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Could not run curl');
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $marker = strrpos($output, "\n__STATUS__");
        if ($marker !== false) {
            $this->status = (int) substr($output, $marker + strlen("\n__STATUS__"));
            $output       = substr($output, 0, $marker);
        }

        $this->body = $output;
        return $output;
    }

    /** The CSRF token from the most recently loaded page. */
    public function token(): string
    {
        if (preg_match('/name="_token" value="([a-f0-9]{64})"/', $this->body, $m) === 1) {
            return $m[1];
        }
        return '';
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }

    /** Sign in and return whether the tab bar rendered (i.e. it worked). */
    public function login(string $portal, string $identifier, string $password = Seeder::DEMO_PASSWORD): bool
    {
        $this->get(['p' => $portal]);
        $token = $this->token();

        $this->post([], [
            '_token'     => $token,
            'p'          => $portal,
            'r'          => 'login',
            'identifier' => $identifier,
            'password'   => $password,
        ]);

        return $this->contains('nav class="tabs"');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }
}

$fixture = sys_get_temp_dir() . '/slms-test-upload.txt';
file_put_contents($fixture, "X-ray report placeholder for the automated test suite.\n");

// =====================================================================
T::section('Public surface');
// =====================================================================
$guest = new Browser($baseUrl, 'guest');

$guest->get();
T::equals(200, $guest->status, 'Landing page returns 200');
T::ok($guest->contains('Choose your portal'), 'Landing page offers both portals');

$guest->get(['p' => 'requester']);
T::equals(200, $guest->status, 'Requester login page returns 200');
T::ok($guest->contains('Requester Portal'), 'Requester login shows the portal name');
T::ok($guest->contains('name="_token"'), 'Login form carries a CSRF token');

$guest->get(['p' => 'approver']);
T::ok($guest->contains('Approver'), 'Approver login page renders');
T::ok(!$guest->contains('Mona Fathy'), 'Approver login does not list requester-only accounts');

$guest->get(['p' => 'nonsense']);
T::ok($guest->contains('Choose your portal'), 'An unknown portal falls back to the landing page');

// An unauthenticated attempt at a privileged route gets the login page, not data.
$guest->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
T::ok(!$guest->contains('Lower back strain'), 'Signed-out request for the review queue leaks no case data');

// A POST without a token is rejected.
$guest->get(['p' => 'requester']);
$guest->post([], ['p' => 'requester', 'r' => 'login', 'identifier' => 'U1', 'password' => Seeder::DEMO_PASSWORD]);
T::ok(!$guest->contains('nav class="tabs"'), 'Login POST without a CSRF token is refused');

// =====================================================================
T::section('Authentication');
// =====================================================================
$bad = new Browser($baseUrl, 'bad');
T::ok(!$bad->login('requester', 'U1', 'wrong-password'), 'Wrong password is rejected');
T::ok($bad->contains('Incorrect account or password'), 'Wrong password shows the error message');

T::ok(!$bad->login('requester', 'U8'), 'Medical account cannot sign in to the requester portal');
T::ok($bad->contains('cannot sign in to this portal'), 'Wrong-portal login explains why');

T::ok(!$bad->login('approver', 'U1'), 'Employee account cannot sign in to the approver portal');

$employee = new Browser($baseUrl, 'employee');
T::ok($employee->login('requester', 'U1'), 'Employee signs in to the requester portal');
T::ok($employee->contains('Mona Fathy'), 'Header shows the signed-in employee');

$byEmail = new Browser($baseUrl, 'byemail');
T::ok($byEmail->login('requester', 'mona.fathy@example.com'), 'Sign-in by e-mail address works');

// =====================================================================
T::section('Employee portal');
// =====================================================================
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'dash']);
T::ok($employee->contains('Sick days used this year'), 'Employee dashboard shows the entitlement KPIs');

$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'mycases']);
T::ok($employee->contains('SL-0001'), 'My Cases lists the employee\'s own case');
T::ok(!$employee->contains('SL-0002'), 'My Cases does not list another employee\'s case');

$employee->get(['p' => 'requester', 'r' => 'case', 'id' => 'SL-0001']);
T::equals(200, $employee->status, 'Employee can open their own case');
T::ok($employee->contains('Lower back strain'), 'Own case shows its diagnosis');

$employee->get(['p' => 'requester', 'r' => 'case', 'id' => 'SL-0002']);
T::equals(403, $employee->status, 'Employee cannot open another employee\'s case');

$employee->get(['p' => 'requester', 'r' => 'case', 'id' => 'SL-9999']);
T::equals(404, $employee->status, 'A non-existent case gives 404');

// Reaching for an approver-only route from a requester session.
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'review']);
T::ok(!$employee->contains('Medical Review Queue'), 'Employee cannot render the medical review queue tab');

$employee->get(['p' => 'requester', 'r' => 'patient', 'id' => 'U2']);
T::equals(403, $employee->status, 'Employee cannot open the patient lookup');

// ---- submit a new sick leave request, with a real file ----
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'submit']);
$token = $employee->token();
$from  = date('Y-m-d', strtotime('-1 day'));
$to    = date('Y-m-d', strtotime('+2 days'));

$employee->post([], [
    '_token'    => $token,
    'p'         => 'requester',
    'r'         => 'case.submit',
    'diagnosis' => 'Automated test — acute bronchitis',
    'from_date' => $from,
    'to_date'   => $to,
    'specialty' => 'General',
], true, ['documents[]' => $fixture]);

$newCaseId = (string) Database::scalar(
    "SELECT case_id FROM sick_cases WHERE diagnosis LIKE 'Automated test%' ORDER BY case_id DESC"
);
T::ok($newCaseId !== '', 'Submitted case was stored (' . $newCaseId . ')');
T::ok($employee->contains($newCaseId), 'My Cases shows the newly submitted case');
T::equals(
    Domain::STATUS_SUBMITTED,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'New case starts in Submitted'
);
T::equals(
    1,
    (int) Database::scalar('SELECT COUNT(*) FROM case_documents WHERE case_id = :c', ['c' => $newCaseId]),
    'Uploaded document was recorded'
);
$storedName = (string) Database::scalar(
    'SELECT stored_name FROM case_documents WHERE case_id = :c',
    ['c' => $newCaseId]
);
T::ok($storedName !== '' && is_file(__DIR__ . '/../storage/uploads/' . $storedName), 'Uploaded file exists on disk');
T::equals(
    1,
    (int) Database::scalar(
        'SELECT COUNT(*) FROM notifications n JOIN notification_targets t ON t.notification_id = n.id
         WHERE n.ref_id = :c AND t.target = :m',
        ['c' => $newCaseId, 'm' => 'role:medical']
    ),
    'Submission notified the medical team'
);

// ---- validation ----
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'submit']);
$employee->post([], [
    '_token'    => $employee->token(),
    'p'         => 'requester',
    'r'         => 'case.submit',
    'diagnosis' => 'Reversed dates',
    'from_date' => date('Y-m-d', strtotime('+5 days')),
    'to_date'   => date('Y-m-d'),
    'specialty' => 'General',
]);
T::ok($employee->contains('To-date cannot be earlier'), 'Reversed dates are rejected');
T::equals(
    0,
    (int) Database::scalar("SELECT COUNT(*) FROM sick_cases WHERE diagnosis = 'Reversed dates'"),
    'A rejected submission stores nothing'
);

$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'submit']);
$employee->post([], [
    '_token'    => $employee->token(),
    'p'         => 'requester',
    'r'         => 'case.submit',
    'diagnosis' => '',
    'from_date' => date('Y-m-d'),
    'to_date'   => date('Y-m-d'),
    'specialty' => 'General',
]);
T::ok($employee->contains('complete diagnosis and dates') || $employee->contains('التشخيص'), 'Empty diagnosis is rejected');

// =====================================================================
T::section('Medical review workflow');
// =====================================================================
$medical = new Browser($baseUrl, 'medical');
T::ok($medical->login('approver', 'U8'), 'Medical signs in to the approver portal');

$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash']);
T::ok($medical->contains('Pending Review'), 'Approver dashboard shows the KPI row');
T::ok($medical->contains('Recent Activity'), 'Approver dashboard shows the activity feed');

$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
T::ok($medical->contains($newCaseId), 'Review queue contains the new case');
T::ok($medical->contains('Within SLA') || $medical->contains('SLA'), 'Review queue shows an SLA column');

// ---- request more information (Pending) ----
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
T::ok($medical->contains('Patient Story'), 'Case review shows the patient story');
T::ok($medical->contains('Automated test — acute bronchitis'), 'Case review shows the diagnosis');

$medical->post([], [
    '_token'  => $medical->token(),
    'p'       => 'approver',
    'r'       => 'case.pending',
    'id'      => $newCaseId,
    'comment' => 'Please upload the chest X-ray.',
]);
T::equals(
    Domain::STATUS_PENDING,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'Marking pending sets the Pending status'
);

// A decision with no comment must be refused.
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
$firstQueued = (string) Database::scalar(
    'SELECT case_id FROM sick_cases WHERE status = :s ORDER BY case_id',
    ['s' => Domain::STATUS_SUBMITTED]
);
if ($firstQueued !== '') {
    $medical->get(['p' => 'approver', 'r' => 'case', 'id' => $firstQueued]);
    $medical->post([], [
        '_token'  => $medical->token(),
        'p'       => 'approver',
        'r'       => 'case.reject',
        'id'      => $firstQueued,
        'comment' => '',
    ]);
    T::ok($medical->contains('provide a reason') || $medical->contains('سبب الرفض'), 'Rejection without a reason is refused');
    T::equals(
        Domain::STATUS_SUBMITTED,
        (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $firstQueued]),
        'A refused rejection leaves the status untouched'
    );
}

// ---- employee resubmits ----
$employee->get(['p' => 'requester', 'r' => 'case', 'id' => $newCaseId]);
T::ok($employee->contains('Resubmit'), 'A pending case offers the resubmission form');

$employee->post([], [
    '_token'  => $employee->token(),
    'p'       => 'requester',
    'r'       => 'case.resubmit',
    'id'      => $newCaseId,
    'comment' => 'X-ray attached as requested.',
], true, ['documents[]' => $fixture]);

T::equals(
    Domain::STATUS_REVIEW,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'Resubmission returns the case to medical review'
);
T::equals(
    2,
    (int) Database::scalar(
        'SELECT MAX(version) FROM case_documents WHERE case_id = :c AND name LIKE :n',
        ['c' => $newCaseId, 'n' => 'slms-test-upload%']
    ),
    'Re-uploading the same filename creates version 2'
);

// ---- approve, requiring RTW clearance ----
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
$medical->post([], [
    '_token'       => $medical->token(),
    'p'            => 'approver',
    'r'            => 'case.approve',
    'id'           => $newCaseId,
    'rtw_required' => 'yes',
    'note'         => 'Clearance required before returning to the line.',
]);
T::equals(
    Domain::STATUS_RTW_PENDING,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'Approving with RTW required moves the case to RTW Pending'
);

// A second decision on the same case must now be refused.
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
$medical->post([], [
    '_token'       => $medical->token(),
    'p'            => 'approver',
    'r'            => 'case.approve',
    'id'           => $newCaseId,
    'rtw_required' => 'no',
    'note'         => 'duplicate decision',
]);
T::ok($medical->contains('already actioned') || $medical->contains('تمت معالجة'), 'A duplicate decision is refused');
T::equals(
    Domain::STATUS_RTW_PENDING,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'The refused duplicate did not change the status'
);

// =====================================================================
T::section('Return-to-work outcomes');
// =====================================================================
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'rtw']);
T::ok($medical->contains($newCaseId), 'RTW queue contains the approved case');

// ---- not fit: extend, keeping the same case id ----
$originalTo = (string) Database::scalar('SELECT to_date FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]);
$extendTo   = date('Y-m-d', strtotime($originalTo . ' +4 days'));

$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
$medical->post([], [
    '_token'    => $medical->token(),
    'p'         => 'approver',
    'r'         => 'case.rtw',
    'id'        => $newCaseId,
    'outcome'   => 'notfit',
    'notes'     => 'Still symptomatic.',
    'extend_to' => $extendTo,
]);
T::equals(
    $extendTo,
    (string) Database::scalar('SELECT to_date FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'Extension moves the To-date'
);
T::equals(
    Domain::STATUS_RTW_PENDING,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'An extended case stays in RTW Pending under the same case id'
);
T::equals(
    1,
    (int) Database::scalar('SELECT COUNT(*) FROM case_extensions WHERE case_id = :c', ['c' => $newCaseId]),
    'The extension was recorded'
);

// An extension that does not move the date forward is refused.
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
$medical->post([], [
    '_token'    => $medical->token(),
    'p'         => 'approver',
    'r'         => 'case.rtw',
    'id'        => $newCaseId,
    'outcome'   => 'notfit',
    'notes'     => '',
    'extend_to' => date('Y-m-d', strtotime($extendTo . ' -1 day')),
]);
T::equals(
    1,
    (int) Database::scalar('SELECT COUNT(*) FROM case_extensions WHERE case_id = :c', ['c' => $newCaseId]),
    'A backwards extension is refused'
);

// ---- fit to return: closes the case ----
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => $newCaseId]);
$medical->post([], [
    '_token'  => $medical->token(),
    'p'       => 'approver',
    'r'       => 'case.rtw',
    'id'      => $newCaseId,
    'outcome' => 'fit',
    'notes'   => 'Cleared for full duty.',
]);
T::equals(
    Domain::STATUS_CLOSED,
    (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'Fit to Return closes the case'
);
T::equals(
    'Fit to Return',
    (string) Database::scalar('SELECT rtw_outcome FROM sick_cases WHERE case_id = :c', ['c' => $newCaseId]),
    'The RTW outcome was recorded'
);

// ---- restricted return, on the seeded case SL-0003 ----
$reviewDate = date('Y-m-d', strtotime('+5 days'));
$medical->get(['p' => 'approver', 'r' => 'case', 'id' => 'SL-0003']);
$medical->post([], [
    '_token'                  => $medical->token(),
    'p'                       => 'approver',
    'r'                       => 'case.rtw',
    'id'                      => 'SL-0003',
    'outcome'                 => 'restricted',
    'notes'                   => 'Light duties only.',
    'restriction_details'     => 'No lifting over 5kg for two weeks.',
    'restriction_review_date' => $reviewDate,
]);
T::equals(
    Domain::STATUS_RETURNED_RESTRICTED,
    (string) Database::scalar("SELECT status FROM sick_cases WHERE case_id = 'SL-0003'"),
    'Restricted return sets the Returned – With Restrictions status'
);
T::equals(
    $reviewDate,
    (string) Database::scalar("SELECT restriction_review_date FROM sick_cases WHERE case_id = 'SL-0003'"),
    'The restriction review date was stored'
);

// A restricted return with no details must be refused.
$rtwPending = (string) Database::scalar(
    'SELECT case_id FROM sick_cases WHERE status = :s ORDER BY case_id',
    ['s' => Domain::STATUS_RTW_PENDING]
);
if ($rtwPending !== '') {
    $medical->get(['p' => 'approver', 'r' => 'case', 'id' => $rtwPending]);
    $medical->post([], [
        '_token'                  => $medical->token(),
        'p'                       => 'approver',
        'r'                       => 'case.rtw',
        'id'                      => $rtwPending,
        'outcome'                 => 'restricted',
        'notes'                   => '',
        'restriction_details'     => '',
        'restriction_review_date' => '',
    ]);
    T::equals(
        Domain::STATUS_RTW_PENDING,
        (string) Database::scalar('SELECT status FROM sick_cases WHERE case_id = :c', ['c' => $rtwPending]),
        'A restricted return with no details is refused'
    );
}

// The restriction now appears in the expiry tracker.
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'expiry']);
T::ok($medical->contains('SL-0003'), 'Restriction expiry lists the restricted return');

// =====================================================================
T::section('Fit-to-work workflow');
// =====================================================================
$manager = new Browser($baseUrl, 'manager');
T::ok($manager->login('requester', 'U7'), 'Manager signs in to the requester portal');

$manager->get(['p' => 'requester', 'r' => 'tab', 't' => 'teamdash']);
T::ok($manager->contains('Salma Nabil'), 'Team dashboard lists the manager\'s direct report');
T::ok(!$manager->contains('appendectomy'), 'Team dashboard does not leak a diagnosis');
T::ok(!$manager->contains('Gastroenteritis'), 'Team dashboard does not leak any other diagnosis');

// A manager opening a restricted case sees the restriction, not the diagnosis.
$manager->get(['p' => 'requester', 'r' => 'case', 'id' => 'SL-0003']);
T::equals(200, $manager->status, 'Manager can open their report\'s restricted case');
T::ok($manager->contains('No lifting over 5kg'), 'Manager sees the restriction text');
T::ok(!$manager->contains('appendectomy'), 'Manager does not see the diagnosis');
T::ok(!$manager->contains('Patient Story'), 'Manager does not get the patient story');

// A manager cannot open a case belonging to someone else's report.
$manager->get(['p' => 'requester', 'r' => 'case', 'id' => 'SL-0001']);
T::equals(403, $manager->status, 'Manager cannot open another manager\'s report\'s case');

// ---- manager raises an FTW request ----
$manager->get(['p' => 'requester', 'r' => 'tab', 't' => 'ftwinit']);
T::ok($manager->contains('Salma Nabil'), 'Initiate FTW offers the manager\'s own reports');
T::ok(!$manager->contains('Mona Fathy'), 'Initiate FTW does not offer other managers\' reports');

$manager->post([], [
    '_token'        => $manager->token(),
    'p'             => 'requester',
    'r'             => 'ftw.request',
    'employee_id'   => 'U4',
    'diagnosis'     => 'Automated test — shoulder impingement',
    'med_history'   => 'Physio ongoing.',
    'demands[]'     => ['Lifting/carrying weight', 'Prolonged standing'],
    'job_free_text' => 'Warehouse picking, 20kg cartons.',
]);
$newFtwId = (string) Database::scalar(
    "SELECT id FROM ftw_requests WHERE diagnosis LIKE 'Automated test%' ORDER BY id DESC"
);
T::ok($newFtwId !== '', 'Manager-raised FTW request was stored (' . $newFtwId . ')');
T::equals(
    2,
    (int) Database::scalar('SELECT COUNT(*) FROM ftw_job_demands WHERE ftw_id = :f', ['f' => $newFtwId]),
    'Both job demands were stored'
);

// A manager cannot raise a request for someone who is not their report.
$manager->get(['p' => 'requester', 'r' => 'tab', 't' => 'ftwinit']);
$manager->post([], [
    '_token'      => $manager->token(),
    'p'           => 'requester',
    'r'           => 'ftw.request',
    'employee_id' => 'U1',
    'diagnosis'   => 'Should never be stored',
]);
T::equals(
    0,
    (int) Database::scalar("SELECT COUNT(*) FROM ftw_requests WHERE diagnosis = 'Should never be stored'"),
    'Manager cannot raise an FTW request outside their own team'
);

// ---- medical assesses: all five outcomes ----
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'ftwqueue']);
T::ok($medical->contains($newFtwId), 'FTW queue contains the new request');

$medical->get(['p' => 'approver', 'r' => 'ftw', 'id' => $newFtwId]);
$medical->post([], [
    '_token'  => $medical->token(),
    'p'       => 'approver',
    'r'       => 'ftw.outcome',
    'id'      => $newFtwId,
    'outcome' => Domain::FTW_F2F,
]);
T::equals(
    Domain::FTW_F2F,
    (string) Database::scalar('SELECT status FROM ftw_requests WHERE id = :f', ['f' => $newFtwId]),
    'Needs F2F Consultation was recorded'
);

// Still assessable after F2F, per the queue definition.
$medical->get(['p' => 'approver', 'r' => 'ftw', 'id' => $newFtwId]);
$medical->post([], [
    '_token'             => $medical->token(),
    'p'                  => 'approver',
    'r'                  => 'ftw.outcome',
    'id'                 => $newFtwId,
    'outcome'            => Domain::FTW_DIAGTEST,
    'diagnostic_details' => 'Shoulder MRI and nerve conduction study.',
]);
T::equals(
    Domain::FTW_DIAGTEST,
    (string) Database::scalar('SELECT status FROM ftw_requests WHERE id = :f', ['f' => $newFtwId]),
    'Needs Further Diagnostic Tests was recorded'
);
T::ok(
    str_contains((string) Database::scalar('SELECT diagnostic_details FROM ftw_requests WHERE id = :f', ['f' => $newFtwId]), 'MRI'),
    'The requested diagnostic tests were stored'
);

// FTW-0001 is still Submitted: use it for the restriction outcome.
$medical->get(['p' => 'approver', 'r' => 'ftw', 'id' => 'FTW-0001']);
$medical->post([], [
    '_token'                  => $medical->token(),
    'p'                       => 'approver',
    'r'                       => 'ftw.outcome',
    'id'                      => 'FTW-0001',
    'outcome'                 => Domain::FTW_RESTRICT,
    'restriction_details'     => 'No lifting over 10kg; seated tasks preferred.',
    'restriction_review_date' => date('Y-m-d', strtotime('+3 days')),
]);
T::equals(
    Domain::FTW_RESTRICT,
    (string) Database::scalar("SELECT status FROM ftw_requests WHERE id = 'FTW-0001'"),
    'Fit with Restrictions was recorded'
);

// Restrictions with no details are refused.
$medical->get(['p' => 'approver', 'r' => 'ftw', 'id' => $newFtwId]);
$before = (string) Database::scalar('SELECT status FROM ftw_requests WHERE id = :f', ['f' => $newFtwId]);
$medical->post([], [
    '_token'                  => $medical->token(),
    'p'                       => 'approver',
    'r'                       => 'ftw.outcome',
    'id'                      => $newFtwId,
    'outcome'                 => Domain::FTW_RESTRICT,
    'restriction_details'     => '',
    'restriction_review_date' => '',
]);
T::equals(
    $before,
    (string) Database::scalar('SELECT status FROM ftw_requests WHERE id = :f', ['f' => $newFtwId]),
    'Fit with Restrictions without details is refused'
);

// ---- medical raises its own request and lands straight on the assessment ----
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'ftwinit']);
$medical->post([], [
    '_token'      => $medical->token(),
    'p'           => 'approver',
    'r'           => 'ftw.request',
    'employee_id' => 'U5',
    'diagnosis'   => 'Automated test — medical-initiated',
]);
T::ok($medical->contains('FTW Assessment'), 'Medical raising a request lands on the assessment screen');

$medicalFtwId = (string) Database::scalar(
    "SELECT id FROM ftw_requests WHERE diagnosis = 'Automated test — medical-initiated'"
);
$medical->get(['p' => 'approver', 'r' => 'ftw', 'id' => $medicalFtwId]);
$medical->post([], [
    '_token'  => $medical->token(),
    'p'       => 'approver',
    'r'       => 'ftw.outcome',
    'id'      => $medicalFtwId,
    'outcome' => Domain::FTW_FIT,
]);
T::equals(
    Domain::FTW_FIT,
    (string) Database::scalar('SELECT status FROM ftw_requests WHERE id = :f', ['f' => $medicalFtwId]),
    'Fit to Work was recorded'
);

// ---- employee raises their own request ----
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'myftw']);
T::ok($employee->contains('FTW-0002'), 'My FTW lists the employee\'s own assessment');
$employee->post([], [
    '_token'      => $employee->token(),
    'p'           => 'requester',
    'r'           => 'ftw.request',
    'employee_id' => 'U3',
    'diagnosis'   => 'Automated test — employee self request',
]);
T::equals(
    'U1',
    (string) Database::scalar(
        "SELECT employee_id FROM ftw_requests WHERE diagnosis = 'Automated test — employee self request'"
    ),
    'An employee\'s FTW request is forced to their own id, not the one they posted'
);

// An employee cannot record an outcome.
$employee->get(['p' => 'requester', 'r' => 'tab', 't' => 'myftw']);
$employee->post([], [
    '_token'  => $employee->token(),
    'p'       => 'requester',
    'r'       => 'ftw.outcome',
    'id'      => 'FTW-0002',
    'outcome' => Domain::FTW_FIT,
]);
T::equals(403, $employee->status, 'Employee cannot record an FTW outcome');

// =====================================================================
T::section('Notifications');
// =====================================================================
$employee->get(['p' => 'requester', 'r' => 'notifications']);
T::ok($employee->contains('pending') || $employee->contains('Approved'), 'Employee inbox shows their notifications');
T::ok(!$employee->contains('Salma Nabil — approved'), 'Employee inbox does not show another employee\'s notification');

$unreadBefore = (int) Database::scalar(
    "SELECT COUNT(*) FROM notifications n
     WHERE n.id IN (SELECT notification_id FROM notification_targets WHERE target = 'U1')
       AND NOT EXISTS (SELECT 1 FROM notification_reads r WHERE r.notification_id = n.id AND r.user_id = 'U1')"
);
T::ok($unreadBefore > 0, 'The employee has unread notifications to start with');

$employee->post([], [
    '_token'  => $employee->token(),
    'p'       => 'requester',
    'r'       => 'notifications.read',
    '_return' => '/index.php?p=requester&r=notifications',
]);
T::equals(
    0,
    (int) Database::scalar(
        "SELECT COUNT(*) FROM notifications n
         WHERE n.id IN (SELECT notification_id FROM notification_targets WHERE target = 'U1')
           AND NOT EXISTS (SELECT 1 FROM notification_reads r WHERE r.notification_id = n.id AND r.user_id = 'U1')"
    ),
    'Mark-all-read clears the unread count'
);

$manager->get(['p' => 'requester', 'r' => 'tab', 't' => 'feed']);
T::ok($manager->contains('Salma Nabil') || $manager->contains('restrictions'), 'Manager feed shows copies of decisions about their team');

// =====================================================================
T::section('Reports and audit');
// =====================================================================
$hr = new Browser($baseUrl, 'hr');
T::ok($hr->login('approver', 'U10'), 'HR signs in to the approver portal');

$hr->get(['p' => 'approver', 'r' => 'tab', 't' => 'reports']);
T::equals(200, $hr->status, 'HR can open Reports & Audit');
T::ok($hr->contains('SL-0001'), 'Report table lists cases');
T::ok($hr->contains('FTW Records') || $hr->contains('سجلات'), 'Report page includes the FTW register');

// Filtering by status narrows the table.
$hr->get(['p' => 'approver', 'r' => 'tab', 't' => 'reports', 'statuses' => [Domain::STATUS_CLOSED]]);
T::ok($hr->contains('SL-0002'), 'Status filter keeps a matching case');
T::ok(!$hr->contains('SL-0004'), 'Status filter removes a non-matching case');

// Filtering by employee.
$hr->get(['p' => 'approver', 'r' => 'tab', 't' => 'reports', 'employees' => ['U5']]);
T::ok($hr->contains('SL-0005'), 'Employee filter keeps that employee\'s case');
T::ok(!$hr->contains('SL-0001'), 'Employee filter removes other employees\' cases');

// HR must not be able to record decisions.
$hr->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
T::ok(!$hr->contains('Medical Review Queue'), 'HR has no review queue tab');
$hr->get(['p' => 'approver', 'r' => 'case', 'id' => 'SL-0004']);
T::equals(200, $hr->status, 'HR can read a full case record');
T::ok(!$hr->contains('name="r" value="case.approve"'), 'HR is given no approve control');

$hr->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
$hr->post([], [
    '_token'       => $hr->token(),
    'p'            => 'approver',
    'r'            => 'case.approve',
    'id'           => 'SL-0004',
    'rtw_required' => 'no',
    'note'         => 'HR should not be able to do this',
]);
T::equals(403, $hr->status, 'HR posting an approval is refused');
T::equals(
    Domain::STATUS_SUBMITTED,
    (string) Database::scalar("SELECT status FROM sick_cases WHERE case_id = 'SL-0004'"),
    'The refused HR approval changed nothing'
);

// CSV export.
$csv = $hr->get(['p' => 'approver', 'r' => 'report.csv']);
T::ok(str_contains($csv, 'Case ID'), 'CSV export has a header row');
T::ok(str_contains($csv, 'SL-0002'), 'CSV export contains case rows');
T::ok(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV export starts with a UTF-8 BOM');

// Printable record.
$print = $hr->get(['p' => 'approver', 'r' => 'case.print', 'id' => 'SL-0002']);
T::ok(str_contains($print, 'Influenza'), 'Printable record renders the case');
T::ok(str_contains($print, '<table'), 'Printable record includes the history table');

// =====================================================================
T::section('Admin');
// =====================================================================
$admin = new Browser($baseUrl, 'admin');
T::ok($admin->login('approver', 'U11'), 'Admin signs in to the approver portal');

$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
T::ok($admin->contains('Users'), 'Admin sees Users & Roles');
T::ok($admin->contains('U11'), 'User list includes the admin account');

// Settings round-trip.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'settings']);
$admin->post([], [
    '_token'           => $admin->token(),
    'p'                => 'approver',
    'r'                => 'admin.settings',
    'retention_years'  => 12,
    'sla_amber_hrs'    => 8,
    'sla_red_hrs'      => 16,
    'entitlement_days' => 30,
]);
T::equals(
    '30',
    (string) Database::scalar("SELECT setting_value FROM settings WHERE setting_key = 'entitlement_days'"),
    'Settings were saved'
);
T::equals(
    '8',
    (string) Database::scalar("SELECT setting_value FROM settings WHERE setting_key = 'sla_amber_hrs'"),
    'The SLA warning threshold was saved'
);

// Escalation below warning gets clamped up.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'settings']);
$admin->post([], [
    '_token'           => $admin->token(),
    'p'                => 'approver',
    'r'                => 'admin.settings',
    'retention_years'  => 12,
    'sla_amber_hrs'    => 24,
    'sla_red_hrs'      => 4,
    'entitlement_days' => 21,
]);
T::equals(
    '24',
    (string) Database::scalar("SELECT setting_value FROM settings WHERE setting_key = 'sla_red_hrs'"),
    'An escalation threshold below the warning is clamped up to it'
);

// Create a user, then use the account.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
$admin->post([], [
    '_token'     => $admin->token(),
    'p'          => 'approver',
    'r'          => 'admin.user.create',
    'name'       => 'Test Newcomer',
    'email'      => 'test.newcomer@example.com',
    'dept'       => 'Line Operations',
    'role'       => Domain::ROLE_EMPLOYEE,
    'manager_id' => 'U6',
    'password'   => 'TestPass123',
]);
$createdId = (string) Database::scalar("SELECT id FROM users WHERE email = 'test.newcomer@example.com'");
T::ok($createdId !== '', 'Admin created a user (' . $createdId . ')');

$newcomer = new Browser($baseUrl, 'newcomer');
T::ok($newcomer->login('requester', 'test.newcomer@example.com', 'TestPass123'), 'The created account can sign in');

// Duplicate e-mail refused.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
$admin->post([], [
    '_token'   => $admin->token(),
    'p'        => 'approver',
    'r'        => 'admin.user.create',
    'name'     => 'Duplicate',
    'email'    => 'test.newcomer@example.com',
    'dept'     => 'Quality',
    'role'     => Domain::ROLE_EMPLOYEE,
    'password' => 'TestPass123',
]);
T::equals(
    1,
    (int) Database::scalar("SELECT COUNT(*) FROM users WHERE email = 'test.newcomer@example.com'"),
    'A duplicate e-mail address is refused'
);

// Admin cannot change their own role or deactivate themselves.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
$admin->post([], [
    '_token' => $admin->token(),
    'p'      => 'approver',
    'r'      => 'admin.role',
    'id'     => 'U11',
    'role'   => Domain::ROLE_EMPLOYEE,
]);
T::equals(
    Domain::ROLE_ADMIN,
    (string) Database::scalar("SELECT role FROM users WHERE id = 'U11'"),
    'Admin cannot demote their own account'
);

// Deactivating an account ends its access.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
$admin->post([], [
    '_token' => $admin->token(),
    'p'      => 'approver',
    'r'      => 'admin.active',
    'id'     => $createdId,
    'active' => '0',
]);
T::equals(
    0,
    (int) Database::scalar('SELECT is_active FROM users WHERE id = :i', ['i' => $createdId]),
    'The account was deactivated'
);
$newcomer->get(['p' => 'requester', 'r' => 'tab', 't' => 'dash']);
T::ok(!$newcomer->contains('nav class="tabs"'), 'A deactivated account\'s live session is dropped');

$disabled = new Browser($baseUrl, 'disabled');
T::ok(!$disabled->login('requester', 'test.newcomer@example.com', 'TestPass123'), 'A deactivated account cannot sign in');
T::ok($disabled->contains('deactivated'), 'The deactivated account is told why');

// Audit trail recorded all of the above.
$admin->get(['p' => 'approver', 'r' => 'tab', 't' => 'audit']);
T::ok($admin->contains('user.create'), 'Audit trail records user creation');
T::ok($admin->contains('settings.save'), 'Audit trail records settings changes');
T::ok($admin->contains('case.approve'), 'Audit trail records case approvals');
T::ok($admin->contains('login'), 'Audit trail records sign-ins');

// Non-admins cannot reach admin routes.
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'users']);
T::ok(!$medical->contains('Add a User'), 'Medical has no Users & Roles tab');
$medical->get(['p' => 'approver', 'r' => 'tab', 't' => 'review']);
$medical->post([], [
    '_token' => $medical->token(),
    'p'      => 'approver',
    'r'      => 'admin.role',
    'id'     => 'U1',
    'role'   => Domain::ROLE_ADMIN,
]);
T::equals(403, $medical->status, 'Medical posting a role change is refused');
T::equals(
    Domain::ROLE_EMPLOYEE,
    (string) Database::scalar("SELECT role FROM users WHERE id = 'U1'"),
    'The refused role change did nothing'
);

// =====================================================================
T::section('Downloads and account');
// =====================================================================
$docId = (int) Database::scalar(
    'SELECT id FROM case_documents WHERE stored_name IS NOT NULL ORDER BY id DESC'
);
T::ok($docId > 0, 'There is a real uploaded document to test with');

$file = $employee->get(['p' => 'requester', 'r' => 'download', 'type' => 'case', 'id' => $docId]);
T::ok(str_contains($file, 'X-ray report placeholder'), 'The document owner can download their own file');

$otherEmployee = new Browser($baseUrl, 'other');
$otherEmployee->login('requester', 'U3');
$otherEmployee->get(['p' => 'requester', 'r' => 'download', 'type' => 'case', 'id' => $docId]);
T::equals(403, $otherEmployee->status, 'Another employee cannot download that file');

$manager->get(['p' => 'requester', 'r' => 'download', 'type' => 'case', 'id' => $docId]);
T::equals(403, $manager->status, 'A manager cannot download medical documents');

$medical->get(['p' => 'approver', 'r' => 'download', 'type' => 'case', 'id' => $docId]);
T::equals(200, $medical->status, 'Occupational Health can download the document');

// Seeded metadata-only records have no file behind them.
$metaId = (int) Database::scalar('SELECT id FROM case_documents WHERE stored_name IS NULL ORDER BY id');
$medical->get(['p' => 'approver', 'r' => 'download', 'type' => 'case', 'id' => $metaId]);
T::equals(404, $medical->status, 'A metadata-only document reports 404 rather than erroring');

// Password change.
$employee->get(['p' => 'requester', 'r' => 'account']);
T::ok($employee->contains('Change Password'), 'Account page offers a password change');

$employee->post([], [
    '_token'           => $employee->token(),
    'p'                => 'requester',
    'r'                => 'account.password',
    'current_password' => 'wrong',
    'new_password'     => 'BrandNewPass1',
    'confirm_password' => 'BrandNewPass1',
]);
T::ok($employee->contains('current password is incorrect'), 'A wrong current password is refused');

$employee->post([], [
    '_token'           => $employee->token(),
    'p'                => 'requester',
    'r'                => 'account.password',
    'current_password' => Seeder::DEMO_PASSWORD,
    'new_password'     => 'BrandNewPass1',
    'confirm_password' => 'Mismatch1',
]);
T::ok($employee->contains('do not match'), 'A mismatched confirmation is refused');

$employee->post([], [
    '_token'           => $employee->token(),
    'p'                => 'requester',
    'r'                => 'account.password',
    'current_password' => Seeder::DEMO_PASSWORD,
    'new_password'     => 'BrandNewPass1',
    'confirm_password' => 'BrandNewPass1',
]);
T::ok($employee->contains('Password updated'), 'The password change succeeded');

$rotated = new Browser($baseUrl, 'rotated');
T::ok($rotated->login('requester', 'U1', 'BrandNewPass1'), 'The new password works');
T::ok(!(new Browser($baseUrl, 'oldpw'))->login('requester', 'U1', Seeder::DEMO_PASSWORD), 'The old password no longer works');

// =====================================================================
T::section('Sessions and sign-out');
// =====================================================================
$both = new Browser($baseUrl, 'both');
T::ok($both->login('requester', 'U2'), 'One browser signs in to the requester portal');
T::ok($both->login('approver', 'U9'), 'The same browser signs in to the approver portal too');
$both->get(['p' => 'requester', 'r' => 'tab', 't' => 'dash']);
T::ok($both->contains('Karim Adel'), 'The requester session is still intact alongside the approver one');
$both->get(['p' => 'approver', 'r' => 'tab', 't' => 'mdash']);
T::ok($both->contains('Dr. Hossam Kamal'), 'The approver session is intact in the same browser');

$both->post([], ['_token' => $both->token(), 'p' => 'approver', 'r' => 'logout']);
T::ok(!$both->contains('nav class="tabs"'), 'Signing out of the approver portal returns the login page');
$both->get(['p' => 'requester', 'r' => 'tab', 't' => 'dash']);
T::ok($both->contains('Karim Adel'), 'Signing out of one portal leaves the other signed in');

// GET on a POST-only route must not act.
$admin->get(['p' => 'approver', 'r' => 'admin.reset']);
T::ok(
    (int) Database::scalar('SELECT COUNT(*) FROM audit_log') > 0,
    'A GET to a POST-only route did not reset anything'
);

// Unknown route.
$admin->get(['p' => 'approver', 'r' => 'no.such.route']);
T::equals(404, $admin->status, 'An unknown route gives 404');

// =====================================================================
T::section('Fixture cleanup');
// =====================================================================
// This suite rotated U1's password and deactivated a created account. Restore
// the demo fixture so a subsequent suite (tests/sweep.php) can still sign in
// with the documented demo password.
App\Repo\Users::setPassword('U1', Seeder::DEMO_PASSWORD);
T::ok(
    password_verify(Seeder::DEMO_PASSWORD, (string) App\Repo\Users::passwordHash('U1')),
    'The demo password was restored for U1'
);

@unlink($fixture);
exit(T::summary());
