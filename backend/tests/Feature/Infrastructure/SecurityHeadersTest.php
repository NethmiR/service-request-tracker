<?php

// Verifies that every response carries the security headers.
// Strict transport security must appear only in production.

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private const string SUCCESS_ROUTE = '/api/testing/success';

    private const string STRICT_TRANSPORT_SECURITY_HEADER = 'Strict-Transport-Security';

    private const array COMMON_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get(self::SUCCESS_ROUTE, fn (): array => []);
    }

    public function test_successful_response_carries_common_security_headers(): void
    {
        $response = $this->getJson(self::SUCCESS_ROUTE)->assertOk();

        foreach (self::COMMON_HEADERS as $header => $value) {
            $response->assertHeader($header, $value);
        }
    }

    public function test_error_response_carries_common_security_headers(): void
    {
        $response = $this->getJson('/api/does-not-exist')->assertNotFound();

        foreach (self::COMMON_HEADERS as $header => $value) {
            $response->assertHeader($header, $value);
        }
    }

    public function test_cors_preflight_response_carries_common_security_headers(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:3000']]);

        $response = $this->call('OPTIONS', self::SUCCESS_ROUTE, server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertNoContent();

        foreach (self::COMMON_HEADERS as $header => $value) {
            $response->assertHeader($header, $value);
        }
    }

    public function test_strict_transport_security_is_absent_outside_production(): void
    {
        $this->getJson(self::SUCCESS_ROUTE)
            ->assertHeaderMissing(self::STRICT_TRANSPORT_SECURITY_HEADER);
    }

    public function test_strict_transport_security_is_present_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->getJson(self::SUCCESS_ROUTE)
            ->assertHeader(self::STRICT_TRANSPORT_SECURITY_HEADER, 'max-age=31536000; includeSubDomains');
    }
}
