<?php

// Verifies that cross origin requests are allowed only from the configured frontend origin.
// Preflight responses must list only the methods and headers the API uses.

namespace Tests\Feature\Infrastructure;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CorsTest extends TestCase
{
    private const string FRONTEND_ORIGIN = 'http://localhost:3000';

    private const string OTHER_ORIGIN = 'https://untrusted.example.com';

    private const string API_PATH = '/api/testing/cors';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => [self::FRONTEND_ORIGIN]]);
    }

    public function test_preflight_from_frontend_origin_is_allowed_with_credentials(): void
    {
        $response = $this->preflight(self::FRONTEND_ORIGIN)
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->assertEqualsCanonicalizing(
            ['GET', 'POST', 'PATCH', 'DELETE'],
            $this->headerList($response, 'Access-Control-Allow-Methods', 'strtoupper'),
        );

        $this->assertEqualsCanonicalizing(
            ['accept', 'content-type', 'x-requested-with', 'x-xsrf-token'],
            $this->headerList($response, 'Access-Control-Allow-Headers', 'strtolower'),
        );
    }

    public function test_preflight_from_other_origin_is_not_allowed(): void
    {
        $response = $this->preflight(self::OTHER_ORIGIN);

        $this->assertNotSame(self::OTHER_ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
    }

    private function preflight(string $origin): TestResponse
    {
        return $this->call('OPTIONS', self::API_PATH, server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'X-XSRF-TOKEN',
        ]);
    }

    private function headerList(TestResponse $response, string $header, callable $normalize): array
    {
        return array_map(
            fn (string $value): string => $normalize(trim($value)),
            explode(',', (string) $response->headers->get($header)),
        );
    }
}
