<?php

// Verifies the flags of the session and XSRF-TOKEN cookies and that stored session payloads are encrypted.
// Also proves that signing out regenerates the CSRF token as section 5.2 of the main plan requires.

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithStoredSessions;
use Tests\TestCase;

class SessionCookieTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithStoredSessions;
    use RefreshDatabase;

    private const string XSRF_COOKIE = 'XSRF-TOKEN';

    private const string SAME_SITE_LAX = 'lax';

    private const string COOKIE_PATH = '/';

    private const string AUTHENTICATION_KEY_PREFIX = 'login_web';

    private const string SIGN_OUT_ROUTE = '/api/auth/sign-out';

    public function test_session_cookie_is_http_only_lax_and_does_not_carry_the_raw_session_id(): void
    {
        $user = User::factory()->create();

        $cookie = $this->signInThroughApi($user)->assertOk()->getCookie(config('session.cookie'), false);

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(self::SAME_SITE_LAX, $cookie->getSameSite());
        $this->assertSame(self::COOKIE_PATH, $cookie->getPath());
        $this->assertNotSame($this->currentSessionIdFor($user), $cookie->getValue());
    }

    public function test_cookies_carry_the_secure_flag_when_secure_cookies_are_enabled(): void
    {
        config(['session.secure' => true]);

        $response = $this->signInThroughApi(User::factory()->create())->assertOk();

        $this->assertTrue($response->getCookie(config('session.cookie'), false)->isSecure());
        $this->assertTrue($response->getCookie(self::XSRF_COOKIE, false)->isSecure());
    }

    public function test_xsrf_cookie_is_readable_by_the_frontend_and_lax(): void
    {
        $cookie = $this->getJson(self::CSRF_COOKIE_ROUTE)
            ->assertNoContent()
            ->getCookie(self::XSRF_COOKIE, false);

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame(self::SAME_SITE_LAX, $cookie->getSameSite());
    }

    public function test_stored_session_payload_is_encrypted(): void
    {
        $user = User::factory()->create();

        $this->signInThroughApi($user)->assertOk();

        $payload = (string) DB::table(config('session.table'))
            ->where('id', $this->currentSessionIdFor($user))
            ->value('payload');
        $decoded = (string) base64_decode($payload, true);

        $this->assertStringNotContainsString(self::AUTHENTICATION_KEY_PREFIX, $decoded);
        $this->assertStringNotContainsString('":'.$user->id, $decoded);
        $this->assertStringContainsString(self::AUTHENTICATION_KEY_PREFIX, app('encrypter')->decrypt($decoded));
    }

    public function test_signing_out_regenerates_the_csrf_token(): void
    {
        $tokenBeforeSignOut = $this->signInThroughApi(User::factory()->create())
            ->assertOk()
            ->getCookie(self::XSRF_COOKIE)
            ->getValue();

        $tokenAfterSignOut = $this->postJson(self::SIGN_OUT_ROUTE)
            ->assertNoContent()
            ->getCookie(self::XSRF_COOKIE)
            ->getValue();

        $this->assertNotSame($tokenBeforeSignOut, $tokenAfterSignOut);
    }
}
