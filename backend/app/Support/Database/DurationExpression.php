<?php

// Builds the SQL fragment for the number of seconds between two timestamp columns, which each database computes differently.
// PostgreSQL serves the application, and the SQLite branch exists so the test suite can prove the overview averages.
// Column names always come from code, so the fragment never holds user data.

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use LogicException;

final class DurationExpression
{
    private const string POSTGRES_DRIVER = 'pgsql';

    private const string SQLITE_DRIVER = 'sqlite';

    private const int SECONDS_PER_DAY = 86400;

    private const string UNSUPPORTED_DRIVER_MESSAGE = 'Duration expressions support only the PostgreSQL and SQLite drivers.';

    public static function secondsBetween(string $startColumn, string $endColumn): string
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $start = $grammar->wrap($startColumn);
        $end = $grammar->wrap($endColumn);

        return match ($connection->getDriverName()) {
            self::POSTGRES_DRIVER => sprintf('EXTRACT(EPOCH FROM (%s - %s))', $end, $start),
            self::SQLITE_DRIVER => sprintf('((julianday(%s) - julianday(%s)) * %d)', $end, $start, self::SECONDS_PER_DAY),
            default => throw new LogicException(self::UNSUPPORTED_DRIVER_MESSAGE),
        };
    }
}
