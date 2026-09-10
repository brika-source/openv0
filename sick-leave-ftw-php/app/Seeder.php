<?php

declare(strict_types=1);

namespace App;

/**
 * Demo data, reproducing the seed set from the two HTML prototypes exactly —
 * same eleven people, same eight sick leave cases, same two FTW assessments and
 * the same relative dates, so every screen has something meaningful on it the
 * moment the app is installed.
 *
 * Seeded documents carry metadata only (stored_name is NULL) because the
 * prototypes never had real files; anything uploaded through the running app is
 * stored on disk and downloadable.
 */
final class Seeder
{
    /** Demo password for every seeded account. */
    public const DEMO_PASSWORD = 'Passw0rd!';

    public static function run(): void
    {
        Database::transaction(static function (): void {
            self::seedSettings();
            self::seedUsers();
            self::seedCases();
            self::seedFtw();
            self::seedNotifications();
            self::seedSequences();
        });
    }

    private static function seedSettings(): void
    {
        $defaults = [
            'retention_years'  => (string) Config::get('business.retention_years', 15),
            'sla_amber_hrs'    => (string) Config::get('business.sla_amber_hrs', 24),
            'sla_red_hrs'      => (string) Config::get('business.sla_red_hrs', 48),
            'entitlement_days' => (string) Config::get('business.entitlement_days', 21),
        ];

        foreach ($defaults as $key => $value) {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    private static function seedUsers(): void
    {
        $hash = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);
        $now  = Helpers::now();

        $users = [
            ['U1',  'Mona Fathy',       'Line Operations',      Domain::ROLE_EMPLOYEE, 'U6'],
            ['U2',  'Karim Adel',       'Line Operations',      Domain::ROLE_EMPLOYEE, 'U6'],
            ['U3',  'Salma Nabil',      'Packaging',            Domain::ROLE_EMPLOYEE, 'U7'],
            ['U4',  'Youssef Hany',     'Warehouse',            Domain::ROLE_EMPLOYEE, 'U7'],
            ['U5',  'Rania Samir',      'Quality',              Domain::ROLE_EMPLOYEE, 'U6'],
            ['U6',  'Tarek Mostafa',    'Line Operations',      Domain::ROLE_MANAGER,  null],
            ['U7',  'Dina Hosny',       'Packaging / Warehouse', Domain::ROLE_MANAGER, null],
            ['U8',  'Dr. Amira Fouad',  'Occupational Health',  Domain::ROLE_MEDICAL,  null],
            ['U9',  'Dr. Hossam Kamal', 'Occupational Health',  Domain::ROLE_MEDICAL,  null],
            ['U10', 'Nourhan Adel',     'Human Resources',      Domain::ROLE_HR,       null],
            ['U11', 'System Admin',     'IT / Digital',         Domain::ROLE_ADMIN,    null],
        ];

        foreach ($users as [$id, $name, $dept, $role, $managerId]) {
            Database::insert('users', [
                'id'            => $id,
                'name'          => $name,
                'email'         => self::emailFor($name),
                'dept'          => $dept,
                'role'          => $role,
                'manager_id'    => $managerId,
                'password_hash' => $hash,
                'is_active'     => 1,
                'created_at'    => $now,
            ]);
        }
    }

    /** "Dr. Amira Fouad" -> "amira.fouad@example.com" */
    private static function emailFor(string $name): string
    {
        $clean = str_ireplace(['dr.', 'dr '], '', $name);
        $clean = trim(preg_replace('/[^a-zA-Z ]+/', '', $clean) ?? $clean);
        $parts = preg_split('/\s+/', strtolower($clean)) ?: ['user'];

        return implode('.', $parts) . '@example.com';
    }

    private static function seedCases(): void
    {
        $d  = static fn (int $days): string => Helpers::dayOffset($days);
        $dt = static fn (int $hours): string => Helpers::hourOffset($hours);

        $cases = [
            [
                'case_id'     => 'SL-0001',
                'employee_id' => 'U1',
                'diagnosis'   => 'Lower back strain',
                'specialty'   => 'Orthopedic',
                'from_date'   => $d(-2),
                'to_date'     => $d(1),
                'status'      => Domain::STATUS_REVIEW,
                'documents'   => [['medical_report.pdf', 1, $dt(-30)]],
                'history'     => [
                    [$dt(-30), 'Mona Fathy (Employee)', 'Submitted', 'Sick leave request submitted.'],
                ],
            ],
            [
                'case_id'      => 'SL-0002',
                'employee_id'  => 'U2',
                'diagnosis'    => 'Influenza',
                'specialty'    => 'General',
                'from_date'    => $d(-6),
                'to_date'      => $d(-4),
                'status'       => Domain::STATUS_CLOSED,
                'rtw_required' => 0,
                'documents'    => [['clinic_note.pdf', 1, $dt(-96)]],
                'history'      => [
                    [$dt(-96), 'Karim Adel (Employee)', 'Submitted', ''],
                    [$dt(-92), 'Dr. Amira Fouad (Medical)', 'Approved', 'RTW not required – returned automatically on To-date.'],
                ],
            ],
            [
                'case_id'      => 'SL-0003',
                'employee_id'  => 'U3',
                'diagnosis'    => 'Post-surgical recovery (appendectomy)',
                'specialty'    => 'General',
                'from_date'    => $d(-10),
                'to_date'      => $d(-1),
                'status'       => Domain::STATUS_RTW_PENDING,
                'rtw_required' => 1,
                'documents'    => [['discharge_summary.pdf', 1, $dt(-240)]],
                'history'      => [
                    [$dt(-240), 'Salma Nabil (Employee)', 'Submitted', ''],
                    [$dt(-235), 'Dr. Hossam Kamal (Medical)', 'Approved', 'RTW assessment required before return.'],
                ],
            ],
            [
                'case_id'     => 'SL-0004',
                'employee_id' => 'U4',
                'diagnosis'   => 'Gastroenteritis',
                'specialty'   => 'Gastroenterology',
                'from_date'   => $d(-1),
                'to_date'     => $d(0),
                'status'      => Domain::STATUS_SUBMITTED,
                'documents'   => [],
                'history'     => [
                    [$dt(-52), 'Youssef Hany (Employee)', 'Submitted', 'No documents yet, will upload prescription.'],
                ],
            ],
            [
                'case_id'      => 'SL-0005',
                'employee_id'  => 'U5',
                'diagnosis'    => 'Migraine',
                'specialty'    => 'General',
                'from_date'    => $d(-15),
                'to_date'      => $d(-14),
                'status'       => Domain::STATUS_CLOSED,
                'rtw_required' => 0,
                'documents'    => [['note.pdf', 1, $dt(-400)]],
                'history'      => [
                    [$dt(-400), 'Rania Samir (Employee)', 'Submitted', ''],
                    [$dt(-398), 'Dr. Amira Fouad (Medical)', 'Approved', ''],
                ],
            ],
            [
                'case_id'      => 'SL-0006',
                'employee_id'  => 'U5',
                'diagnosis'    => 'Migraine',
                'specialty'    => 'General',
                'from_date'    => $d(-8),
                'to_date'      => $d(-8),
                'status'       => Domain::STATUS_CLOSED,
                'rtw_required' => 0,
                'documents'    => [],
                'history'      => [
                    [$dt(-190), 'Rania Samir (Employee)', 'Submitted', ''],
                    [$dt(-188), 'Dr. Amira Fouad (Medical)', 'Approved', ''],
                ],
            ],
            [
                'case_id'      => 'SL-0007',
                'employee_id'  => 'U5',
                'diagnosis'    => 'Tension headache',
                'specialty'    => 'General',
                'from_date'    => $d(-1),
                'to_date'      => $d(-1),
                'status'       => Domain::STATUS_CLOSED,
                'rtw_required' => 0,
                'documents'    => [],
                'history'      => [
                    [$dt(-30), 'Rania Samir (Employee)', 'Submitted', ''],
                    [$dt(-28), 'Dr. Amira Fouad (Medical)', 'Approved', ''],
                ],
            ],
            [
                'case_id'     => 'SL-0008',
                'employee_id' => 'U2',
                'diagnosis'   => 'Wrist sprain — needs X-ray',
                'specialty'   => 'Orthopedic',
                'from_date'   => $d(0),
                'to_date'     => $d(3),
                'status'      => Domain::STATUS_PENDING,
                'documents'   => [],
                'history'     => [
                    [$dt(-70), 'Karim Adel (Employee)', 'Submitted', ''],
                    [$dt(-60), 'Dr. Hossam Kamal (Medical)', 'Comment / Request More Info', 'Please upload X-ray and orthopedic specialist report.'],
                ],
            ],
        ];

        foreach ($cases as $case) {
            $firstTs = $case['history'][0][0];
            $lastTs  = $case['history'][count($case['history']) - 1][0];

            Database::insert('sick_cases', [
                'case_id'      => $case['case_id'],
                'employee_id'  => $case['employee_id'],
                'diagnosis'    => $case['diagnosis'],
                'specialty'    => $case['specialty'],
                'from_date'    => $case['from_date'],
                'to_date'      => $case['to_date'],
                'status'       => $case['status'],
                'rtw_required' => $case['rtw_required'] ?? null,
                'created_at'   => $firstTs,
                'updated_at'   => $lastTs,
            ]);

            foreach ($case['history'] as [$ts, $actor, $action, $comment]) {
                Database::insert('case_history', [
                    'case_id' => $case['case_id'],
                    'ts'      => $ts,
                    'actor'   => $actor,
                    'action'  => $action,
                    'comment' => $comment,
                ]);
            }

            foreach ($case['documents'] as [$name, $version, $uploadedAt]) {
                Database::insert('case_documents', [
                    'case_id'     => $case['case_id'],
                    'name'        => $name,
                    'version'     => $version,
                    'stored_name' => null,
                    'size_bytes'  => 0,
                    'mime'        => 'application/pdf',
                    'uploaded_at' => $uploadedAt,
                    'uploaded_by' => $case['employee_id'],
                ]);
            }
        }
    }

    private static function seedFtw(): void
    {
        $d  = static fn (int $days): string => Helpers::dayOffset($days);
        $dt = static fn (int $hours): string => Helpers::hourOffset($hours);

        $records = [
            [
                'id'              => 'FTW-0001',
                'employee_id'     => 'U3',
                'requester_id'    => 'U7',
                'requester_label' => 'Dina Hosny (Manager)',
                'diagnosis'       => 'Post-surgical recovery (appendectomy)',
                'med_history'     => 'Recent abdominal surgery; recovering well per RTW note pending.',
                'job_free_text'   => 'Packaging line — moderate lifting up to 15kg, standing shifts.',
                'status'          => Domain::FTW_SUBMITTED,
                'demands'         => ['Lifting/carrying weight', 'Prolonged standing'],
                'attachments'     => ['job_description.pdf'],
                'history'         => [
                    [$dt(-40), 'Dina Hosny (Manager)', 'FTW Requested', ''],
                ],
            ],
            [
                'id'                      => 'FTW-0002',
                'employee_id'             => 'U1',
                'requester_id'            => 'U1',
                'requester_label'         => 'Mona Fathy (Employee)',
                'diagnosis'               => 'Lower back strain',
                'med_history'             => 'Ongoing physiotherapy for lumbar strain.',
                'job_free_text'           => 'Line operator — repetitive lifting of cartons.',
                'status'                  => Domain::FTW_RESTRICT,
                'restriction_details'     => 'No lifting >5kg; no standing >2 hrs continuous; day shift only.',
                'restriction_review_date' => $d(10),
                'demands'                 => ['Lifting/carrying weight', 'Repetitive motion'],
                'attachments'             => [],
                'history'                 => [
                    [$dt(-500), 'Mona Fathy (Employee)', 'FTW Requested', ''],
                    [$dt(-480), 'Dr. Amira Fouad (Medical)', 'Fit with Restrictions', 'Restrictions issued, review in 10 days.'],
                ],
            ],
        ];

        foreach ($records as $record) {
            $firstTs = $record['history'][0][0];
            $lastTs  = $record['history'][count($record['history']) - 1][0];

            Database::insert('ftw_requests', [
                'id'                      => $record['id'],
                'employee_id'             => $record['employee_id'],
                'requester_id'            => $record['requester_id'],
                'requester_label'         => $record['requester_label'],
                'diagnosis'               => $record['diagnosis'],
                'med_history'             => $record['med_history'],
                'job_free_text'           => $record['job_free_text'],
                'status'                  => $record['status'],
                'restriction_details'     => $record['restriction_details'] ?? null,
                'restriction_review_date' => $record['restriction_review_date'] ?? null,
                'created_at'              => $firstTs,
                'updated_at'              => $lastTs,
            ]);

            foreach ($record['demands'] as $demand) {
                Database::insert('ftw_job_demands', ['ftw_id' => $record['id'], 'demand' => $demand]);
            }

            foreach ($record['attachments'] as $name) {
                Database::insert('ftw_attachments', [
                    'ftw_id'      => $record['id'],
                    'name'        => $name,
                    'stored_name' => null,
                    'size_bytes'  => 0,
                    'mime'        => 'application/pdf',
                    'uploaded_at' => $firstTs,
                    'uploaded_by' => $record['requester_id'],
                ]);
            }

            foreach ($record['history'] as [$ts, $actor, $action, $comment]) {
                Database::insert('ftw_history', [
                    'ftw_id'  => $record['id'],
                    'ts'      => $ts,
                    'actor'   => $actor,
                    'action'  => $action,
                    'comment' => $comment,
                ]);
            }
        }
    }

    private static function seedNotifications(): void
    {
        $dt = static fn (int $hours): string => Helpers::hourOffset($hours);

        $notifications = [
            [$dt(-30),  ['role:medical'],                        'Sick leave submitted',  'Mona Fathy submitted a new sick leave request (SL-0001).', 'SL-0001'],
            [$dt(-92),  ['U2', 'role:manager:U6'],               'Decision: Approved',    'Karim Adel — sick leave approved.', 'SL-0002'],
            [$dt(-235), ['U3', 'role:manager:U7'],               'Decision: Approved',    'Salma Nabil — approved, RTW assessment required.', 'SL-0003'],
            [$dt(-60),  ['U2'],                                  'Decision: Comment',     'Please upload X-ray and specialist report.', 'SL-0008'],
            [$dt(-40),  ['role:medical'],                        'FTW request submitted', 'FTW request submitted for Salma Nabil by Dina Hosny.', 'FTW-0001'],
            [$dt(-480), ['U1', 'role:manager:U6', 'role:hr'],    'FTW outcome',           'Mona Fathy — Fit with Restrictions.', 'FTW-0002'],
        ];

        foreach ($notifications as [$ts, $targets, $event, $message, $refId]) {
            Database::insert('notifications', [
                'ts'      => $ts,
                'event'   => $event,
                'message' => $message,
                'ref_id'  => $refId,
            ]);
            $notificationId = (int) Database::lastInsertId();

            foreach ($targets as $target) {
                Database::insert('notification_targets', [
                    'notification_id' => $notificationId,
                    'target'          => $target,
                ]);
            }
        }
    }

    private static function seedSequences(): void
    {
        Database::insert('sequences', ['name' => 'sick_case', 'value' => 8]);
        Database::insert('sequences', ['name' => 'ftw', 'value' => 2]);
        Database::insert('sequences', ['name' => 'user', 'value' => 11]);
    }
}
