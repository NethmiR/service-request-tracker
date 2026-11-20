<?php

// Adds PostgreSQL check constraints from migrations, because the schema builder has no method for them.
// Runs only on PostgreSQL so the SQLite test database still migrates.

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class CheckConstraint
{
    private const string POSTGRES_DRIVER = 'pgsql';

    private const string ALLOWED_VALUES_SUFFIX = 'check';

    private const string LOWERCASE_SUFFIX = 'lowercase_check';

    public static function allowedValues(string $table, string $column, array $values): void
    {
        $connection = self::postgresConnection();

        if ($connection === null) {
            return;
        }

        $pdo = $connection->getPdo();
        $quotedValues = implode(', ', array_map(fn (string $value): string => $pdo->quote($value), $values));
        $grammar = $connection->getQueryGrammar();

        self::add($connection, $table, $column, self::ALLOWED_VALUES_SUFFIX, sprintf(
            '%s IN (%s)',
            $grammar->wrap($column),
            $quotedValues,
        ));
    }

    public static function lowercase(string $table, string $column): void
    {
        $connection = self::postgresConnection();

        if ($connection === null) {
            return;
        }

        $wrappedColumn = $connection->getQueryGrammar()->wrap($column);

        self::add($connection, $table, $column, self::LOWERCASE_SUFFIX, sprintf(
            '%s = LOWER(%s)',
            $wrappedColumn,
            $wrappedColumn,
        ));
    }

    private static function postgresConnection(): ?Connection
    {
        $connection = DB::connection();

        return $connection->getDriverName() === self::POSTGRES_DRIVER ? $connection : null;
    }

    private static function add(Connection $connection, string $table, string $column, string $suffix, string $condition): void
    {
        $grammar = $connection->getQueryGrammar();

        $connection->statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)',
            $grammar->wrapTable($table),
            $grammar->wrap(implode('_', [$table, $column, $suffix])),
            $condition,
        ));
    }
}
