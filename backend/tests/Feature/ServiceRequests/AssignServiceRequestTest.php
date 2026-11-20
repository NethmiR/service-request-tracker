<?php

// Verifies assigning an open request to the signed in technician or admin.
// Covers the response, the history records, remark validation, conflicts and ignored client fields.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class AssignServiceRequestTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string REMARK = 'Taking this one, I am at the office today.';

    public static function invalidRemarks(): array
    {
        return [
            'missing' => [[]],
            'only spaces' => [['remark' => '   ']],
            'too long' => [['remark' => str_repeat('a', ServiceRequestLimits::REMARK_MAX_LENGTH + 1)]],
        ];
    }

    public static function staffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_member_assigns_an_open_request_to_themselves(string $role): void
    {
        $staff = $this->signInAs(User::factory()->{$role}()->create());
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());

        $this->postJson($this->uri($serviceRequest), ['remark' => '  '.self::REMARK.'  '])
            ->assertOk()
            ->assertJsonPath('data.id', $serviceRequest->id)
            ->assertJsonPath('data.status', ServiceRequestStatus::Assigned->value)
            ->assertJsonPath('data.assignee', ['id' => $staff->id, 'name' => $staff->name])
            ->assertJsonPath('data.available_actions', [
                'can_assign_to_self' => false,
                'can_reassign' => true,
                'allowed_statuses' => [ServiceRequestStatus::InProgress->value, ServiceRequestStatus::Resolved->value],
            ]);

        $assigneeRecord = ServiceRequestAssigneeHistory::query()->sole();

        $this->assertNull($assigneeRecord->assigned_from_user_id);
        $this->assertSame($staff->id, $assigneeRecord->assigned_to_user_id);
        $this->assertSame(self::REMARK, $assigneeRecord->remark);

        $statusRecord = ServiceRequestStatusHistory::query()->whereNotNull('from_status')->sole();

        $this->assertSame(ServiceRequestStatus::Open, $statusRecord->from_status);
        $this->assertSame(ServiceRequestStatus::Assigned, $statusRecord->to_status);
        $this->assertSame($staff->id, $statusRecord->changed_by_user_id);
        $this->assertNull($statusRecord->remark);
        $this->assertSame(2, ServiceRequestStatusHistory::query()->count());
        $this->assertSame(ServiceRequestStatus::Assigned, $serviceRequest->refresh()->status);
    }

    #[DataProvider('invalidRemarks')]
    public function test_invalid_remark_is_rejected_without_writing_anything(array $payload): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());

        $this->postJson($this->uri($serviceRequest), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['remark']);

        $this->assertSame(0, ServiceRequestAssigneeHistory::query()->count());
        $this->assertSame(1, ServiceRequestStatusHistory::query()->count());
        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->refresh()->status);
    }

    public function test_assigning_a_request_that_is_already_assigned_returns_a_conflict(): void
    {
        $firstTechnician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $firstTechnician);
        $assigneeRecordCount = ServiceRequestAssigneeHistory::query()->count();
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        foreach ([User::factory()->technician()->create(), $firstTechnician] as $user) {
            $this->switchToFreshClient();
            $this->signInAs($user);

            $this->postJson($this->uri($serviceRequest), ['remark' => self::REMARK])
                ->assertConflict()
                ->assertJsonPath('code', ErrorCode::ConcurrentUpdate->value);
        }

        $this->assertSame($assigneeRecordCount, ServiceRequestAssigneeHistory::query()->count());
        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
    }

    public function test_client_supplied_assignment_fields_are_ignored(): void
    {
        $technician = $this->signInAs(User::factory()->technician()->create());
        $other = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());

        $this->postJson($this->uri($serviceRequest), [
            'remark' => self::REMARK,
            'assignee_id' => $other->id,
            'assigned_to_user_id' => $other->id,
            'status' => ServiceRequestStatus::Resolved->value,
        ])->assertOk()->assertJsonPath('data.assignee.id', $technician->id);

        $this->assertSame(ServiceRequestStatus::Assigned, $serviceRequest->refresh()->status);
        $this->assertSame($technician->id, ServiceRequestAssigneeHistory::query()->sole()->assigned_to_user_id);
    }

    private function uri(ServiceRequest $serviceRequest): string
    {
        return '/api/service-requests/'.$serviceRequest->id.'/assignment';
    }
}
