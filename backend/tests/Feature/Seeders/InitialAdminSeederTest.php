<?php

// Verifies the initial admin seeder.
// It creates one admin with a pending password change, never duplicates it and never prints the password.

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InitialAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private const string ADMIN_NAME = 'System Administrator';

    private const string ADMIN_EMAIL = 'Admin@Example.com';

    private const string ADMIN_PASSWORD = 'Initial-Admin-Password-2026!';

    public function test_it_creates_one_admin_with_a_pending_password_change(): void
    {
        $this->configureAdmin();

        $this->runSeeder();

        $admin = User::query()->sole();

        $this->assertSame('admin@example.com', $admin->email);
        $this->assertSame(self::ADMIN_NAME, $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->requiresPasswordChange());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->configureAdmin();
        $this->runSeeder();

        $originalHash = User::query()->sole()->password;

        $this->configureAdmin(name: 'Another Name', password: 'Another-Admin-Password-2026!');
        $this->runSeeder();

        $admin = User::query()->sole();

        $this->assertSame(self::ADMIN_NAME, $admin->name);
        $this->assertSame($originalHash, $admin->password);
    }

    public function test_missing_values_make_it_throw_naming_the_variables(): void
    {
        $this->configureAdmin(name: null, email: null, password: null);

        try {
            $this->runSeeder();
            $this->fail('The seeder accepted incomplete configuration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('INITIAL_ADMIN_NAME', $exception->getMessage());
            $this->assertStringContainsString('INITIAL_ADMIN_EMAIL', $exception->getMessage());
            $this->assertStringContainsString('INITIAL_ADMIN_PASSWORD', $exception->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }

    public function test_weak_password_makes_it_throw_without_revealing_the_password(): void
    {
        $weakPassword = 'weak';

        $this->configureAdmin(password: $weakPassword);

        try {
            $this->runSeeder();
            $this->fail('The seeder accepted a weak password.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('INITIAL_ADMIN_PASSWORD', $exception->getMessage());
            $this->assertStringNotContainsString($weakPassword, $exception->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }

    private function configureAdmin(
        ?string $name = self::ADMIN_NAME,
        ?string $email = self::ADMIN_EMAIL,
        ?string $password = self::ADMIN_PASSWORD,
    ): void {
        config(['initial_admin' => ['name' => $name, 'email' => $email, 'password' => $password]]);
    }

    private function runSeeder(): void
    {
        $this->app->make(InitialAdminSeeder::class)->run();
    }
}
