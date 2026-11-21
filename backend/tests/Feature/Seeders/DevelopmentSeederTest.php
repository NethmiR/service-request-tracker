<?php

// Verifies the development seeder that creates the test users and demo requests spread across the workflow statuses.

namespace Tests\Feature\Seeders;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Database\Seeders\TestUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_demo_data(): void
    {
        $this->runSeeder();

        $this->assertTestUsersExist();
        $this->assertSame(DevelopmentSeeder::REQUEST_COUNT, ServiceRequest::query()->count());

        $openCount = DevelopmentSeeder::REQUEST_COUNT
            - DevelopmentSeeder::RESOLVED_REQUEST_COUNT
            - DevelopmentSeeder::IN_PROGRESS_REQUEST_COUNT
            - DevelopmentSeeder::ASSIGNED_REQUEST_COUNT;
        $expectedCounts = [
            ServiceRequestStatus::Open->value => $openCount,
            ServiceRequestStatus::Assigned->value => DevelopmentSeeder::ASSIGNED_REQUEST_COUNT,
            ServiceRequestStatus::InProgress->value => DevelopmentSeeder::IN_PROGRESS_REQUEST_COUNT,
            ServiceRequestStatus::Resolved->value => DevelopmentSeeder::RESOLVED_REQUEST_COUNT,
        ];

        foreach ($expectedCounts as $status => $expectedCount) {
            $this->assertSame($expectedCount, ServiceRequest::query()->where('status', $status)->count());
        }

        $requestingUserIds = User::query()
            ->whereIn('email', TestUserSeeder::emailsWithRoles(...DevelopmentSeeder::REQUESTING_ROLES))
            ->pluck('id')
            ->all();
        $this->assertSame(0, ServiceRequest::query()->whereNotIn('requested_by_user_id', $requestingUserIds)->count());

        foreach ($requestingUserIds as $requestingUserId) {
            $this->assertGreaterThan(0, ServiceRequest::query()->where('requested_by_user_id', $requestingUserId)->count());
        }

        $requests = ServiceRequest::query()
            ->with(['tags', 'statusHistories', 'assigneeHistories'])
            ->get();
        $earliestAllowed = now()->subMonths(DevelopmentSeeder::HISTORY_MONTHS)->subMinute();
        $reassignedCount = 0;

        foreach ($requests as $serviceRequest) {
            $this->assertNotEmpty($serviceRequest->tags);
            $this->assertLessThanOrEqual(DevelopmentSeeder::MAXIMUM_TAGS_PER_REQUEST, $serviceRequest->tags->count());
            $this->assertTrue($serviceRequest->created_at->greaterThanOrEqualTo($earliestAllowed));

            $statusHistories = $serviceRequest->statusHistories->sortBy('id')->values();
            $this->assertTrue($statusHistories->first()->isInitialRecord());
            $this->assertTrue($serviceRequest->created_at->equalTo($statusHistories->first()->created_at));

            foreach ($statusHistories->slice(1) as $history) {
                $this->assertNotNull($history->from_status);
                $this->assertNotNull($history->changed_by_user_id);
            }

            $assigneeHistories = $serviceRequest->assigneeHistories->sortBy('id')->values();

            if ($serviceRequest->status === ServiceRequestStatus::Open) {
                $this->assertCount(0, $assigneeHistories);
            } else {
                $this->assertGreaterThanOrEqual(1, $assigneeHistories->count());
            }

            if ($assigneeHistories->count() === 2) {
                $reassignedCount++;
            }

            $previousTime = $serviceRequest->created_at;

            foreach ($statusHistories->slice(1)->concat($assigneeHistories)->sortBy('created_at') as $record) {
                $this->assertTrue($record->created_at->greaterThanOrEqualTo($previousTime));
                $previousTime = $record->created_at;
            }
        }

        $this->assertSame(DevelopmentSeeder::REASSIGNED_REQUEST_COUNT, $reassignedCount);
        $this->assertFalse(Date::hasTestNow());
    }

    public function test_running_it_twice_creates_no_duplicate_users(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(count(TestUserSeeder::USERS), User::query()->count());
        $this->assertTestUsersExist();
    }

    private function assertTestUsersExist(): void
    {
        foreach (TestUserSeeder::USERS as $expectedUser) {
            $user = User::query()->where('email', $expectedUser['email'])->sole();

            $this->assertSame($expectedUser['name'], $user->name);
            $this->assertSame($expectedUser['role'], $user->role);
            $this->assertFalse($user->requiresPasswordChange());
            $this->assertTrue(Hash::check($expectedUser['password'], $user->password));
        }
    }

    private function runSeeder(): void
    {
        $this->seed(DevelopmentSeeder::class);
    }
}
