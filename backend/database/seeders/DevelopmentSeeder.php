<?php

// Creates demo service requests raised and handled by the test users, and moves many of them through the workflow.
// It is run by hand and is never called from the database seeder. It seeds the tags and the test users first.

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

class DevelopmentSeeder extends Seeder
{
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

    public const array REQUESTING_ROLES = [UserRole::Requester, UserRole::Admin];

    private const string EMAIL = 'email';

    private const int MINIMUM_TAGS_PER_REQUEST = 1;

    private const string ASSIGNMENT_REMARK = 'Taking this request.';

    private const string REASSIGNMENT_REMARK = 'Handing this over to a colleague who is better placed to help.';

    private const string RESOLUTION_REMARK = 'The issue is fixed and confirmed with the requester.';

    public function run(): void
    {
        $this->call(TagSeeder::class);
        $this->call(TestUserSeeder::class);

        $requests = $this->createRequests($this->testUsersWithRoles(...self::REQUESTING_ROLES));

        $this->moveRequestsThroughWorkflow($requests, $this->testUsersWithRoles(...UserRole::staff()));
    }

    private function testUsersWithRoles(UserRole ...$roles): array
    {
        return User::query()
            ->whereIn(self::EMAIL, TestUserSeeder::emailsWithRoles(...$roles))
            ->orderBy('id')
            ->get()
            ->all();
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
