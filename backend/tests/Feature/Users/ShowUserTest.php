<?php

// Verifies viewing one user from the admin user management area.
// The response holds exactly the same whitelisted fields as the users list.

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class ShowUserTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string USERS_ROUTE = '/api/users';

    public function test_admin_receives_exactly_the_whitelisted_fields(): void
    {
        $this->signInThroughApi(User::factory()->admin()->create())->assertOk();

        $user = User::factory()->technician()->withPendingPasswordChange()->create();

        $this->getJson(self::USERS_ROUTE.'/'.$user->id)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'password_change_required' => true,
                    'created_at' => $user->created_at->toIso8601ZuluString(),
                ],
            ]);
    }
}
