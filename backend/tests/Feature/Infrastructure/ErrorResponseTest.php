<?php

// Verifies that every failure is rendered in the shared JSON error shape.
// Covers unknown routes, wrong methods, domain errors, validation, authentication, throttling and server errors.

namespace Tests\Feature\Infrastructure;

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    private const string DOMAIN_ERROR_ROUTE = '/api/testing/domain-error';

    private const string VALIDATION_ROUTE = '/api/testing/validation';

    private const string AUTHENTICATED_ROUTE = '/api/testing/authenticated';

    private const string THROTTLED_ROUTE = '/api/testing/throttled';

    private const string SERVER_ERROR_ROUTE = '/api/testing/server-error';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->group(function (): void {
            Route::get(self::DOMAIN_ERROR_ROUTE, function (): never {
                throw new class extends DomainException
                {
                    public function errorCode(): ErrorCode
                    {
                        return ErrorCode::StatusUnchanged;
                    }
                };
            });

            Route::post(self::VALIDATION_ROUTE, fn (Request $request): array => $request->validate([
                'title' => ['required', 'string'],
            ]));

            Route::get(self::AUTHENTICATED_ROUTE, fn (): array => [])->middleware('auth:sanctum');

            Route::get(self::THROTTLED_ROUTE, fn (): array => [])->middleware('throttle:1,1');

            Route::get(self::SERVER_ERROR_ROUTE, function (): never {
                throw new RuntimeException('Internal failure details.');
            });
        });
    }

    public function test_unknown_api_route_returns_not_found_error_shape(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson($this->errorBody(ErrorCode::NotFound));
    }

    public function test_unknown_route_outside_api_returns_error_shape(): void
    {
        $this->getJson('/does-not-exist')
            ->assertNotFound()
            ->assertExactJson($this->errorBody(ErrorCode::NotFound));
    }

    public function test_wrong_method_returns_method_not_allowed_with_allow_header(): void
    {
        $this->postJson('/up')
            ->assertMethodNotAllowed()
            ->assertHeader('Allow')
            ->assertExactJson($this->errorBody(ErrorCode::MethodNotAllowed));
    }

    public function test_domain_exception_returns_its_error_code_and_status(): void
    {
        $this->getJson(self::DOMAIN_ERROR_ROUTE)
            ->assertStatus(ErrorCode::StatusUnchanged->httpStatus())
            ->assertExactJson($this->errorBody(ErrorCode::StatusUnchanged));
    }

    public function test_validation_failure_returns_field_errors(): void
    {
        $this->postJson(self::VALIDATION_ROUTE)
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonPath('message', ErrorCode::ValidationFailed->message())
            ->assertJsonValidationErrors(['title']);
    }

    public function test_protected_route_without_session_returns_unauthenticated(): void
    {
        $this->getJson(self::AUTHENTICATED_ROUTE)
            ->assertUnauthorized()
            ->assertExactJson($this->errorBody(ErrorCode::Unauthenticated));
    }

    public function test_exceeding_rate_limit_returns_too_many_requests_with_retry_after(): void
    {
        $this->getJson(self::THROTTLED_ROUTE)->assertOk();

        $this->getJson(self::THROTTLED_ROUTE)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson($this->errorBody(ErrorCode::TooManyRequests));
    }

    public function test_unexpected_exception_hides_internal_details_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $this->getJson(self::SERVER_ERROR_ROUTE)
            ->assertInternalServerError()
            ->assertExactJson($this->errorBody(ErrorCode::ServerError))
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('trace');
    }

    public function test_request_without_json_accept_header_still_receives_json(): void
    {
        $this->get(self::AUTHENTICATED_ROUTE)
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson($this->errorBody(ErrorCode::Unauthenticated));
    }

    private function errorBody(ErrorCode $errorCode): array
    {
        return [
            'message' => $errorCode->message(),
            'code' => $errorCode->value,
        ];
    }
}
