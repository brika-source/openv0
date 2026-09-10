<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query in the application goes through here with
 * bound parameters — there is no string interpolation of user input into SQL
 * anywhere in this codebase.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = (string) Config::get('db.driver', 'sqlite');

        try {
            self::$pdo = match ($driver) {
                'sqlite' => self::connectSqlite(),
                'mysql'  => self::connectMysql(),
                'pgsql'  => self::connectPgsql(),
                default  => throw new RuntimeException("Unsupported DB driver: {$driver}"),
            };
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return self::$pdo;
    }

    private static function connectSqlite(): PDO
    {
        $path = (string) Config::get('db.sqlite.path');
        $dir  = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create database directory: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $path);
        // Foreign keys are off by default in SQLite; the schema relies on them.
        $pdo->exec('PRAGMA foreign_keys = ON');
        // WAL keeps readers from blocking on the single writer.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    private static function connectMysql(): PDO
    {
        $c   = (array) Config::get('db.mysql');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'],
            (int) $c['port'],
            $c['database'],
            $c['charset']
        );

        return new PDO($dsn, (string) $c['username'], (string) $c['password'], [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES'",
        ]);
    }

    private static function connectPgsql(): PDO
    {
        $c   = (array) Config::get('db.pgsql');
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $c['host'],
            (int) $c['port'],
            $c['database']
        );

        return new PDO($dsn, (string) $c['username'], (string) $c['password']);
    }

    public static function driver(): string
    {
        return (string) Config::get('db.driver', 'sqlite');
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        /** @var array<int,array<string,mixed>> $rows */
        $rows = self::run($sql, $params)->fetchAll();
        return $rows;
    }

    /**
     * @param  array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $params */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Insert a row from an associative array and return the statement.
     *
     * @param array<string,mixed> $data
     */
    public static function insert(string $table, array $data): \PDOStatement
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return self::run($sql, self::bindable($data));
    }

    /**
     * Update rows matching a single-column condition.
     *
     * @param array<string,mixed> $data
     */
    public static function update(string $table, array $data, string $whereColumn, mixed $whereValue): \PDOStatement
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = :' . $column;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :__where',
            $table,
            implode(', ', $sets),
            $whereColumn
        );

        $params            = self::bindable($data);
        $params['__where'] = $whereValue;

        return self::run($sql, $params);
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    public static function begin(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * Run a callback inside a transaction, rolling back on any exception.
     *
     * @template T
     * @param  callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        self::begin();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * PDO cannot bind booleans portably across SQLite/MySQL/Postgres; normalise
     * them to integers, and leave null/scalars untouched.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function bindable(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /** Reset the connection — used by the test suite between fixtures. */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
