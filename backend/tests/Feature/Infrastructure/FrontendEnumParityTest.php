<?php

// Compares the frontend TypeScript enums with the backend PHP enums so both sides match exactly.
// Also checks that the frontend has one error message for every backend error code.

namespace Tests\Feature\Infrastructure;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendEnumParityTest extends TestCase
{
    private const string FRONTEND_PATH = '../frontend/';

    private const string ENUM_MEMBER_PATTERN = '/^\s*([A-Za-z][A-Za-z0-9]*)\s*=\s*"([^"]*)"\s*,?\s*$/m';

    private const string ERROR_MESSAGE_KEY_PATTERN = '/\[ErrorCode\.([A-Za-z][A-Za-z0-9]*)\]\s*:/';

    private const string ERROR_MESSAGES_FILE = 'constants/error-messages.ts';

    public static function enumPairs(): array
    {
        return [
            'user role' => [UserRole::class, 'enums/user-role.ts'],
            'service request status' => [ServiceRequestStatus::class, 'enums/service-request-status.ts'],
            'service request tag' => [ServiceRequestTag::class, 'enums/service-request-tag.ts'],
            'error code' => [ErrorCode::class, 'enums/error-code.ts'],
        ];
    }

    #[DataProvider('enumPairs')]
    public function test_frontend_enum_matches_the_backend_enum(string $enumClass, string $frontendFile): void
    {
        preg_match_all(self::ENUM_MEMBER_PATTERN, $this->readFrontendFile($frontendFile), $matches);

        $frontendMembers = array_combine($matches[1], $matches[2]);
        $backendMembers = [];

        foreach ($enumClass::cases() as $case) {
            $backendMembers[$case->name] = $case->value;
        }

        $this->assertSame($backendMembers, $frontendMembers);
    }

    public function test_every_error_code_has_a_frontend_message(): void
    {
        preg_match_all(self::ERROR_MESSAGE_KEY_PATTERN, $this->readFrontendFile(self::ERROR_MESSAGES_FILE), $matches);

        $backendNames = array_map(fn (ErrorCode $code): string => $code->name, ErrorCode::cases());

        $this->assertSame($backendNames, $matches[1]);
    }

    private function readFrontendFile(string $relativePath): string
    {
        $path = base_path(self::FRONTEND_PATH.$relativePath);

        $this->assertFileExists($path, "The frontend file {$relativePath} is missing. The repository must hold both applications.");

        return (string) file_get_contents($path);
    }
}
