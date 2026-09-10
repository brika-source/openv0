<?php

declare(strict_types=1);

namespace App\Repo;

use App\Database;

/**
 * Monotonic counters behind the human-readable identifiers (SL-0009, FTW-0003,
 * U12). The read-modify-write runs as a single UPDATE so two concurrent
 * submissions cannot be handed the same number.
 */
final class Sequences
{
    public static function next(string $name): int
    {
        return Database::transaction(static function () use ($name): int {
            $exists = Database::scalar('SELECT value FROM sequences WHERE name = :n', ['n' => $name]);

            if ($exists === null) {
                Database::insert('sequences', ['name' => $name, 'value' => 1]);
                return 1;
            }

            Database::run(
                'UPDATE sequences SET value = value + 1 WHERE name = :n',
                ['n' => $name]
            );

            return (int) Database::scalar('SELECT value FROM sequences WHERE name = :n', ['n' => $name]);
        });
    }

    public static function current(string $name): int
    {
        return (int) Database::scalar('SELECT value FROM sequences WHERE name = :n', ['n' => $name]);
    }
}
