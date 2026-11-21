<?php

// Verifies that only the PostgreSQL lock_not_available state is recognised as a lock timeout.
// Also confirms that no statement runs on the SQLite test database.

namespace Tests\Unit\Support\Database;

use App\Support\Database\LockTimeout;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LockTimeoutTest extends TestCase
{
    private const string LOCK_NOT_AVAILABLE_STATE = '55P03';

    private const string CONNECTION_NAME = 'pgsql';

    private const string SAMPLE_SQL = 'select 1';

    private const int SAMPLE_TIMEOUT_MILLISECONDS = 3000;

    public static function otherStates(): array
    {
        return [
            'serialization failure' => ['40001'],
            'unique violation' => ['23505'],
        ];
    }

    public function test_lock_not_available_state_is_a_lock_timeout(): void
    {
        $this->assertTrue(LockTimeout::isLockTimeout($this->queryExceptionWithState(self::LOCK_NOT_AVAILABLE_STATE)));
    }

    #[DataProvider('otherStates')]
    public function test_other_states_are_not_a_lock_timeout(string $state): void
    {
        $this->assertFalse(LockTimeout::isLockTimeout($this->queryExceptionWithState($state)));
    }

    public function test_exception_without_error_information_is_not_a_lock_timeout(): void
    {
        $this->assertFalse(LockTimeout::isLockTimeout($this->queryExceptionWithState(null)));
    }

    public function test_no_statement_runs_on_sqlite(): void
    {
        $statements = [];

        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = $query->sql;
        });

        DB::transaction(fn () => LockTimeout::applyToCurrentTransaction(self::SAMPLE_TIMEOUT_MILLISECONDS));

        $this->assertSame([], $statements);
    }

    private function queryExceptionWithState(?string $state): QueryException
    {
        $previous = new PDOException;
        $previous->errorInfo = $state === null ? null : [$state, 7, 'database error'];

        return new QueryException(self::CONNECTION_NAME, self::SAMPLE_SQL, [], $previous);
    }
}
