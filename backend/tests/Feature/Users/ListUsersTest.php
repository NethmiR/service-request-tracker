<?php

// Verifies the paginated users list for admins.
// Covers the envelope, the newest first order, the page size, the exposed fields and pages beyond the last one.

namespace Tests\Feature\Users;

use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string USERS_ROUTE = '/api/users';

    private const array USER_FIELDS = ['id', 'name', 'email', 'role', 'password_change_required', 'created_at'];

    private const array HIDDEN_FIELDS = ['password', 'remember_token', 'password_changed_at', 'updated_at'];

    private const string ISO_8601_UTC_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const int USERS_ON_SECOND_PAGE = 2;

    private const int PAGE_BEYOND_THE_LAST = 99;

    public function test_admin_receives_the_paginated_envelope(): void
    {
        $this->signInAsAdmin();

        $this->getJson(self::USERS_ROUTE)
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', UserManagementService::PAGE_SIZE)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_users_are_listed_newest_first_across_pages(): void
    {
        $admin = $this->signInAsAdmin();
        User::factory()->count(UserManagementService::PAGE_SIZE + self::USERS_ON_SECOND_PAGE - 1)->create();

        $expectedIds = User::query()->orderByDesc('id')->pluck('id')->all();

        $firstPage = $this->getJson(self::USERS_ROUTE)
            ->assertOk()
            ->assertJsonCount(UserManagementService::PAGE_SIZE, 'data')
            ->json('data.*.id');

        $secondPage = $this->getJson(self::USERS_ROUTE.'?page=2')
            ->assertOk()
            ->assertJsonCount(self::USERS_ON_SECOND_PAGE, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->json('data.*.id');

        $this->assertSame(array_slice($expectedIds, 0, UserManagementService::PAGE_SIZE), $firstPage);
        $this->assertSame(array_slice($expectedIds, UserManagementService::PAGE_SIZE), $secondPage);
        $this->assertSame($admin->id, end($secondPage));
    }

    public function test_each_user_exposes_only_the_whitelisted_fields(): void
    {
        $admin = $this->signInAsAdmin();
        User::factory()->technician()->withPendingPasswordChange()->create();

        $response = $this->getJson(self::USERS_ROUTE)->assertOk();

        foreach ($response->json('data') as $user) {
            $this->assertEqualsCanonicalizing(self::USER_FIELDS, array_keys($user));
            $this->assertMatchesRegularExpression(self::ISO_8601_UTC_PATTERN, $user['created_at']);

            foreach (self::HIDDEN_FIELDS as $hiddenField) {
                $this->assertArrayNotHasKey($hiddenField, $user);
            }
        }

        $this->assertStringNotContainsString($admin->password, $response->getContent());
    }

    public function test_password_change_status_is_reported_per_user(): void
    {
        $this->signInAsAdmin();
        $pendingUser = User::factory()->withPendingPasswordChange()->create();

        $this->getJson(self::USERS_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.0.id', $pendingUser->id)
            ->assertJsonPath('data.0.password_change_required', true)
            ->assertJsonPath('data.1.password_change_required', false);
    }

    public function test_page_beyond_the_last_returns_no_users(): void
    {
        $this->signInAsAdmin();

        $this->getJson(self::USERS_ROUTE.'?page='.self::PAGE_BEYOND_THE_LAST)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', self::PAGE_BEYOND_THE_LAST)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    private function signInAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        $this->signInThroughApi($admin)->assertOk();

        return $admin;
    }
}
