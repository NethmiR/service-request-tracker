<?php

// Verifies who can reach the user management routes and in which order the checks run.
// Guests, non admins and admins with a pending password change are rejected before an unknown user id can be revealed.

namespace Tests\Feature\Users;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class UserManagementAccessTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string USER_MANAGEMENT_ROUTE_PREFIX = 'users.';

    private const string USER_PLACEHOLDER = '{user}';

    private const string SAMPLE_SESSION_IDENTIFIER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const int UNKNOWN_USER_ID = 999999;

    private const string NON_NUMERIC_USER_ID = 'abc';

    private const array ROUTES = [
        'users.index' => ['GET', '/api/users'],
        'users.store' => ['POST', '/api/users'],
        'users.show' => ['GET', '/api/users/'.self::USER_PLACEHOLDER],
        'users.password-reset' => ['POST', '/api/users/'.self::USER_PLACEHOLDER.'/password-reset'],
        'users.sessions.index' => ['GET', '/api/users/'.self::USER_PLACEHOLDER.'/sessions'],
        'users.sessions.destroy-all' => ['DELETE', '/api/users/'.self::USER_PLACEHOLDER.'/sessions'],
        'users.sessions.destroy' => [
            'DELETE',
            '/api/users/'.self::USER_PLACEHOLDER.'/sessions/'.self::SAMPLE_SESSION_IDENTIFIER,
        ],
    ];

    public static function userManagementRoutes(): array
    {
        return self::ROUTES;
    }

    public static function userBoundRoutes(): array
    {
        return array_filter(
            self::ROUTES,
            fn (array $route): bool => str_contains($route[1], self::USER_PLACEHOLDER),
        );
    }

    public function test_every_user_management_route_is_covered(): void
    {
        $registeredNames = array_values(array_filter(
            array_map(
                fn (RegisteredRoute $route): string => (string) $route->getName(),
                Route::getRoutes()->getRoutes(),
            ),
            fn (string $name): bool => str_starts_with($name, self::USER_MANAGEMENT_ROUTE_PREFIX),
        ));

        $this->assertEqualsCanonicalizing(array_keys(self::ROUTES), $registeredNames);
    }

    #[DataProvider('userManagementRoutes')]
    public function test_guest_is_unauthenticated(string $method, string $uri): void
    {
        $existingUser = User::factory()->create();

        $this->json($method, $this->resolveUri($uri, $existingUser->id))
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    #[DataProvider('userManagementRoutes')]
    public function test_technician_is_forbidden_for_existing_and_unknown_users(string $method, string $uri): void
    {
        $this->assertForbiddenForExistingAndUnknownUsers(User::factory()->technician()->create(), $method, $uri);
    }

    #[DataProvider('userManagementRoutes')]
    public function test_requester_is_forbidden_for_existing_and_unknown_users(string $method, string $uri): void
    {
        $this->assertForbiddenForExistingAndUnknownUsers(User::factory()->requester()->create(), $method, $uri);
    }

    #[DataProvider('userManagementRoutes')]
    public function test_admin_with_a_pending_password_change_is_forbidden_for_existing_and_unknown_users(
        string $method,
        string $uri,
    ): void {
        $admin = User::factory()->admin()->withPendingPasswordChange()->create();

        $this->signInThroughApi($admin)->assertOk();

        foreach ([$admin->id, self::UNKNOWN_USER_ID] as $userId) {
            $this->json($method, $this->resolveUri($uri, $userId))
                ->assertForbidden()
                ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
        }
    }

    #[DataProvider('userBoundRoutes')]
    public function test_admin_requesting_an_unknown_user_is_not_found(string $method, string $uri): void
    {
        $this->signInThroughApi(User::factory()->admin()->create())->assertOk();

        $this->json($method, $this->resolveUri($uri, self::UNKNOWN_USER_ID))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    #[DataProvider('userBoundRoutes')]
    public function test_non_numeric_user_id_is_not_found(string $method, string $uri): void
    {
        $this->signInThroughApi(User::factory()->admin()->create())->assertOk();

        $this->json($method, str_replace(self::USER_PLACEHOLDER, self::NON_NUMERIC_USER_ID, $uri))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    private function assertForbiddenForExistingAndUnknownUsers(User $user, string $method, string $uri): void
    {
        $existingUser = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();

        foreach ([$existingUser->id, self::UNKNOWN_USER_ID] as $userId) {
            $this->json($method, $this->resolveUri($uri, $userId))
                ->assertForbidden()
                ->assertJsonPath('code', ErrorCode::Forbidden->value);
        }
    }

    private function resolveUri(string $uri, int $userId): string
    {
        return str_replace(self::USER_PLACEHOLDER, (string) $userId, $uri);
    }
}
