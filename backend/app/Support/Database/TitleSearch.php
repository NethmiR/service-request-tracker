<?php

// Adds a case insensitive substring match on a text column, using ILIKE on PostgreSQL and LIKE on other databases.
// The wildcard and escape characters in the term are escaped so they match literally, and the term is always a bound parameter.

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;

final class TitleSearch
{
    private const string POSTGRES_DRIVER = 'pgsql';

    private const string POSTGRES_OPERATOR = 'ILIKE';

    private const string DEFAULT_OPERATOR = 'LIKE';

    private const string WILDCARD = '%';

    private const string SINGLE_CHARACTER_WILDCARD = '_';

    private const string ESCAPE_CHARACTER = '\\';

    public static function apply(Builder $query, string $column, string $term): Builder
    {
        $connection = $query->getConnection();
        $operator = $connection->getDriverName() === self::POSTGRES_DRIVER ? self::POSTGRES_OPERATOR : self::DEFAULT_OPERATOR;
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));

        return $query->whereRaw(
            sprintf("%s %s ? ESCAPE '%s'", $wrappedColumn, $operator, self::ESCAPE_CHARACTER),
            [self::WILDCARD.self::escape($term).self::WILDCARD],
        );
    }

    private static function escape(string $term): string
    {
        $specialCharacters = [self::ESCAPE_CHARACTER, self::WILDCARD, self::SINGLE_CHARACTER_WILDCARD];

        return str_replace(
            $specialCharacters,
            array_map(fn (string $character): string => self::ESCAPE_CHARACTER.$character, $specialCharacters),
            $term,
        );
    }
}
