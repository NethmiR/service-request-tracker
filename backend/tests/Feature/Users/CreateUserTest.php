<?php

// Verifies an admin creating a user with a generated temporary password.
// Covers the stored user, the one time password response, the audit record, validation, duplicate emails, ignored fields and rate limiting.

namespace Tests\Feature\Users;

use App\Enums\AuthAuditEvent;
use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\PresenceVerifierInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuditLogs;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use InteractsWithAuditLogs;
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string USERS_ROUTE = '/api/users';

    private const string REQUEST_IP_ADDRESS = '127.0.0.1';

    private const string NEW_USER_NAME = 'Kasun Perera';

    private const string NEW_USER_EMAIL = 'kasun.perera@example.com';

    private const string TAKEN_EMAIL = 'taken@example.com';

    private const string CLIENT_SUPPLIED_PASSWORD = 'Client-Supplied-Password-2026!';

    private const int CLIENT_SUPPLIED_ID = 999999;

    private const int MAX_LENGTH = 255;

    public static function roles(): array
    {
        return array_combine(
            UserRole::values(),
            array_map(fn (UserRole $role): array => [$role], UserRole::cases()),
        );
    }

    public static function invalidPayloads(): array
    {
        $valid = [
            'name' => self::NEW_USER_NAME,
            'email' => self::NEW_USER_EMAIL,
            'role' => UserRole::Technician->value,
        ];

        return [
            'missing name' => [array_diff_key($valid, ['name' => true]), 'name'],
            'missing email' => [array_diff_key($valid, ['email' => true]), 'email'],
            'missing role' => [array_diff_key($valid, ['role' => true]), 'role'],
            'blank name' => [array_merge($valid, ['name' => '   ']), 'name'],
            'invalid email' => [array_merge($valid, ['email' => 'not-an-email']), 'email'],
            'name too long' => [array_merge($valid, ['name' => str_repeat('a', self::MAX_LENGTH + 1)]), 'name'],
            'unknown role' => [array_merge($valid, ['role' => 'SUPERVISOR']), 'role'],
            'role in lowercase' => [array_merge($valid, ['role' => 'technician']), 'role'],
        ];
    }

    public static function duplicateEmails(): array
    {
        return [
            'same email' => [self::TAKEN_EMAIL],
            'different case' => ['Taken@Example.COM'],
            'surrounding spaces' => ['  '.self::TAKEN_EMAIL.'  '],
        ];
    }

    public function test_valid_request_returns_the_user_and_a_temporary_password_that_is_never_cached(): void
    {
        $this->signInAsAdmin();

        $response = $this->postJson(self::USERS_ROUTE, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.user.name', self::NEW_USER_NAME)
            ->assertJsonPath('data.user.email', self::NEW_USER_EMAIL)
            ->assertJsonPath('data.user.role', UserRole::Technician->value)
            ->assertJsonPath('data.user.password_change_required', true);

        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_stored_user_has_the_role_a_normalized_email_a_pending_change_and_a_hash_of_the_password(): void
    {
        $this->signInAsAdmin();

        $temporaryPassword = $this->postJson(self::USERS_ROUTE, $this->payload(email: '  Kasun.Perera@Example.com '))
            ->assertCreated()
            ->json('data.temporary_password');

        $user = User::query()->where('email', self::NEW_USER_EMAIL)->sole();

        $this->assertSame(UserRole::Technician, $user->role);
        $this->assertNull($user->password_changed_at);
        $this->assertNotSame($temporaryPassword, $user->password);
        $this->assertTrue(Hash::check($temporaryPassword, $user->password));
    }

    public function test_creation_is_audited_with_the_admin_as_actor_and_the_new_user_as_target(): void
    {
        $admin = $this->signInAsAdmin();

        $userId = $this->postJson(self::USERS_ROUTE, $this->payload())
            ->assertCreated()
            ->json('data.user.id');

        $log = $this->auditLogsFor(AuthAuditEvent::UserCreatedByAdmin)->sole();

        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($userId, $log->target_user_id);
        $this->assertSame(self::REQUEST_IP_ADDRESS, $log->ip_address);
    }

    #[DataProvider('roles')]
    public function test_a_user_is_created_for_each_role(UserRole $role): void
    {
        $this->signInAsAdmin();

        $this->postJson(self::USERS_ROUTE, $this->payload(role: $role->value))
            ->assertCreated()
            ->assertJsonPath('data.user.role', $role->value);

        $this->assertSame($role, User::query()->where('email', self::NEW_USER_EMAIL)->sole()->role);
    }

    public function test_new_user_signs_in_with_the_temporary_password_and_must_change_it(): void
    {
        $this->signInAsAdmin();

        $temporaryPassword = $this->postJson(self::USERS_ROUTE, $this->payload())
            ->assertCreated()
            ->json('data.temporary_password');

        $this->switchToFreshClient();

        $this->signInThroughApi(User::query()->where('email', self::NEW_USER_EMAIL)->sole(), $temporaryPassword)
            ->assertOk()
            ->assertJsonPath('data.email', self::NEW_USER_EMAIL)
            ->assertJsonPath('data.password_change_required', true);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_without_writing_anything(array $payload, string $field): void
    {
        $this->signInAsAdmin();

        $this->postJson(self::USERS_ROUTE, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(1, User::query()->count());
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::UserCreatedByAdmin));
    }

    #[DataProvider('duplicateEmails')]
    public function test_duplicate_email_is_rejected(string $email): void
    {
        User::factory()->create(['email' => self::TAKEN_EMAIL]);

        $this->signInAsAdmin();

        $this->postJson(self::USERS_ROUTE, $this->payload(email: $email))
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::query()->where('email', self::TAKEN_EMAIL)->count());
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::UserCreatedByAdmin));
    }

    public function test_email_taken_after_validation_is_rejected_by_the_database_without_writing_anything(): void
    {
        $existingUser = User::factory()->create(['email' => self::TAKEN_EMAIL]);

        $this->signInAsAdmin();
        $this->ignoreExistingRowsDuringValidation();

        $this->postJson(self::USERS_ROUTE, $this->payload(email: self::TAKEN_EMAIL))
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame([$existingUser->id], User::query()->where('email', self::TAKEN_EMAIL)->pluck('id')->all());
        $this->assertCount(0, $this->auditLogsFor(AuthAuditEvent::UserCreatedByAdmin));
    }

    public function test_client_supplied_password_timestamp_and_id_are_ignored(): void
    {
        $this->signInAsAdmin();

        $response = $this->postJson(self::USERS_ROUTE, array_merge($this->payload(), [
            'id' => self::CLIENT_SUPPLIED_ID,
            'password' => self::CLIENT_SUPPLIED_PASSWORD,
            'password_changed_at' => now()->toIso8601ZuluString(),
        ]))->assertCreated();

        $user = User::query()->where('email', self::NEW_USER_EMAIL)->sole();

        $this->assertNotSame(self::CLIENT_SUPPLIED_ID, $user->id);
        $this->assertNull($user->password_changed_at);
        $this->assertFalse(Hash::check(self::CLIENT_SUPPLIED_PASSWORD, $user->password));
        $this->assertTrue(Hash::check($response->json('data.temporary_password'), $user->password));
    }

    public function test_exceeding_the_user_creation_limit_returns_too_many_requests(): void
    {
        config(['rate_limits.user_creation.max_attempts_per_minute' => 2]);

        $this->signInAsAdmin();

        $this->postJson(self::USERS_ROUTE, [])->assertUnprocessable();
        $this->postJson(self::USERS_ROUTE, [])->assertUnprocessable();

        $this->postJson(self::USERS_ROUTE, $this->payload())
            ->assertTooManyRequests()
            ->assertJsonPath('code', ErrorCode::TooManyRequests->value);

        $this->assertSame(0, User::query()->where('email', self::NEW_USER_EMAIL)->count());
    }

    private function signInAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        $this->signInThroughApi($admin)->assertOk();

        return $admin;
    }

    private function payload(
        string $name = self::NEW_USER_NAME,
        string $email = self::NEW_USER_EMAIL,
        string $role = UserRole::Technician->value,
    ): array {
        return [
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];
    }

    private function ignoreExistingRowsDuringValidation(): void
    {
        Validator::setPresenceVerifier(new class implements PresenceVerifierInterface
        {
            public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
            {
                return 0;
            }

            public function getMultiCount($collection, $column, array $values, array $extra = []): int
            {
                return 0;
            }
        });
    }
}
