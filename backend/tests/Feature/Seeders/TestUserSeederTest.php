<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\TestUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class TestUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private const string EXISTING_NAME = 'Existing Technician';

    public function test_it_creates_every_test_user(): void
    {
        $this->runSeeder();

        $this->assertSame(count(TestUserSeeder::USERS), User::query()->count());

        foreach (TestUserSeeder::USERS as $expectedUser) {
            $user = User::query()->where('email', $expectedUser['email'])->sole();

            $this->assertSame($expectedUser['name'], $user->name);
            $this->assertSame($expectedUser['role'], $user->role);
            $this->assertFalse($user->requiresPasswordChange());
            $this->assertNotSame($expectedUser['password'], $user->password);
            $this->assertTrue(Hash::check($expectedUser['password'], $user->password));
        }
    }

    public function test_every_test_password_satisfies_the_password_policy(): void
    {
        foreach (TestUserSeeder::USERS as $user) {
            $validator = Validator::make(
                ['password' => $user['password']],
                ['password' => ['required', 'string', Password::defaults()]],
            );

            $this->assertTrue($validator->passes(), sprintf('The password of %s breaks the password policy.', $user['email']));
        }
    }

    public function test_running_it_twice_creates_no_duplicate_users(): void
    {

        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(count(TestUserSeeder::USERS), User::query()->count());
    }

    public function test_an_existing_user_with_the_same_email_is_left_unchanged(): void
    {
        $testUser = TestUserSeeder::USERS[0];
        $existingUser = User::factory()->requester()->withPendingPasswordChange()->create([
            'name' => self::EXISTING_NAME,
            'email' => $testUser['email'],
        ]);

        $this->runSeeder();

        $user = User::query()->where('email', $testUser['email'])->sole();

        $this->assertTrue($user->is($existingUser));
        $this->assertSame(self::EXISTING_NAME, $user->name);
        $this->assertSame($existingUser->role, $user->role);
        $this->assertSame($existingUser->password, $user->password);
        $this->assertTrue($user->requiresPasswordChange());
    }

    private function runSeeder(): void
    {
        $this->app->make(TestUserSeeder::class)->run();
    }
}
