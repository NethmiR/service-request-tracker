<?php

// Calls every endpoint that returns a body and walks each JSON tree for fields that must never leave the server.
// Emails and temporary passwords are allowed only on the endpoints that are meant to return them.

namespace Tests\Feature\Security;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class SensitiveFieldExposureTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use InteractsWithStoredSessions;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string API_PREFIX = 'api/';

    private const string GET_METHOD = 'GET';

    private const string SERVICE_REQUEST_PARAMETER = '{serviceRequest}';

    private const string USER_PARAMETER = '{user}';

    private const string REMARK = 'Handled during the exposure check.';

    private const string NEW_USER_EMAIL = 'new.technician@example.com';

    private const array FORBIDDEN_KEYS = [
        'password',
        'remember_token',
        'payload',
        'password_changed_at',
        'service_request_id',
        'tag_id',
        'priority_tag_id',
    ];

    private const string FORBIDDEN_KEY_SUFFIX = '_user_id';

    private const string EMAIL_KEY = 'email';

    private const string TEMPORARY_PASSWORD_KEY = 'temporary_password';

    private const array ROUTES_ALLOWED_TO_RETURN_EMAIL = [
        'auth.sign-in',
        'auth.user',
        'users.index',
        'users.show',
        'users.store',
        'users.password-reset',
    ];

    private const array ROUTES_ALLOWED_TO_RETURN_TEMPORARY_PASSWORD = [
        'users.store',
        'users.password-reset',
    ];

    private const int STORED_SESSIONS_PER_USER = 2;

    private User $admin;

    private User $technician;

    private User $requester;

    private array $bodies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->technician = User::factory()->technician()->create();
        $this->requester = User::factory()->requester()->create();

        $this->createServiceRequestFor($this->requester, ServiceRequestTag::Hardware);
        $handled = $this->createServiceRequestFor($this->requester, ServiceRequestTag::Network);
        $this->assignThroughWorkflow($handled, $this->technician);

        for ($session = 0; $session < self::STORED_SESSIONS_PER_USER; $session++) {
            $this->storeSession($this->technician);
            $this->storeSession($this->requester);
        }
    }

    public function test_no_response_exposes_sensitive_fields(): void
    {
        $this->collect('auth.sign-in', $this->signInThroughApi($this->admin)->assertOk());

        $this->collectEveryReadingRoute();
        $this->collectWritingRoutes();

        $this->assertNotEmpty($this->bodies);

        $sessionIds = DB::table(config('session.table'))->pluck('id')->all();

        foreach ($this->bodies as $name => $body) {
            $this->assertBodyIsSafe($name, $body, $sessionIds);
        }
    }

    private function collectEveryReadingRoute(): void
    {
        $serviceRequest = ServiceRequest::query()->firstOrFail();

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! $this->isReadingApiRoute($route)) {
                continue;
            }

            $uri = '/'.strtr($route->uri(), [
                self::SERVICE_REQUEST_PARAMETER => (string) $serviceRequest->id,
                self::USER_PARAMETER => (string) $this->technician->id,
            ]);

            $this->collect((string) $route->getName(), $this->getJson($uri)->assertOk());
        }
    }

    private function collectWritingRoutes(): void
    {
        $created = $this->postJson('/api/service-requests', [
            'title' => 'Exposure check request',
            'description' => 'Created while checking response fields.',
            'tags' => [ServiceRequestTag::Software->value],
            'priority' => ServiceRequestPriority::High->value,
        ])->assertCreated();
        $this->collect('service-requests.store', $created);

        $serviceRequestId = $created->json('data.id');

        $this->collect('service-requests.assign', $this->postJson(
            "/api/service-requests/{$serviceRequestId}/assignment",
            ['remark' => self::REMARK],
        )->assertOk());

        $this->collect('service-requests.status', $this->patchJson(
            "/api/service-requests/{$serviceRequestId}/status",
            ['status' => ServiceRequestStatus::InProgress->value, 'remark' => self::REMARK],
        )->assertOk());

        $this->collect('service-requests.reassign', $this->postJson(
            "/api/service-requests/{$serviceRequestId}/reassignment",
            ['assignee_id' => $this->technician->id, 'remark' => self::REMARK],
        )->assertOk());

        $this->collect('users.store', $this->postJson('/api/users', [
            'name' => 'New Technician',
            'email' => self::NEW_USER_EMAIL,
            'role' => UserRole::Technician->value,
        ])->assertCreated());

        $this->collect('users.password-reset', $this->postJson(
            "/api/users/{$this->requester->id}/password-reset",
        )->assertOk());
    }

    private function isReadingApiRoute(RegisteredRoute $route): bool
    {
        return str_starts_with($route->uri(), self::API_PREFIX)
            && in_array(self::GET_METHOD, $route->methods(), true);
    }

    private function collect(string $name, TestResponse $response): void
    {
        $this->bodies[$name] = (array) $response->json();
    }

    private function assertBodyIsSafe(string $name, array $body, array $sessionIds): void
    {
        array_walk_recursive($body, function (mixed $value) use ($name, $sessionIds): void {
            if (is_string($value)) {
                $this->assertNotContains($value, $sessionIds, "{$name} returned a raw session id.");
            }
        });

        $this->assertKeysAreSafe($name, $body);
    }

    private function assertKeysAreSafe(string $name, array $node): void
    {
        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $this->assertNotContains($key, self::FORBIDDEN_KEYS, "{$name} returned the field {$key}.");
                $this->assertStringEndsNotWith(self::FORBIDDEN_KEY_SUFFIX, $key, "{$name} returned the field {$key}.");

                if ($key === self::EMAIL_KEY) {
                    $this->assertContains($name, self::ROUTES_ALLOWED_TO_RETURN_EMAIL, "{$name} returned an email.");
                }

                if ($key === self::TEMPORARY_PASSWORD_KEY) {
                    $this->assertContains($name, self::ROUTES_ALLOWED_TO_RETURN_TEMPORARY_PASSWORD, "{$name} returned a temporary password.");
                }
            }

            if (is_array($value)) {
                $this->assertKeysAreSafe($name, $value);
            }
        }
    }
}
