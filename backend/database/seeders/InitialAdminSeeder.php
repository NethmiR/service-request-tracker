<?php

// Creates the first admin from environment variables with a pending password change.
// Does nothing when an account with the same email already exists.

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    private const string CONFIG_KEY = 'initial_admin';

    private const string NAME = 'name';

    private const string EMAIL = 'email';

    private const string PASSWORD = 'password';

    private const array ENVIRONMENT_VARIABLES = [
        self::NAME => 'INITIAL_ADMIN_NAME',
        self::EMAIL => 'INITIAL_ADMIN_EMAIL',
        self::PASSWORD => 'INITIAL_ADMIN_PASSWORD',
    ];

    private const string INVALID_CONFIGURATION_MESSAGE = 'The initial admin cannot be seeded. Set valid values for these environment variables: %s. The password must satisfy the password policy.';

    private const int MAXIMUM_LENGTH = 255;

    public function run(): void
    {
        $configuration = config(self::CONFIG_KEY);

        $this->ensureValid($configuration);

        $email = Str::lower(trim($configuration[self::EMAIL]));

        if (User::query()->where(self::EMAIL, $email)->exists()) {
            return;
        }

        User::query()->create([
            self::NAME => trim($configuration[self::NAME]),
            self::EMAIL => $email,
            self::PASSWORD => $configuration[self::PASSWORD],
            'role' => UserRole::Admin,
        ]);
    }

    private function ensureValid(array $configuration): void
    {
        $validator = Validator::make($configuration, [
            self::NAME => ['required', 'string', 'max:'.self::MAXIMUM_LENGTH],
            self::EMAIL => ['required', 'string', 'email', 'max:'.self::MAXIMUM_LENGTH],
            self::PASSWORD => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->passes()) {
            return;
        }

        $invalidVariables = array_map(
            fn (string $field): string => self::ENVIRONMENT_VARIABLES[$field],
            $validator->errors()->keys(),
        );

        throw new RuntimeException(sprintf(self::INVALID_CONFIGURATION_MESSAGE, implode(', ', $invalidVariables)));
    }
}
