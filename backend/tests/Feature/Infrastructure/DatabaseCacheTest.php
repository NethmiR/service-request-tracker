<?php

// Verifies that the database cache store works with the cache table created by the migration.
// A failed sign in must store its rate limit counter and the dummy password hash in that table.

namespace Tests\Feature\Infrastructure;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class DatabaseCacheTest extends TestCase
{
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string DATABASE_STORE = 'database';

    private const string CACHE_TABLE = 'cache';

    private const string SIGN_IN_COUNTER_KEY_PATTERN = '%sign-in:%';

    private const string DUMMY_HASH_KEY_PATTERN = '%auth.dummy_password_hash';

    private const string UNKNOWN_EMAIL = 'nobody@example.com';

    private const string WRONG_PASSWORD = 'Wrong-Password-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => self::DATABASE_STORE]);

        $this->app->forgetInstance(RateLimiter::class);
        RateLimiterFacade::clearResolvedInstance(RateLimiter::class);
    }

    public function test_failed_sign_in_writes_its_counter_and_the_dummy_hash_to_the_cache_table(): void
    {
        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => self::UNKNOWN_EMAIL,
            'password' => self::WRONG_PASSWORD,
        ])->assertUnauthorized();

        $this->assertTrue(DB::table(self::CACHE_TABLE)->where('key', 'like', self::SIGN_IN_COUNTER_KEY_PATTERN)->exists());
        $this->assertTrue(DB::table(self::CACHE_TABLE)->where('key', 'like', self::DUMMY_HASH_KEY_PATTERN)->exists());
    }
}
