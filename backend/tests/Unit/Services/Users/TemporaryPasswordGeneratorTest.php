<?php

// Verifies the temporary password generator over many generated values.
// Every password must have the fixed length, one character of each kind, no easily misread characters and pass the password policy.

namespace Tests\Unit\Services\Users;

use App\Services\Users\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class TemporaryPasswordGeneratorTest extends TestCase
{
    private const int SAMPLE_SIZE = 200;

    private const array REQUIRED_CHARACTER_PATTERNS = [
        'lowercase letter' => '/[a-z]/',
        'uppercase letter' => '/[A-Z]/',
        'digit' => '/[0-9]/',
        'symbol' => '/[^a-zA-Z0-9]/',
    ];

    private const string MISREAD_CHARACTERS_PATTERN = '/[0Oo1lI]/';

    public function test_every_password_has_the_fixed_length_and_one_character_of_each_kind(): void
    {
        foreach ($this->generatePasswords() as $password) {
            $this->assertSame(TemporaryPasswordGenerator::LENGTH, strlen($password));

            foreach (self::REQUIRED_CHARACTER_PATTERNS as $kind => $pattern) {
                $this->assertMatchesRegularExpression($pattern, $password, "Missing a {$kind} in {$password}.");
            }
        }
    }

    public function test_passwords_never_contain_easily_misread_characters(): void
    {
        foreach ($this->generatePasswords() as $password) {
            $this->assertDoesNotMatchRegularExpression(self::MISREAD_CHARACTERS_PATTERN, $password);
        }
    }

    public function test_generated_passwords_are_all_different(): void
    {
        $passwords = $this->generatePasswords();

        $this->assertCount(self::SAMPLE_SIZE, array_unique($passwords));
    }

    public function test_every_password_passes_the_password_policy(): void
    {
        foreach ($this->generatePasswords() as $password) {
            $validator = Validator::make(['password' => $password], ['password' => [Password::defaults()]]);

            $this->assertFalse($validator->fails(), "Policy rejected {$password}.");
        }
    }

    private function generatePasswords(): array
    {
        $generator = new TemporaryPasswordGenerator;

        return array_map(fn (): string => $generator->generate(), range(1, self::SAMPLE_SIZE));
    }
}
