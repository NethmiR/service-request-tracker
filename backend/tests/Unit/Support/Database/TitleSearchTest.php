<?php

// Verifies the title search clause built for the SQLite test database.
// The term is escaped, wrapped in wildcards and bound as a parameter, and the clause uses LIKE with an escape character.

namespace Tests\Unit\Support\Database;

use App\Models\ServiceRequest;
use App\Support\Database\TitleSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TitleSearchTest extends TestCase
{
    private const string COLUMN = 'title';

    private const string EXPECTED_CLAUSE = "\"service_requests\".\"title\" LIKE ? ESCAPE '\\'";

    public static function terms(): array
    {
        return [
            'plain term' => ['printer', '%printer%'],
            'percent sign' => ['100%', '%100\\%%'],
            'underscore' => ['user_id', '%user\\_id%'],
            'backslash' => ['C:\\Temp', '%C:\\\\Temp%'],
            'every special character' => ['a\\b%c_d', '%a\\\\b\\%c\\_d%'],
        ];
    }

    #[DataProvider('terms')]
    public function test_term_is_escaped_wrapped_and_bound(string $term, string $expectedBinding): void
    {
        $query = TitleSearch::apply(ServiceRequest::query(), self::COLUMN, $term);

        $this->assertSame([$expectedBinding], $query->getBindings());
    }

    public function test_clause_uses_like_with_an_escape_character_on_sqlite(): void
    {
        $query = TitleSearch::apply(ServiceRequest::query(), self::COLUMN, 'printer');

        $this->assertStringContainsString(self::EXPECTED_CLAUSE, $query->toSql());
        $this->assertStringNotContainsString('ILIKE', $query->toSql());
        $this->assertStringNotContainsString('printer', $query->toSql());
    }
}
