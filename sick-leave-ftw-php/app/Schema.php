<?php

declare(strict_types=1);

namespace App;

/**
 * Schema creation, portable across SQLite, MySQL and PostgreSQL.
 *
 * Design notes:
 *  - Timestamps are VARCHAR(19) holding 'Y-m-d H:i:s' and dates are VARCHAR(10)
 *    holding 'Y-m-d'. Both sort correctly as strings, so range filters work
 *    identically on every driver while all date arithmetic stays in PHP.
 *  - Business identifiers (U1, SL-0001, FTW-0002) are the natural primary keys,
 *    exactly as in the prototypes, so exported data stays readable.
 */
final class Schema
{
    /**
     * Index definitions, kept separate from the table DDL because the three
     * drivers disagree on the syntax: SQLite and PostgreSQL accept
     * "CREATE INDEX IF NOT EXISTS", MySQL does not support IF NOT EXISTS on
     * CREATE INDEX at all, so there it is created unguarded and a
     * "duplicate key name" error is treated as success.
     *
     * @return list<array{name:string,table:string,columns:string,unique:bool}>
     */
    public static function indexes(): array
    {
        return [
            ['name' => 'idx_users_email',       'table' => 'users',                 'columns' => 'email',                'unique' => true],
            ['name' => 'idx_users_role',        'table' => 'users',                 'columns' => 'role',                 'unique' => false],
            ['name' => 'idx_users_manager',     'table' => 'users',                 'columns' => 'manager_id',           'unique' => false],
            ['name' => 'idx_cases_employee',    'table' => 'sick_cases',            'columns' => 'employee_id',          'unique' => false],
            ['name' => 'idx_cases_status',      'table' => 'sick_cases',            'columns' => 'status',               'unique' => false],
            ['name' => 'idx_cases_from',        'table' => 'sick_cases',            'columns' => 'from_date',            'unique' => false],
            ['name' => 'idx_case_history_case', 'table' => 'case_history',          'columns' => 'case_id, ts',          'unique' => false],
            ['name' => 'idx_case_docs_case',    'table' => 'case_documents',        'columns' => 'case_id',              'unique' => false],
            ['name' => 'idx_case_ext_case',     'table' => 'case_extensions',       'columns' => 'case_id',              'unique' => false],
            ['name' => 'idx_ftw_employee',      'table' => 'ftw_requests',          'columns' => 'employee_id',          'unique' => false],
            ['name' => 'idx_ftw_status',        'table' => 'ftw_requests',          'columns' => 'status',               'unique' => false],
            ['name' => 'idx_ftw_demands',       'table' => 'ftw_job_demands',       'columns' => 'ftw_id',               'unique' => false],
            ['name' => 'idx_ftw_att',           'table' => 'ftw_attachments',       'columns' => 'ftw_id',               'unique' => false],
            ['name' => 'idx_ftw_history',       'table' => 'ftw_history',           'columns' => 'ftw_id, ts',           'unique' => false],
            ['name' => 'idx_notif_ts',          'table' => 'notifications',         'columns' => 'ts',                   'unique' => false],
            ['name' => 'idx_notif_target',      'table' => 'notification_targets',  'columns' => 'target',               'unique' => false],
            ['name' => 'idx_notif_target_nid',  'table' => 'notification_targets',  'columns' => 'notification_id',      'unique' => false],
            ['name' => 'idx_notif_read',        'table' => 'notification_reads',    'columns' => 'notification_id, user_id', 'unique' => true],
            ['name' => 'idx_audit_ts',          'table' => 'audit_log',             'columns' => 'ts',                   'unique' => false],
            ['name' => 'idx_audit_entity',      'table' => 'audit_log',             'columns' => 'entity, entity_id',    'unique' => false],
        ];
    }

    /** Table DDL only; see indexes() for the index DDL. @return list<string> */
    public static function statements(string $driver): array
    {
        $pk  = match ($driver) {
            'mysql'  => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql'  => 'SERIAL PRIMARY KEY',
            default  => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $ts  = 'VARCHAR(19)';
        $dt  = 'VARCHAR(10)';
        $id  = 'VARCHAR(32)';

        return [
            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS users (
                id            {$id}       NOT NULL PRIMARY KEY,
                name          VARCHAR(160) NOT NULL,
                email         VARCHAR(190) NOT NULL,
                dept          VARCHAR(160) NOT NULL,
                role          VARCHAR(20)  NOT NULL,
                manager_id    {$id}       NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active     INTEGER      NOT NULL DEFAULT 1,
                created_at    {$ts}        NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS sick_cases (
                case_id                    {$id}        NOT NULL PRIMARY KEY,
                employee_id                {$id}        NOT NULL,
                diagnosis                  TEXT         NOT NULL,
                specialty                  VARCHAR(60)  NOT NULL,
                from_date                  {$dt}        NOT NULL,
                to_date                    {$dt}        NOT NULL,
                status                     VARCHAR(60)  NOT NULL,
                rtw_required               INTEGER      NULL,
                rtw_outcome                VARCHAR(60)  NULL,
                rtw_notes                  TEXT         NULL,
                rtw_by                     VARCHAR(160) NULL,
                rtw_at                     {$ts}        NULL,
                restriction_details        TEXT         NULL,
                restriction_review_date    {$dt}        NULL,
                created_at                 {$ts}        NOT NULL,
                updated_at                 {$ts}        NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS case_history (
                id         {$pk},
                case_id    {$id}        NOT NULL,
                ts         {$ts}        NOT NULL,
                actor      VARCHAR(190) NOT NULL,
                action     VARCHAR(120) NOT NULL,
                comment    TEXT         NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS case_documents (
                id          {$pk},
                case_id     {$id}        NOT NULL,
                name        VARCHAR(200) NOT NULL,
                version     INTEGER      NOT NULL DEFAULT 1,
                stored_name VARCHAR(200) NULL,
                size_bytes  INTEGER      NOT NULL DEFAULT 0,
                mime        VARCHAR(120) NULL,
                uploaded_at {$ts}        NOT NULL,
                uploaded_by {$id}        NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS case_extensions (
                id        {$pk},
                case_id   {$id} NOT NULL,
                from_date {$dt} NOT NULL,
                to_date   {$dt} NOT NULL,
                ts        {$ts} NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS ftw_requests (
                id                      {$id}        NOT NULL PRIMARY KEY,
                employee_id             {$id}        NOT NULL,
                requester_id            {$id}        NULL,
                requester_label         VARCHAR(190) NOT NULL,
                diagnosis               TEXT         NOT NULL,
                med_history             TEXT         NULL,
                job_free_text           TEXT         NULL,
                status                  VARCHAR(60)  NOT NULL,
                restriction_details     TEXT         NULL,
                restriction_review_date {$dt}        NULL,
                diagnostic_details      TEXT         NULL,
                diagnostic_at           {$ts}        NULL,
                created_at              {$ts}        NOT NULL,
                updated_at              {$ts}        NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS ftw_job_demands (
                id     {$pk},
                ftw_id {$id}        NOT NULL,
                demand VARCHAR(120) NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS ftw_attachments (
                id          {$pk},
                ftw_id      {$id}        NOT NULL,
                name        VARCHAR(200) NOT NULL,
                stored_name VARCHAR(200) NULL,
                size_bytes  INTEGER      NOT NULL DEFAULT 0,
                mime        VARCHAR(120) NULL,
                uploaded_at {$ts}        NOT NULL,
                uploaded_by {$id}        NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS ftw_history (
                id      {$pk},
                ftw_id  {$id}        NOT NULL,
                ts      {$ts}        NOT NULL,
                actor   VARCHAR(190) NOT NULL,
                action  VARCHAR(120) NOT NULL,
                comment TEXT         NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS notifications (
                id      {$pk},
                ts      {$ts}        NOT NULL,
                event   VARCHAR(120) NOT NULL,
                message TEXT         NOT NULL,
                ref_id  VARCHAR(32)  NULL
            )",

            // A notification fans out to one or more targets: a user id ('U1'),
            // a role ('role:medical') or a specific manager ('role:manager:U6').
            "CREATE TABLE IF NOT EXISTS notification_targets (
                id              {$pk},
                notification_id INTEGER      NOT NULL,
                target          VARCHAR(64)  NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS notification_reads (
                id              {$pk},
                notification_id INTEGER NOT NULL,
                user_id         {$id}   NOT NULL,
                read_at         {$ts}   NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS settings (
                setting_key   VARCHAR(60)  NOT NULL PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS sequences (
                name  VARCHAR(30) NOT NULL PRIMARY KEY,
                value INTEGER     NOT NULL DEFAULT 0
            )",

            // -------------------------------------------------------------
            "CREATE TABLE IF NOT EXISTS audit_log (
                id        {$pk},
                ts        {$ts}        NOT NULL,
                user_id   {$id}        NULL,
                user_name VARCHAR(160) NULL,
                role      VARCHAR(20)  NULL,
                portal    VARCHAR(20)  NULL,
                action    VARCHAR(80)  NOT NULL,
                entity    VARCHAR(40)  NULL,
                entity_id VARCHAR(64)  NULL,
                detail    TEXT         NULL,
                ip        VARCHAR(45)  NULL
            )",
        ];
    }

    /** Tables in dependency-safe drop order. */
    public static function tables(): array
    {
        return [
            'audit_log',
            'notification_reads',
            'notification_targets',
            'notifications',
            'ftw_history',
            'ftw_attachments',
            'ftw_job_demands',
            'ftw_requests',
            'case_extensions',
            'case_documents',
            'case_history',
            'sick_cases',
            'sequences',
            'settings',
            'users',
        ];
    }

    public static function create(): void
    {
        $pdo    = Database::pdo();
        $driver = Database::driver();

        foreach (self::statements($driver) as $sql) {
            $pdo->exec($sql);
        }

        foreach (self::indexes() as $index) {
            try {
                $pdo->exec(self::indexSql($index, $driver));
            } catch (\PDOException $e) {
                // MySQL has no IF NOT EXISTS for CREATE INDEX, so re-running the
                // migration raises "Duplicate key name" (errno 1061). That means
                // the index is already there, which is the desired end state.
                if ($driver === 'mysql' && str_contains($e->getMessage(), 'Duplicate key name')) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @param array{name:string,table:string,columns:string,unique:bool} $index
     */
    private static function indexSql(array $index, string $driver): string
    {
        $unique = $index['unique'] ? 'UNIQUE ' : '';

        // MySQL accepts no IF NOT EXISTS here; create() catches the duplicate
        // name error that a repeated migration would raise.
        if ($driver === 'mysql') {
            return sprintf(
                'CREATE %sINDEX %s ON %s (%s)',
                $unique,
                $index['name'],
                $index['table'],
                $index['columns']
            );
        }

        return sprintf(
            'CREATE %sINDEX IF NOT EXISTS %s ON %s (%s)',
            $unique,
            $index['name'],
            $index['table'],
            $index['columns']
        );
    }

    public static function drop(): void
    {
        $pdo = Database::pdo();
        foreach (self::tables() as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }

    public static function exists(): bool
    {
        try {
            Database::scalar('SELECT COUNT(*) FROM users');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
