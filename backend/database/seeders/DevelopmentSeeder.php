<?php

// Creates demo technicians, requesters and open service requests for local development only.
// It is run by hand, refuses to run outside the local environment and is never called from the database seeder.

namespace Database\Seeders;

use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public const string LOCAL_ENVIRONMENT_REQUIRED_MESSAGE = 'The development seeder runs only in the local environment.';

    public const string INVALID_PASSWORD_MESSAGE = 'The development seeder cannot run. Set DEVELOPMENT_USER_PASSWORD to a value that satisfies the password policy.';

    public const int OPEN_REQUEST_COUNT = 40;

    public const int HISTORY_MONTHS = 12;

    public const int MAXIMUM_TAGS_PER_REQUEST = 2;

    public const array TECHNICIANS = [
        ['name' => 'Tharindu Perera', 'email' => 'tharindu.perera@example.test'],
        ['name' => 'Ishara Fernando', 'email' => 'ishara.fernando@example.test'],
    ];

    public const array REQUESTERS = [
        ['name' => 'Kasun Silva', 'email' => 'kasun.silva@example.test'],
        ['name' => 'Dilani Jayasinghe', 'email' => 'dilani.jayasinghe@example.test'],
        ['name' => 'Ruwan Bandara', 'email' => 'ruwan.bandara@example.test'],
    ];

    private const string PASSWORD_CONFIG_KEY = 'development_seed.user_password';

    private const string INITIAL_ADMIN_EMAIL_CONFIG_KEY = 'initial_admin.email';

    private const string PASSWORD = 'password';

    private const string NAME = 'name';

    private const string EMAIL = 'email';

    private const int MINIMUM_TAGS_PER_REQUEST = 1;

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException(self::LOCAL_ENVIRONMENT_REQUIRED_MESSAGE);
        }

        $password = config(self::PASSWORD_CONFIG_KEY);

        $this->ensureValidPassword($password);

        $this->call(TagSeeder::class);

        foreach (self::TECHNICIANS as $technician) {
            $this->createUserUnlessExists($technician, UserRole::Technician, $password);
        }

        foreach (self::REQUESTERS as $requester) {
            $this->createUserUnlessExists($requester, UserRole::Requester, $password);
        }

        $this->createOpenRequests($this->requestingUsers());
    }

    private function ensureValidPassword(mixed $password): void
    {
        $validator = Validator::make(
            [self::PASSWORD => $password],
            [self::PASSWORD => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            throw new RuntimeException(self::INVALID_PASSWORD_MESSAGE);
        }
    }

    private function createUserUnlessExists(array $details, UserRole $role, string $password): void
    {
        if (User::query()->where(self::EMAIL, $details[self::EMAIL])->exists()) {
            return;
        }

        User::factory()->create([
            self::NAME => $details[self::NAME],
            self::EMAIL => $details[self::EMAIL],
            self::PASSWORD => $password,
            'role' => $role,
            'password_changed_at' => now(),
        ]);
    }

    private function requestingUsers(): array
    {
        $emails = array_column(self::REQUESTERS, self::EMAIL);
        $initialAdminEmail = config(self::INITIAL_ADMIN_EMAIL_CONFIG_KEY);

        if (is_string($initialAdminEmail) && trim($initialAdminEmail) !== '') {
            $emails[] = Str::lower(trim($initialAdminEmail));
        }

        return User::query()->whereIn(self::EMAIL, $emails)->get()->all();
    }

    private function createOpenRequests(array $requestingUsers): void
    {
        $userCount = count($requestingUsers);
        $earliestTime = '-'.self::HISTORY_MONTHS.' months';

        for ($index = 0; $index < self::OPEN_REQUEST_COUNT; $index++) {
            $tags = fake()->randomElements(
                ServiceRequestTag::cases(),
                fake()->numberBetween(self::MINIMUM_TAGS_PER_REQUEST, self::MAXIMUM_TAGS_PER_REQUEST),
            );

            ServiceRequest::factory()
                ->requestedBy($requestingUsers[$index % $userCount])
                ->withTags(...$tags)
                ->createdAt(CarbonImmutable::instance(fake()->dateTimeBetween($earliestTime)))
                ->create();
        }
    }
}
