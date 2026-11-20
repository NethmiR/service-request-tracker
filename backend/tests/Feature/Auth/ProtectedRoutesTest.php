<?php

// Walks every registered API route and checks its middleware.
// New routes added in later phases are checked automatically without changing this test.

namespace Tests\Feature\Auth;

use App\Enums\GateAbility;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProtectedRoutesTest extends TestCase
{
    private const string API_PREFIX = 'api/';

    private const string AUTHENTICATION_MIDDLEWARE = 'auth:sanctum';

    private const string SIGN_IN_ROUTE_NAME = 'auth.sign-in';

    private const string USER_MANAGEMENT_ROUTE_PREFIX = 'users.';

    private const array ALLOWED_WHILE_PASSWORD_CHANGE_PENDING = [
        'auth.user',
        'auth.sign-out',
        'auth.password',
    ];

    public function test_every_api_route_except_sign_in_requires_authentication(): void
    {
        $unprotected = [];

        foreach ($this->apiRoutes() as $route) {
            if ($route->getName() === self::SIGN_IN_ROUTE_NAME) {
                continue;
            }

            if (! in_array(self::AUTHENTICATION_MIDDLEWARE, $route->gatherMiddleware(), true)) {
                $unprotected[] = $route->uri();
            }
        }

        $this->assertSame([], $unprotected);
    }

    public function test_every_protected_route_blocks_pending_password_changes_unless_allowed(): void
    {
        $unguarded = [];

        foreach ($this->apiRoutes() as $route) {
            if ($route->getName() === self::SIGN_IN_ROUTE_NAME
                || in_array($route->getName(), self::ALLOWED_WHILE_PASSWORD_CHANGE_PENDING, true)) {
                continue;
            }

            if (! in_array(EnsurePasswordChanged::class, $route->gatherMiddleware(), true)) {
                $unguarded[] = $route->uri();
            }
        }

        $this->assertSame([], $unguarded);
    }

    public function test_every_user_management_route_requires_the_manage_users_gate(): void
    {
        $gateMiddleware = Authorize::using(GateAbility::ManageUsers->value);

        $userManagementRoutes = array_filter(
            $this->apiRoutes(),
            fn (RegisteredRoute $route): bool => str_starts_with((string) $route->getName(), self::USER_MANAGEMENT_ROUTE_PREFIX),
        );

        $this->assertNotEmpty($userManagementRoutes);

        $unguarded = [];

        foreach ($userManagementRoutes as $route) {
            if (! in_array($gateMiddleware, $route->gatherMiddleware(), true)) {
                $unguarded[] = $route->uri();
            }
        }

        $this->assertSame([], $unguarded);
    }

    /**
     * @return list<RegisteredRoute>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RegisteredRoute $route): bool => str_starts_with($route->uri(), self::API_PREFIX),
        ));
    }
}
