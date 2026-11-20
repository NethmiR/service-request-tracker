<?php

// Verifies the current user endpoint.
// It exposes only safe identity fields and rejects guests and stray bearer tokens without a server error.

namespace Tests\Feature\Auth;

use App\Enums\ErrorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string CURRENT_USER_ROUTE = '/api/auth/user';

    private const string STRAY_TOKEN = '1|not-a-real-token';

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_signed_in_user_receives_only_safe_fields(): void
    {
        $user = User::factory()->admin()->create();

        $this->signInThroughApi($user)->assertOk();

        $this->getJson(self::CURRENT_USER_ROUTE)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'password_change_required' => false,
                ],
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.password_changed_at')
            ->assertJsonMissingPath('data.created_at')
            ->assertJsonMissingPath('data.updated_at');
    }

    public function test_stray_bearer_token_without_a_stateful_origin_is_unauthenticated(): void
    {
        $this->flushHeaders();

        $this->withToken(self::STRAY_TOKEN)
            ->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }

    public function test_stray_bearer_token_from_the_frontend_is_unauthenticated(): void
    {
        $this->withToken(self::STRAY_TOKEN)
            ->getJson(self::CURRENT_USER_ROUTE)
            ->assertUnauthorized()
            ->assertJsonPath('code', ErrorCode::Unauthenticated->value);
    }
}
