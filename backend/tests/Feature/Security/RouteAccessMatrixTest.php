<?php

// Holds the expected access of every API route for guests, pending users and each role, built from the Phase 8 route table.
// Fails when a route has no entry, so every new route must be classified before it ships.

namespace Tests\Feature\Security;

use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class RouteAccessMatrixTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string API_PREFIX = 'api/';

    private const string SIGN_IN_ROUTE_NAME = 'auth.sign-in';

    private const int UNKNOWN_RECORD_ID = 999999;

    private const string UNKNOWN_SESSION_IDENTIFIER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string SERVICE_REQUEST_PARAMETER = '{serviceRequest}';

    private const string USER_PARAMETER = '{user}';

    private const string SESSION_IDENTIFIER_PARAMETER = '{sessionIdentifier}';

    private const string EXISTING_RECORDS = 'existing';

    private const string UNKNOWN_RECORDS = 'unknown';

    private const array ALL_ROLES = [UserRole::Admin, UserRole::Technician, UserRole::Requester];

    private const array STAFF_ROLES = [UserRole::Admin, UserRole::Technician];

    private const array ADMIN_ONLY = [UserRole::Admin];

    private const array ROUTES = [
        'auth.sign-in' => ['method' => 'POST', 'uri' => 'api/auth/sign-in', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => true],
        'auth.user' => ['method' => 'GET', 'uri' => 'api/auth/user', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => true],
        'auth.sign-out' => ['method' => 'POST', 'uri' => 'api/auth/sign-out', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => true],
        'auth.password' => ['method' => 'PATCH', 'uri' => 'api/auth/password', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => true],
        'sessions.index' => ['method' => 'GET', 'uri' => 'api/sessions', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'sessions.destroy-others' => ['method' => 'DELETE', 'uri' => 'api/sessions/others', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'sessions.destroy' => ['method' => 'DELETE', 'uri' => 'api/sessions/{sessionIdentifier}', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'overview.show' => ['method' => 'GET', 'uri' => 'api/overview', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.store' => ['method' => 'POST', 'uri' => 'api/service-requests', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'service-requests.mine' => ['method' => 'GET', 'uri' => 'api/service-requests/mine', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'service-requests.mine.assignees' => ['method' => 'GET', 'uri' => 'api/service-requests/mine/assignees', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'service-requests.index' => ['method' => 'GET', 'uri' => 'api/service-requests', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.assignees' => ['method' => 'GET', 'uri' => 'api/service-requests/assignees', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.assign' => ['method' => 'POST', 'uri' => 'api/service-requests/{serviceRequest}/assignment', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.reassign' => ['method' => 'POST', 'uri' => 'api/service-requests/{serviceRequest}/reassignment', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.status' => ['method' => 'PATCH', 'uri' => 'api/service-requests/{serviceRequest}/status', 'roles' => self::STAFF_ROLES, 'allowed_while_pending' => false],
        'service-requests.show' => ['method' => 'GET', 'uri' => 'api/service-requests/{serviceRequest}', 'roles' => self::ALL_ROLES, 'allowed_while_pending' => false],
        'users.index' => ['method' => 'GET', 'uri' => 'api/users', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.store' => ['method' => 'POST', 'uri' => 'api/users', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.show' => ['method' => 'GET', 'uri' => 'api/users/{user}', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.password-reset' => ['method' => 'POST', 'uri' => 'api/users/{user}/password-reset', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.sessions.index' => ['method' => 'GET', 'uri' => 'api/users/{user}/sessions', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.sessions.destroy-all' => ['method' => 'DELETE', 'uri' => 'api/users/{user}/sessions', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
        'users.sessions.destroy' => ['method' => 'DELETE', 'uri' => 'api/users/{user}/sessions/{sessionIdentifier}', 'roles' => self::ADMIN_ONLY, 'allowed_while_pending' => false],
    ];

    private ServiceRequest $storedServiceRequest;

    private User $storedUser;

    private string $storedSessionIdentifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storedUser = User::factory()->requester()->create();
        $this->storedServiceRequest = ServiceRequest::factory()->requestedBy($this->storedUser)->create();
        $this->storedSessionIdentifier = $this->sessionIdentifierFor($this->storeSession($this->storedUser));
    }

    public static function authenticatedRoutes(): array
    {
        return self::routeNamesWhere(fn (string $name): bool => $name !== self::SIGN_IN_ROUTE_NAME);
    }

    public static function routesBlockedWhilePending(): array
    {
        return self::routeNamesWhere(
            fn (string $name, array $entry): bool => $name !== self::SIGN_IN_ROUTE_NAME && ! $entry['allowed_while_pending'],
        );
    }

    public static function roleRestrictedRoutes(): array
    {
        return self::routeNamesWhere(fn (string $name, array $entry): bool => $entry['roles'] !== self::ALL_ROLES);
    }

    public function test_every_api_route_has_an_access_entry(): void
    {
        $registered = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), self::API_PREFIX)) {
                continue;
            }

            $name = (string) $route->getName();

            $this->assertArrayHasKey($name, self::ROUTES, "The route {$route->uri()} has no access entry.");
            $this->assertSame(self::ROUTES[$name]['uri'], $route->uri(), $name);
            $this->assertSame(self::ROUTES[$name]['method'], $this->primaryMethod($route), $name);

            $registered[] = $name;
        }

        $this->assertEqualsCanonicalizing(array_keys(self::ROUTES), $registered);
    }

    #[DataProvider('authenticatedRoutes')]
    public function test_guest_is_rejected_as_unauthenticated(string $name): void
    {
        foreach ($this->sampleUris($name) as $uri) {
            $this->json(self::ROUTES[$name]['method'], $uri)
                ->assertUnauthorized()
                ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
        }
    }

    #[DataProvider('routesBlockedWhilePending')]
    public function test_user_with_a_pending_password_change_is_rejected(string $name): void
    {
        foreach (self::ALL_ROLES as $role) {
            $this->switchToFreshClient();
            $this->signInThroughApi($this->userWithRole($role)->withPendingPasswordChange()->create())->assertOk();

            foreach ($this->sampleUris($name) as $uri) {
                $this->json(self::ROUTES[$name]['method'], $uri)
                    ->assertForbidden()
                    ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
            }
        }
    }

    #[DataProvider('roleRestrictedRoutes')]
    public function test_roles_outside_the_gate_are_forbidden(string $name): void
    {
        foreach (self::ALL_ROLES as $role) {
            if (in_array($role, self::ROUTES[$name]['roles'], true)) {
                continue;
            }

            $this->switchToFreshClient();
            $this->signInThroughApi($this->userWithRole($role)->create())->assertOk();

            foreach ($this->sampleUris($name) as $uri) {
                $this->json(self::ROUTES[$name]['method'], $uri)
                    ->assertForbidden()
                    ->assertJsonPath('code', ErrorCode::Forbidden->value);
            }
        }
    }

    private static function routeNamesWhere(callable $filter): array
    {
        $names = [];

        foreach (self::ROUTES as $name => $entry) {
            if ($filter($name, $entry)) {
                $names[$name] = [$name];
            }
        }

        return $names;
    }

    private function sampleUris(string $name): array
    {
        $template = self::ROUTES[$name]['uri'];

        $existing = $this->fillParameters($template, self::EXISTING_RECORDS);
        $unknown = $this->fillParameters($template, self::UNKNOWN_RECORDS);

        return array_values(array_unique(['/'.$existing, '/'.$unknown]));
    }

    private function fillParameters(string $template, string $records): string
    {
        $existing = $records === self::EXISTING_RECORDS;

        return strtr($template, [
            self::SERVICE_REQUEST_PARAMETER => (string) ($existing ? $this->storedServiceRequest->id : self::UNKNOWN_RECORD_ID),
            self::USER_PARAMETER => (string) ($existing ? $this->storedUser->id : self::UNKNOWN_RECORD_ID),
            self::SESSION_IDENTIFIER_PARAMETER => $existing ? $this->storedSessionIdentifier : self::UNKNOWN_SESSION_IDENTIFIER,
        ]);
    }

    private function userWithRole(UserRole $role): UserFactory
    {
        return match ($role) {
            UserRole::Admin => User::factory()->admin(),
            UserRole::Technician => User::factory()->technician(),
            UserRole::Requester => User::factory()->requester(),
        };
    }

    private function primaryMethod(RegisteredRoute $route): string
    {
        return $route->methods()[0];
    }
}
