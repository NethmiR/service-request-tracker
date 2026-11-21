<?php

// Sets a PostgreSQL lock timeout for the current transaction and recognises the error it raises.
// Runs only on PostgreSQL so the SQLite test database is not affected.

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class LockTimeout
{
    private const string POSTGRES_DRIVER = 'pgsql';

    private const string LOCK_NOT_AVAILABLE_STATE = '55P03';

    private const string MILLISECONDS_SUFFIX = 'ms';

    public static function applyToCurrentTransaction(int $milliseconds): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== self::POSTGRES_DRIVER) {
            return;
        }

        $connection->statement(sprintf("SET LOCAL lock_timeout = '%d%s'", $milliseconds, self::MILLISECONDS_SUFFIX));
    }

    public static function isLockTimeout(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === self::LOCK_NOT_AVAILABLE_STATE;
    }
}
