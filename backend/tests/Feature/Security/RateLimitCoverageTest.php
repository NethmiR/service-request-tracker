<?php

// Verifies that every named limiter is registered and that every authenticated API route is rate limited.
// Also proves that the shared API limiter counts per user and answers with the standard 429 error.

namespace Tests\Feature\Security;

use App\Enums\ErrorCode;
use App\Enums\RateLimiterName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class RateLimitCoverageTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string API_PREFIX = 'api/';

    private const string SIGN_IN_ROUTE_NAME = 'auth.sign-in';

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const int LOWERED_API_LIMIT = 3;

    private const array DEDICATED_LIMITERS = [
        'auth.password' => RateLimiterName::PasswordChange,
        'users.store' => RateLimiterName::UserCreation,
        'users.password-reset' => RateLimiterName::AdminPasswordReset,
    ];

    public function test_every_named_limiter_is_registered(): void
    {
        foreach (RateLimiterName::cases() as $limiter) {
            $this->assertNotNull(RateLimiter::limiter($limiter->value), $limiter->value);
        }
    }

    public function test_every_authenticated_api_route_uses_the_api_limiter(): void
    {
        $unlimited = [];

        foreach ($this->apiRoutes() as $route) {
            if ($route->getName() === self::SIGN_IN_ROUTE_NAME) {
                continue;
            }

            if (! in_array(ThrottleRequests::using(RateLimiterName::Api->value), $route->gatherMiddleware(), true)) {
                $unlimited[] = $route->uri();
            }
        }

        $this->assertSame([], $unlimited);
    }

    public function test_sensitive_routes_use_their_dedicated_limiter(): void
    {
        $routes = [];

        foreach ($this->apiRoutes() as $route) {
            $routes[(string) $route->getName()] = $route;
        }

        foreach (self::DEDICATED_LIMITERS as $name => $limiter) {
            $this->assertArrayHasKey($name, $routes);
            $this->assertContains(ThrottleRequests::using($limiter->value), $routes[$name]->gatherMiddleware(), $name);
            $this->assertContains(ThrottleRequests::using(RateLimiterName::Api->value), $routes[$name]->gatherMiddleware(), $name);
        }
    }

    public function test_api_limiter_counts_each_user_separately(): void
    {
        config(['rate_limits.api.max_requests_per_minute' => self::LOWERED_API_LIMIT]);

        $this->signInThroughApi(User::factory()->create())->assertOk();
        $this->useFullAllowance();

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', ErrorCode::TooManyRequests->value);

        $this->switchToFreshClient();
        $this->signInThroughApi(User::factory()->create())->assertOk();
        $this->useFullAllowance();
    }

    private function useFullAllowance(): void
    {
        for ($request = 0; $request < self::LOWERED_API_LIMIT; $request++) {
            $this->getJson(self::CURRENT_USER_ROUTE)->assertOk();
        }
    }

    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RegisteredRoute $route): bool => str_starts_with($route->uri(), self::API_PREFIX),
        ));
    }
}
