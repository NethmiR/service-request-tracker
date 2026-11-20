<?php

// Verifies that every error code has an error HTTP status and a translated message.
// Catches a new case that was added without a translation in lang/en/errors.php.

namespace Tests\Unit\Enums;

use App\Enums\ErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ErrorCodeTest extends TestCase
{
    public static function errorCodes(): array
    {
        return array_combine(
            ErrorCode::values(),
            array_map(fn (ErrorCode $errorCode): array => [$errorCode], ErrorCode::cases()),
        );
    }

    #[DataProvider('errorCodes')]
    public function test_error_code_has_an_error_http_status(ErrorCode $errorCode): void
    {
        $this->assertGreaterThanOrEqual(Response::HTTP_BAD_REQUEST, $errorCode->httpStatus());
        $this->assertLessThanOrEqual(599, $errorCode->httpStatus());
    }

    #[DataProvider('errorCodes')]
    public function test_error_code_has_a_translated_message(ErrorCode $errorCode): void
    {
        $this->assertNotEmpty($errorCode->message());
        $this->assertNotSame('errors.'.$errorCode->value, $errorCode->message());
    }
}
