<?php

// Builds users for tests and local development, with one state per role.
// Every user shares a default password that satisfies the password policy.

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public const string DEFAULT_PASSWORD = 'Factory-Password-2026!';

    private const int REMEMBER_TOKEN_LENGTH = 10;

    protected $model = User::class;

    private static ?string $hashedDefaultPassword = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$hashedDefaultPassword ??= Hash::make(self::DEFAULT_PASSWORD),
            'role' => UserRole::Requester,
            'password_changed_at' => now(),
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
        ];
    }

    public function admin(): static
    {
        return $this->withRole(UserRole::Admin);
    }

    public function technician(): static
    {
        return $this->withRole(UserRole::Technician);
    }

    public function requester(): static
    {
        return $this->withRole(UserRole::Requester);
    }

    public function withPendingPasswordChange(): static
    {
        return $this->state(fn (): array => ['password_changed_at' => null]);
    }

    private function withRole(UserRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
