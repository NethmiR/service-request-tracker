<?php

// Verifies that the forwarded client address is used only when the request comes from a configured proxy.
// The static proxy setting is flushed after each test so it never leaks into other tests.

namespace Tests\Feature\Security;

use App\Enums\AuthAuditEvent;
use App\Support\Http\TrustedProxyConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\Concerns\InteractsWithAuditLogs;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use InteractsWithAuditLogs;
    use InteractsWithFrontendSession;
    use RefreshDatabase;

    private const string PROXY_ADDRESS = '10.0.0.1';

    private const string UNTRUSTED_PEER_ADDRESS = '10.0.0.2';

    private const string CLIENT_ADDRESS = '203.0.113.9';

    private const string FORWARDED_FOR_HEADER = 'X-Forwarded-For';

    private const string UNKNOWN_EMAIL = 'nobody@example.com';

    private const string WRONG_PASSWORD = 'Wrong-Password-2026!';

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_address_is_ignored_when_no_proxy_is_trusted(): void
    {
        $this->assertSame(self::PROXY_ADDRESS, $this->recordedAddressForFailedSignInFrom(self::PROXY_ADDRESS));
    }

    public function test_forwarded_address_is_used_when_the_peer_is_a_trusted_proxy(): void
    {
        $this->trustConfiguredProxies();

        $this->assertSame(self::CLIENT_ADDRESS, $this->recordedAddressForFailedSignInFrom(self::PROXY_ADDRESS));
    }

    public function test_forwarded_address_is_ignored_when_the_peer_is_not_a_trusted_proxy(): void
    {
        $this->trustConfiguredProxies();

        $this->assertSame(self::UNTRUSTED_PEER_ADDRESS, $this->recordedAddressForFailedSignInFrom(self::UNTRUSTED_PEER_ADDRESS));
    }

    private function trustConfiguredProxies(): void
    {
        config(['trusted_proxies.addresses' => [self::PROXY_ADDRESS]]);

        TrustedProxyConfigurator::configure((array) config('trusted_proxies.addresses'));
    }

    private function recordedAddressForFailedSignInFrom(string $peerAddress): ?string
    {
        $this->withServerVariables(['REMOTE_ADDR' => $peerAddress]);
        $this->withHeader(self::FORWARDED_FOR_HEADER, self::CLIENT_ADDRESS);

        $this->startGuestSession();

        $this->postJson(self::SIGN_IN_ROUTE, [
            'email' => self::UNKNOWN_EMAIL,
            'password' => self::WRONG_PASSWORD,
        ])->assertUnauthorized();

        return $this->auditLogsFor(AuthAuditEvent::SignInFailed)->sole()->ip_address;
    }
}
