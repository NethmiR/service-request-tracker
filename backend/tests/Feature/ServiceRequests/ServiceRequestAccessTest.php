<?php

// Verifies who can reach the service request routes and in which order the checks run.
// Guests and users with a pending password change are rejected before an unknown request id can be revealed.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ServiceRequestAccessTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string SERVICE_REQUEST_ROUTE_PREFIX = 'service-requests.';

    private const string SERVICE_REQUEST_PLACEHOLDER = '{serviceRequest}';

    private const string SHOW_ROUTE = '/api/service-requests/'.self::SERVICE_REQUEST_PLACEHOLDER;

    private const int UNKNOWN_SERVICE_REQUEST_ID = 999999;

    private const string NON_NUMERIC_SERVICE_REQUEST_ID = 'abc';

    private const array ROUTES = [
        'service-requests.store' => ['POST', '/api/service-requests'],
        'service-requests.mine' => ['GET', '/api/service-requests/mine'],
        'service-requests.show' => ['GET', self::SHOW_ROUTE],
    ];

    public static function serviceRequestRoutes(): array
    {
        return self::ROUTES;
    }

    public function test_every_service_request_route_is_covered(): void
    {
        $registeredNames = array_values(array_filter(
            array_map(
                fn (RegisteredRoute $route): string => (string) $route->getName(),
                Route::getRoutes()->getRoutes(),
            ),
            fn (string $name): bool => str_starts_with($name, self::SERVICE_REQUEST_ROUTE_PREFIX),
        ));

        $this->assertEqualsCanonicalizing(array_keys(self::ROUTES), $registeredNames);
    }

    #[DataProvider('serviceRequestRoutes')]
    public function test_guest_is_unauthenticated(string $method, string $uri): void
    {
        $serviceRequest = $this->createServiceRequestFor(User::factory()->create());

        $this->json($method, $this->resolveUri($uri, (string) $serviceRequest->id))
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    #[DataProvider('serviceRequestRoutes')]
    public function test_user_with_a_pending_password_change_is_forbidden_for_existing_and_unknown_requests(
        string $method,
        string $uri,
    ): void {
        $user = User::factory()->admin()->withPendingPasswordChange()->create();
        $serviceRequest = $this->createServiceRequestFor($user);

        $this->signInAs($user);

        foreach ([$serviceRequest->id, self::UNKNOWN_SERVICE_REQUEST_ID] as $serviceRequestId) {
            $this->json($method, $this->resolveUri($uri, (string) $serviceRequestId))
                ->assertForbidden()
                ->assertJsonPath('code', ErrorCode::PasswordChangeRequired->value);
        }
    }

    public function test_unknown_request_id_is_not_found(): void
    {
        $this->signInAs(User::factory()->admin()->create());

        $this->getJson($this->resolveUri(self::SHOW_ROUTE, (string) self::UNKNOWN_SERVICE_REQUEST_ID))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    public function test_non_numeric_request_id_is_not_found(): void
    {
        $this->signInAs(User::factory()->admin()->create());

        $this->getJson($this->resolveUri(self::SHOW_ROUTE, self::NON_NUMERIC_SERVICE_REQUEST_ID))
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value);
    }

    private function resolveUri(string $uri, string $serviceRequestId): string
    {
        return str_replace(self::SERVICE_REQUEST_PLACEHOLDER, $serviceRequestId, $uri);
    }
}
