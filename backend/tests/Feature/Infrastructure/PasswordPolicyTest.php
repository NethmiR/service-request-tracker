<?php

// Verifies the central password policy defined in the application service provider.
// Passwords need at least twelve characters with mixed case, numbers and symbols.

namespace Tests\Feature\Infrastructure;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public static function invalidPasswords(): array
    {
        return [
            'shorter than twelve characters' => ['Short-Pass1'],
            'missing an uppercase letter' => ['lowercase-only-pass1'],
            'missing a lowercase letter' => ['UPPERCASE-ONLY-PASS1'],
            'missing a number' => ['Missing-Number-Pass'],
            'missing a symbol' => ['MissingSymbolPass1'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_password_breaking_the_policy_is_rejected(string $password): void
    {
        $this->assertTrue($this->validate($password)->fails());
    }

    public function test_password_meeting_every_rule_is_accepted(): void
    {
        $this->assertFalse($this->validate('Valid-Password-2026')->fails());
    }

    private function validate(string $password): ValidatorContract
    {
        return Validator::make(['password' => $password], ['password' => [Password::defaults()]]);
    }
}
