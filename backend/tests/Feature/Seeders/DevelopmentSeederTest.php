<?php

// Verifies the development seeder that creates demo users and open requests.
// It runs only in the local environment, needs a valid password, never reveals it and can run twice.

namespace Tests\Feature\Seeders;

use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    private const string LOCAL_ENVIRONMENT = 'local';

    private const string PRODUCTION_ENVIRONMENT = 'production';

    private const string VALID_PASSWORD = 'Development-Password-2026!';

    private int $tagCountBeforeSeeding = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tagCountBeforeSeeding = Tag::query()->count();
    }

    public static function invalidPasswords(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'weak' => ['weak-password'],
            'placeholder' => ['change-this-placeholder'],
        ];
    }

    public function test_outside_the_local_environment_it_throws_and_writes_nothing(): void
    {
        $this->app['env'] = self::PRODUCTION_ENVIRONMENT;
        $this->configureSeeder(self::VALID_PASSWORD);

        try {
            $this->runSeeder();
            $this->fail('The seeder ran outside the local environment.');
        } catch (RuntimeException $exception) {
            $this->assertSame(DevelopmentSeeder::LOCAL_ENVIRONMENT_REQUIRED_MESSAGE, $exception->getMessage());
        }

        $this->assertNothingWasWritten();
    }

    public function test_in_the_local_environment_it_creates_the_demo_data(): void
    {
        $this->useLocalEnvironment(self::VALID_PASSWORD);

        $this->runSeeder();

        $this->assertDemoUsersExist();
        $this->assertSame(DevelopmentSeeder::OPEN_REQUEST_COUNT, ServiceRequest::query()->count());
        $this->assertSame(0, ServiceRequest::query()->where('status', '!=', ServiceRequestStatus::Open)->count());

        $demoRequesterIds = User::query()
            ->whereIn('email', array_column(DevelopmentSeeder::REQUESTERS, 'email'))
            ->pluck('id')
            ->all();
        $this->assertSame(0, ServiceRequest::query()->whereNotIn('requested_by_user_id', $demoRequesterIds)->count());

        $histories = ServiceRequestStatusHistory::query()->get();
        $this->assertCount(DevelopmentSeeder::OPEN_REQUEST_COUNT, $histories);
        $this->assertCount(DevelopmentSeeder::OPEN_REQUEST_COUNT, $histories->unique('service_request_id'));
        $this->assertTrue($histories->every(fn (ServiceRequestStatusHistory $history): bool => $history->isInitialRecord()));

        $requests = ServiceRequest::query()->with(['tags', 'statusHistories'])->get();
        $earliestAllowed = now()->subMonths(DevelopmentSeeder::HISTORY_MONTHS)->subMinute();

        foreach ($requests as $serviceRequest) {
            $this->assertNotEmpty($serviceRequest->tags);
            $this->assertLessThanOrEqual(DevelopmentSeeder::MAXIMUM_TAGS_PER_REQUEST, $serviceRequest->tags->count());
            $this->assertTrue($serviceRequest->created_at->greaterThanOrEqualTo($earliestAllowed));
            $this->assertTrue($serviceRequest->created_at->equalTo($serviceRequest->statusHistories->sole()->created_at));
        }
    }

    public function test_requests_are_spread_to_the_initial_admin_when_configured(): void
    {
        $admin = User::factory()->admin()->create();
        $this->useLocalEnvironment(self::VALID_PASSWORD, strtoupper($admin->email));

        $this->runSeeder();

        $this->assertGreaterThan(0, ServiceRequest::query()->where('requested_by_user_id', $admin->id)->count());
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_makes_it_throw_without_revealing_it(?string $password): void
    {
        $this->useLocalEnvironment($password);

        try {
            $this->runSeeder();
            $this->fail('The seeder accepted an invalid password.');
        } catch (RuntimeException $exception) {
            $this->assertSame(DevelopmentSeeder::INVALID_PASSWORD_MESSAGE, $exception->getMessage());

            if ($password !== null && $password !== '') {
                $this->assertStringNotContainsString($password, $exception->getMessage());
            }
        }

        $this->assertNothingWasWritten();
    }

    public function test_running_it_twice_creates_no_duplicate_users(): void
    {
        $this->useLocalEnvironment(self::VALID_PASSWORD);

        $this->runSeeder();
        $this->runSeeder();

        $demoUserCount = count(DevelopmentSeeder::TECHNICIANS) + count(DevelopmentSeeder::REQUESTERS);

        $this->assertSame($demoUserCount, User::query()->count());
        $this->assertDemoUsersExist();
    }

    private function assertDemoUsersExist(): void
    {
        $expectedUsers = [
            ...array_map(fn (array $user): array => [...$user, 'role' => UserRole::Technician], DevelopmentSeeder::TECHNICIANS),
            ...array_map(fn (array $user): array => [...$user, 'role' => UserRole::Requester], DevelopmentSeeder::REQUESTERS),
        ];

        foreach ($expectedUsers as $expectedUser) {
            $user = User::query()->where('email', $expectedUser['email'])->sole();

            $this->assertSame($expectedUser['name'], $user->name);
            $this->assertSame($expectedUser['role'], $user->role);
            $this->assertFalse($user->requiresPasswordChange());
            $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->password));
        }
    }

    private function assertNothingWasWritten(): void
    {
        $this->assertSame(0, User::query()->count());
        $this->assertSame($this->tagCountBeforeSeeding, Tag::query()->count());
        $this->assertSame(0, ServiceRequest::query()->count());
    }

    private function useLocalEnvironment(?string $password, ?string $initialAdminEmail = null): void
    {
        $this->app['env'] = self::LOCAL_ENVIRONMENT;
        $this->configureSeeder($password, $initialAdminEmail);
    }

    private function configureSeeder(?string $password, ?string $initialAdminEmail = null): void
    {
        config([
            'development_seed.user_password' => $password,
            'initial_admin.email' => $initialAdminEmail,
        ]);
    }

    private function runSeeder(): void
    {
        $this->app->make(DevelopmentSeeder::class)->setContainer($this->app)->run();
    }
}
