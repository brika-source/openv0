<?php

declare(strict_types=1);

/**
 * Unit tests for the ported business rules and the escaping helpers.
 *
 * These run without a web server and without touching the application
 * database: each fixture is built in an in-memory SQLite database, so the tests
 * are order-independent and leave nothing behind.
 *
 *   php tests/unit.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Config;
use App\Database;
use App\Domain;
use App\Helpers;
use App\I18n;
use App\Repo\Cases;
use App\Repo\Notifications;
use App\Repo\Settings;
use App\Schema;
use App\Seeder;
use App\Service\Analytics;

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

function same(mixed $expected, mixed $actual, string $description): void
{
    ok(
        $expected === $actual,
        $description . ($expected === $actual ? '' : sprintf(
            ' (expected %s, got %s)',
            var_export($expected, true),
            var_export($actual, true)
        ))
    );
}

// =====================================================================
section('Date and duration helpers');
// =====================================================================
same(1, Helpers::daysBetween('2026-03-10', '2026-03-10'), 'A single day counts as 1 day');
same(2, Helpers::daysBetween('2026-03-10', '2026-03-11'), 'Two consecutive dates count as 2 days');
same(4, Helpers::daysBetween('2026-03-10', '2026-03-13'), 'A four-day span counts as 4 days');
same(1, Helpers::daysBetween('2026-03-13', '2026-03-10'), 'A reversed span is floored at 1 day');
same(32, Helpers::daysBetween('2026-01-01', '2026-02-01'), 'A span across a month boundary is inclusive');
same(366, Helpers::daysBetween('2028-01-01', '2028-12-31'), 'A leap year spans 366 days');

ok(Helpers::isValidDate('2026-02-28'), 'A real date validates');
ok(!Helpers::isValidDate('2026-02-30'), '30 February is rejected');
ok(!Helpers::isValidDate('2026-13-01'), 'Month 13 is rejected');
ok(!Helpers::isValidDate('26-01-01'), 'A two-digit year is rejected');
ok(!Helpers::isValidDate(''), 'An empty date is rejected');
ok(!Helpers::isValidDate('not-a-date'), 'Free text is rejected as a date');
ok(Helpers::isValidDate('2028-02-29'), '29 February validates in a leap year');
ok(!Helpers::isValidDate('2026-02-29'), '29 February is rejected in a non-leap year');

same('—', Helpers::fmtDate(null), 'A null date renders as an em dash');
same('—', Helpers::fmtDate(''), 'An empty date renders as an em dash');
same('10 Mar 2026', Helpers::fmtDate('2026-03-10'), 'Dates render as "10 Mar 2026"');

ok(Helpers::isPast('2000-01-01'), 'A past date is detected');
ok(!Helpers::isPast('2099-01-01'), 'A future date is not past');
ok(!Helpers::isPast(null), 'A null date is not past');

same('SL-0001', Helpers::refId('SL', 1), 'Reference ids are zero-padded to four digits');
same('FTW-0042', Helpers::refId('FTW', 42), 'Two-digit sequences pad correctly');
same('SL-12345', Helpers::refId('SL', 12345), 'A five-digit sequence is not truncated');

// =====================================================================
section('Escaping');
// =====================================================================
same('&lt;script&gt;', Helpers::e('<script>'), 'Angle brackets are escaped');
same('&quot;', Helpers::e('"'), 'Double quotes are escaped');
same('&#039;', Helpers::e("'"), 'Single quotes are escaped');
same('&amp;amp;', Helpers::e('&amp;'), 'An ampersand is escaped once, not decoded');
same('لائق للعمل', Helpers::e('لائق للعمل'), 'Arabic text passes through unchanged');
ok(
    !str_contains(Helpers::badge('<img src=x onerror=alert(1)>', 'b-fit'), '<img'),
    'A badge escapes markup in its text'
);
ok(
    // The prototype rendered <span class="badge ${cls}">; the port keeps that.
    str_contains(Helpers::badge('ok', 'b-fit'), 'class="badge b-fit"'),
    'A badge keeps the shared badge class plus its own'
);

same('file.pdf', Helpers::safeFilename('file.pdf'), 'A plain filename is unchanged');
same('passwd', Helpers::safeFilename('../../etc/passwd'), 'Path traversal is stripped from a filename');
same('evil.pdf', Helpers::safeFilename('C:\\windows\\evil.pdf'), 'A Windows path is stripped');
ok(!str_contains(Helpers::safeFilename("a\0b.pdf"), "\0"), 'A null byte is removed from a filename');
ok(Helpers::safeFilename('   ') !== '', 'A whitespace-only filename gets a fallback');

// =====================================================================
section('Bilingual labels');
// =====================================================================
ok(str_contains(I18n::t('sign_in'), 'Sign in'), 't() includes the English label');
ok(str_contains(I18n::t('sign_in'), 'تسجيل الدخول'), 't() includes the Arabic label');
ok(str_contains(I18n::t('sign_in'), "\n"), 't() stacks the two languages on separate lines');
ok(str_contains(I18n::topt('sign_in'), ' / '), 'topt() joins the two languages with a slash');
same('Sign in', I18n::en('sign_in'), 'en() returns English only');
same('nonexistent_key_xyz', I18n::t('nonexistent_key_xyz'), 'An unknown key returns the key itself');

foreach (Domain::statuses() as $status) {
    ok(I18n::has('status_' . $status), "Status '{$status}' has a label");
}
foreach (Domain::ftwStatuses() as $status) {
    ok(I18n::has('ftw_' . $status), "FTW status '{$status}' has a label");
}
foreach (Domain::specialties() as $specialty) {
    ok(I18n::has('spec_' . $specialty), "Specialty '{$specialty}' has a label");
}
foreach (Domain::jobDemands() as $demand) {
    ok(I18n::has('jd_' . $demand), "Job demand '{$demand}' has a label");
}
foreach (Domain::roles() as $role) {
    ok(I18n::has('role_' . $role), "Role '{$role}' has a label");
}

// Every status and FTW status maps to a real badge class.
foreach (Domain::statuses() as $status) {
    ok(str_starts_with(Domain::statusBadgeClass($status), 'b-'), "Status '{$status}' maps to a badge class");
}
foreach (Domain::ftwStatuses() as $status) {
    ok(str_starts_with(Domain::ftwBadgeClass($status), 'b-'), "FTW status '{$status}' maps to a badge class");
}

// =====================================================================
section('Portal / role matrix');
// =====================================================================
same(
    ['employee', 'manager'],
    Domain::rolesForPortal(Domain::PORTAL_REQUESTER),
    'The requester portal admits employees and managers'
);
same(
    ['medical', 'hr', 'admin'],
    Domain::rolesForPortal(Domain::PORTAL_APPROVER),
    'The approver portal admits medical, HR and admin'
);
foreach (Domain::roles() as $role) {
    $portal = Domain::portalForRole($role);
    ok(
        in_array($role, Domain::rolesForPortal($portal), true),
        "Role '{$role}' belongs to the portal it maps to"
    );
}
ok(Domain::isPortal('requester') && Domain::isPortal('approver'), 'Both portal names are recognised');
ok(!Domain::isPortal('') && !Domain::isPortal('admin'), 'A non-portal string is rejected');

// =====================================================================
section('Derived numbers, against a controlled fixture');
// =====================================================================
// Build the whole schema in memory so nothing touches the real database.
putenv('DB_DRIVER=sqlite');
Config::load(dirname(__DIR__) . '/config/config.php');
$reflection = new ReflectionClass(Config::class);
$items      = $reflection->getProperty('items');
$all        = Config::all();
$all['db']['driver']      = 'sqlite';
$all['db']['sqlite']['path'] = ':memory:';
$items->setValue(null, $all);

Database::reset();
Settings::flush();
Schema::create();
Seeder::run();

same(11, (int) Database::scalar('SELECT COUNT(*) FROM users'), 'The fixture has eleven users');
same(8, (int) Database::scalar('SELECT COUNT(*) FROM sick_cases'), 'The fixture has eight sick leave cases');
same(2, (int) Database::scalar('SELECT COUNT(*) FROM ftw_requests'), 'The fixture has two FTW records');
same(6, (int) Database::scalar('SELECT COUNT(*) FROM notifications'), 'The fixture has six notifications');

// ---- entitlement balance ----
$balance = Analytics::balance('U5');
same(21, $balance['entitlement'], 'The default entitlement is 21 days');
// U5 has three closed cases this year: 2 days + 1 day + 1 day = 4.
same(4, $balance['used'], 'Closed cases consume entitlement days');
same(17, $balance['remaining'], 'Remaining days are entitlement minus used');

$unused = Analytics::balance('U4');
same(0, $unused['used'], 'A submitted-but-undecided case consumes no entitlement');

// A case still under review must not count.
$review = Analytics::balance('U1');
same(0, $review['used'], 'A case under medical review consumes no entitlement');

// ---- SLA ----
$underReview = Cases::find('SL-0001');
$sla         = Analytics::sla($underReview);
ok($sla !== null, 'A case awaiting medical action has an SLA state');
same('amber', $sla['level'], 'A case idle for 30 hours is in SLA warning');

$closed = Cases::find('SL-0002');
same(null, Analytics::sla($closed), 'A closed case has no SLA clock');

$escalated = Cases::find('SL-0003');
same(null, Analytics::sla($escalated), 'A case awaiting RTW has no review SLA clock');

$pending = Cases::find('SL-0008');
$slaPending = Analytics::sla($pending);
ok($slaPending !== null && $slaPending['level'] === 'red', 'A case idle for 60 hours is escalated');

// Thresholds come from settings, not constants.
Settings::set('sla_amber_hrs', 100);
Settings::set('sla_red_hrs', 200);
Settings::flush();
same('ok', Analytics::sla(Cases::find('SL-0008'))['level'], 'Raising the thresholds clears the escalation');
Settings::set('sla_amber_hrs', 24);
Settings::set('sla_red_hrs', 48);
Settings::flush();

// ---- pattern flags ----
// The seeded data's flag outcome depends on which weekday "today" is, so both
// branches of the rule are exercised against dates built for the purpose
// rather than against the fixture.
same(false, Analytics::patternFlag('U3')['flag'], 'A single long absence raises no flag');
same(false, Analytics::patternFlag('U4')['flag'], 'One recent case raises no flag');

/** Insert a bare case for the flag tests. */
$makeCase = static function (string $caseId, string $employeeId, string $from, string $to, int $daysAgo): void {
    $ts = Helpers::hourOffset(-24 * $daysAgo);
    Database::insert('sick_cases', [
        'case_id'     => $caseId,
        'employee_id' => $employeeId,
        'diagnosis'   => 'Pattern fixture',
        'specialty'   => 'General',
        'from_date'   => $from,
        'to_date'     => $to,
        'status'      => Domain::STATUS_CLOSED,
        'created_at'  => $ts,
        'updated_at'  => $ts,
    ]);
    Database::insert('case_history', [
        'case_id' => $caseId,
        'ts'      => $ts,
        'actor'   => 'Fixture',
        'action'  => 'Submitted',
        'comment' => '',
    ]);
};

// Branch 1: three single-day absences inside the 90-day window.
$makeCase('SL-9001', 'U2', Helpers::dayOffset(-40), Helpers::dayOffset(-40), 40);
$makeCase('SL-9002', 'U2', Helpers::dayOffset(-25), Helpers::dayOffset(-25), 25);
$flagTwo = Analytics::patternFlag('U2');

$makeCase('SL-9003', 'U2', Helpers::dayOffset(-12), Helpers::dayOffset(-12), 12);
$flagThree = Analytics::patternFlag('U2');

ok(!$flagTwo['flag'] || $flagThree['flag'], 'Adding a third single-day absence does not clear the flag');
ok($flagThree['flag'], 'Three single-day absences in 90 days raise a pattern flag');
ok(str_contains($flagThree['reason'], 'single-day'), 'The flag reason names the single-day rule');

// Outside the window it stops counting.
$makeCase('SL-9004', 'U4', Helpers::dayOffset(-200), Helpers::dayOffset(-200), 200);
$makeCase('SL-9005', 'U4', Helpers::dayOffset(-190), Helpers::dayOffset(-190), 190);
$makeCase('SL-9006', 'U4', Helpers::dayOffset(-180), Helpers::dayOffset(-180), 180);
same(false, Analytics::patternFlag('U4')['flag'], 'Single-day absences older than 90 days do not count');

// Branch 2: two absences starting on a Monday or a Friday.
$lastMonday = date('Y-m-d', (int) strtotime('last monday'));
$priorFriday = date('Y-m-d', (int) strtotime('last monday -3 days'));
ok(in_array((int) date('N', (int) strtotime($lastMonday)), [1], true), 'The Monday fixture really is a Monday');
ok(in_array((int) date('N', (int) strtotime($priorFriday)), [5], true), 'The Friday fixture really is a Friday');

$makeCase('SL-9007', 'U3', $lastMonday, date('Y-m-d', (int) strtotime($lastMonday . ' +2 days')), 5);
$makeCase('SL-9008', 'U3', $priorFriday, date('Y-m-d', (int) strtotime($priorFriday . ' +3 days')), 8);
$mfFlag = Analytics::patternFlag('U3');
ok($mfFlag['flag'], 'Two Monday/Friday starts in 90 days raise a pattern flag');
ok(str_contains($mfFlag['reason'], 'Mon/Fri'), 'The flag reason names the Monday/Friday rule');

$flagged = Analytics::flaggedEmployees();
ok($flagged !== [], 'The flagged list is populated once the rule is met');
foreach ($flagged as $item) {
    same('employee', (string) $item['user']['role'], 'Only employees are pattern-flagged');
}
same(count($flagged), count(Analytics::flaggedEmployees()), 'flaggedEmployees() is stable across calls');

// ---- restriction expiry ----
$expiring = Analytics::expiringRestrictions();
same(0, count($expiring), 'A restriction reviewed in 10 days is outside the 7-day window');

// Move the review date inside the window.
Database::run(
    "UPDATE ftw_requests SET restriction_review_date = :d WHERE id = 'FTW-0002'",
    ['d' => Helpers::dayOffset(3)]
);
$expiring = Analytics::expiringRestrictions();
same(1, count($expiring), 'A restriction reviewed in 3 days is inside the window');
same('ftw', $expiring[0]['kind'], 'The expiring restriction is identified as an FTW one');

// An already-overdue restriction is still listed.
Database::run(
    "UPDATE ftw_requests SET restriction_review_date = :d WHERE id = 'FTW-0002'",
    ['d' => Helpers::dayOffset(-5)]
);
same(1, count(Analytics::expiringRestrictions()), 'An overdue restriction is still listed');

// ---- KPIs ----
$kpis = Analytics::kpis();
same(3, $kpis['pending_review'], 'Three cases await a medical decision');
same(1, $kpis['rtw_pending'], 'One case awaits RTW clearance');
same(1, $kpis['ftw_pending'], 'One FTW assessment is outstanding');
same(1, $kpis['ftw_done'], 'One FTW assessment is complete');
same(count($flagged), $kpis['flags'], 'The KPI row reports the same flag count as the flag list');
foreach ($kpis as $name => $value) {
    ok(is_int($value) && $value >= 0, "KPI '{$name}' is a non-negative integer");
}

// ---- activity feed ----
$feed = Analytics::activityFeed(5);
same(5, count($feed), 'The activity feed honours its limit');
for ($i = 1; $i < count($feed); $i++) {
    ok($feed[$i - 1]['ts'] >= $feed[$i]['ts'], 'The activity feed is ordered newest first');
}

// ---- case totals ----
$extended = Cases::find('SL-0001');
same(
    Helpers::daysBetween((string) $extended['from_date'], (string) $extended['to_date']),
    Cases::totalDays($extended),
    'A case with no extension totals its own span'
);
Cases::addExtension('SL-0001', (string) $extended['to_date'], Helpers::dayOffset(5));
$extended = Cases::find('SL-0001');
ok(
    Cases::totalDays($extended) > Helpers::daysBetween((string) $extended['from_date'], (string) $extended['to_date']),
    'An extension adds to the case total'
);

// ---- reviewers ----
same(
    'Dr. Amira Fouad (Medical)',
    Cases::reviewersOf(Cases::find('SL-0002')),
    'Reviewers are extracted from the case history'
);
same('—', Cases::reviewersOf(Cases::find('SL-0004')), 'A case with no medical action has no reviewer');

// =====================================================================
section('Notification targeting');
// =====================================================================
// Employee U2 is targeted directly; their manager is U6.
$forU2 = Notifications::forUser('U2', Domain::ROLE_EMPLOYEE);
ok($forU2 !== [], 'The employee receives their own notifications');
foreach ($forU2 as $notification) {
    ok(
        (int) Database::scalar(
            'SELECT COUNT(*) FROM notification_targets WHERE notification_id = :n AND target = :t',
            ['n' => $notification['id'], 't' => 'U2']
        ) === 1,
        'Every notification returned for U2 is addressed to U2'
    );
}

$forU6 = Notifications::forUser('U6', Domain::ROLE_MANAGER);
ok($forU6 !== [], 'A manager receives the notifications copied to them');

// A manager must not receive another manager's copies.
$u7Refs = array_map(static fn (array $n): string => (string) $n['ref_id'], Notifications::forUser('U7', Domain::ROLE_MANAGER));
ok(!in_array('SL-0002', $u7Refs, true), 'A manager does not receive another manager\'s team notification');

// An employee must not receive role-addressed medical notifications.
$employeeEvents = array_map(static fn (array $n): string => (string) $n['event'], $forU2);
ok(!in_array('Sick leave submitted', $employeeEvents, true), 'An employee does not receive medical-team notifications');

// Read state.
$unreadBefore = Notifications::unreadCount('U2', Domain::ROLE_EMPLOYEE);
ok($unreadBefore > 0, 'Notifications start unread');
Notifications::markAllRead('U2', Domain::ROLE_EMPLOYEE);
same(0, Notifications::unreadCount('U2', Domain::ROLE_EMPLOYEE), 'Marking all read clears the count');
Notifications::markAllRead('U2', Domain::ROLE_EMPLOYEE);
same(0, Notifications::unreadCount('U2', Domain::ROLE_EMPLOYEE), 'Marking all read twice is harmless');

// An empty or manager-shaped target that resolves to nobody is dropped.
$id = Notifications::push(['U2', '', 'role:manager:'], 'Test', 'Target filtering', null);
same(
    1,
    (int) Database::scalar('SELECT COUNT(*) FROM notification_targets WHERE notification_id = :n', ['n' => $id]),
    'Empty and manager-less targets are dropped on push'
);

// =====================================================================
section('Sequences');
// =====================================================================
$first  = App\Repo\Sequences::next('unit_test_seq');
$second = App\Repo\Sequences::next('unit_test_seq');
same(1, $first, 'A new sequence starts at 1');
same(2, $second, 'A sequence increments');
same(2, App\Repo\Sequences::current('unit_test_seq'), 'current() reports the last issued value');

$next = App\Repo\Sequences::next('sick_case');
same(9, $next, 'The seeded case sequence continues from 8');

echo "\n" . str_repeat('─', 62) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[32m\033[1mAll {$total} assertions passed.\033[0m\n";
    exit(0);
}
echo "\033[31m\033[1m{$failed} of {$total} assertions failed:\033[0m\n";
foreach ($failures as $failure) {
    echo "  · {$failure}\n";
}
exit(1);
