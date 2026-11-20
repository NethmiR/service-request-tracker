<?php

// Creates demo technicians, requesters and service requests for local development only, and moves many of them through the workflow.
// It is run by hand, refuses to run outside the local environment and is never called from the database seeder.

namespace Database\Seeders;

use App\Data\ServiceRequests\Reassignment;
use App\Data\ServiceRequests\SelfAssignment;
use App\Data\ServiceRequests\StatusChange;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public const string LOCAL_ENVIRONMENT_REQUIRED_MESSAGE = 'The development seeder runs only in the local environment.';

    public const string INVALID_PASSWORD_MESSAGE = 'The development seeder cannot run. Set DEVELOPMENT_USER_PASSWORD to a value that satisfies the password policy.';

    public const int REQUEST_COUNT = 40;

    public const int RESOLVED_REQUEST_COUNT = 16;

    public const int IN_PROGRESS_REQUEST_COUNT = 8;

    public const int ASSIGNED_REQUEST_COUNT = 8;

    public const int REASSIGNED_REQUEST_COUNT = 6;

    public const int FIRST_ASSIGNMENT_MINIMUM_HOURS = 1;

    public const int FIRST_ASSIGNMENT_MAXIMUM_HOURS = 48;

    public const int REASSIGNMENT_MINIMUM_HOURS = 1;

    public const int REASSIGNMENT_MAXIMUM_HOURS = 6;

    public const int IN_PROGRESS_MINIMUM_HOURS = 1;

    public const int IN_PROGRESS_MAXIMUM_HOURS = 6;

    public const int RESOLUTION_MINIMUM_HOURS = 24;

    public const int RESOLUTION_MAXIMUM_HOURS = 240;

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

    private const string ASSIGNMENT_REMARK = 'Taking this request.';

    private const string REASSIGNMENT_REMARK = 'Handing this over to a colleague who is better placed to help.';

    private const string RESOLUTION_REMARK = 'The issue is fixed and confirmed with the requester.';

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

        $requests = $this->createRequests($this->requestingUsers());

        $this->moveRequestsThroughWorkflow($requests, $this->staffUsers());
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

    private function staffUsers(): array
    {
        $emails = array_column(self::TECHNICIANS, self::EMAIL);
        $initialAdminEmail = config(self::INITIAL_ADMIN_EMAIL_CONFIG_KEY);

        if (is_string($initialAdminEmail) && trim($initialAdminEmail) !== '') {
            $emails[] = Str::lower(trim($initialAdminEmail));
        }

        return User::query()->whereIn(self::EMAIL, $emails)->orderBy('id')->get()->all();
    }

    private function createRequests(array $requestingUsers): array
    {
        $userCount = count($requestingUsers);
        $earliestTime = '-'.self::HISTORY_MONTHS.' months';
        $requests = [];

        for ($index = 0; $index < self::REQUEST_COUNT; $index++) {
            $tags = fake()->randomElements(
                ServiceRequestTag::cases(),
                fake()->numberBetween(self::MINIMUM_TAGS_PER_REQUEST, self::MAXIMUM_TAGS_PER_REQUEST),
            );

            $requests[] = ServiceRequest::factory()
                ->requestedBy($requestingUsers[$index % $userCount])
                ->withTags(...$tags)
                ->createdAt(CarbonImmutable::instance(fake()->dateTimeBetween($earliestTime)))
                ->create();
        }

        usort($requests, fn (ServiceRequest $first, ServiceRequest $second): int => $first->created_at <=> $second->created_at);

        return $requests;
    }

    private function moveRequestsThroughWorkflow(array $requests, array $staffUsers): void
    {
        $workflow = app(ServiceRequestWorkflowService::class);
        $limit = CarbonImmutable::now();
        $reassignedCount = 0;

        try {
            foreach ($requests as $index => $serviceRequest) {
                $targetStatus = $this->targetStatusFor($index);

                if ($targetStatus === ServiceRequestStatus::Open) {
                    continue;
                }

                $assignee = fake()->randomElement($staffUsers);
                $time = $this->advance(
                    CarbonImmutable::instance($serviceRequest->created_at),
                    self::FIRST_ASSIGNMENT_MINIMUM_HOURS,
                    self::FIRST_ASSIGNMENT_MAXIMUM_HOURS,
                    $limit,
                );

                Date::setTestNow($time);
                $workflow->assignToSelf($assignee, $serviceRequest, new SelfAssignment(self::ASSIGNMENT_REMARK));

                if ($reassignedCount < self::REASSIGNED_REQUEST_COUNT) {
                    $reassignedCount++;
                    $newAssignee = fake()->randomElement(
                        array_values(array_filter($staffUsers, fn (User $user): bool => ! $user->is($assignee))),
                    );
                    $time = $this->advance($time, self::REASSIGNMENT_MINIMUM_HOURS, self::REASSIGNMENT_MAXIMUM_HOURS, $limit);

                    Date::setTestNow($time);
                    $workflow->reassign($assignee, $serviceRequest, new Reassignment($newAssignee->id, self::REASSIGNMENT_REMARK));
                    $assignee = $newAssignee;
                }

                if ($targetStatus === ServiceRequestStatus::Assigned) {
                    continue;
                }

                $time = $this->advance($time, self::IN_PROGRESS_MINIMUM_HOURS, self::IN_PROGRESS_MAXIMUM_HOURS, $limit);

                Date::setTestNow($time);
                $workflow->changeStatus($assignee, $serviceRequest, new StatusChange(ServiceRequestStatus::InProgress, null));

                if ($targetStatus === ServiceRequestStatus::InProgress) {
                    continue;
                }

                $time = $this->advance($time, self::RESOLUTION_MINIMUM_HOURS, self::RESOLUTION_MAXIMUM_HOURS, $limit);

                Date::setTestNow($time);
                $workflow->changeStatus($assignee, $serviceRequest, new StatusChange(ServiceRequestStatus::Resolved, self::RESOLUTION_REMARK));
            }
        } finally {
            Date::setTestNow();
        }
    }

    private function targetStatusFor(int $index): ServiceRequestStatus
    {
        return match (true) {
            $index < self::RESOLVED_REQUEST_COUNT => ServiceRequestStatus::Resolved,
            $index < self::RESOLVED_REQUEST_COUNT + self::IN_PROGRESS_REQUEST_COUNT => ServiceRequestStatus::InProgress,
            $index < self::RESOLVED_REQUEST_COUNT + self::IN_PROGRESS_REQUEST_COUNT + self::ASSIGNED_REQUEST_COUNT => ServiceRequestStatus::Assigned,
            default => ServiceRequestStatus::Open,
        };
    }

    private function advance(CarbonImmutable $time, int $minimumHours, int $maximumHours, CarbonImmutable $limit): CarbonImmutable
    {
        $next = $time->addHours(fake()->numberBetween($minimumHours, $maximumHours));

        return $next->greaterThan($limit) ? $limit : $next;
    }
}
