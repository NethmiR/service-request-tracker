<?php

// Generates temporary passwords with a cryptographically secure generator.
// Every password holds a lowercase letter, an uppercase letter, a digit and a symbol, and leaves out characters that are easy to misread.

namespace App\Services\Users;

use Random\Randomizer;

final class TemporaryPasswordGenerator
{
    public const int LENGTH = 16;

    private const string LOWERCASE_LETTERS = 'abcdefghijkmnpqrstuvwxyz';

    private const string UPPERCASE_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const string DIGITS = '23456789';

    private const string SYMBOLS = '!@#$%^&*-_=+?';

    private const array REQUIRED_CHARACTER_SETS = [
        self::LOWERCASE_LETTERS,
        self::UPPERCASE_LETTERS,
        self::DIGITS,
        self::SYMBOLS,
    ];

    public function generate(): string
    {
        $password = '';

        foreach (self::REQUIRED_CHARACTER_SETS as $characterSet) {
            $password .= $this->pickFrom($characterSet);
        }

        $allCharacters = implode('', self::REQUIRED_CHARACTER_SETS);

        while (strlen($password) < self::LENGTH) {
            $password .= $this->pickFrom($allCharacters);
        }

        return (new Randomizer)->shuffleBytes($password);
    }

    private function pickFrom(string $characterSet): string
    {
        return $characterSet[random_int(0, strlen($characterSet) - 1)];
    }
}
