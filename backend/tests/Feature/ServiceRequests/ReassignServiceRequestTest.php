<?php

// Verifies reassigning a request to another technician or admin.
// Covers every status, history writes without a status record, conflicts, validation and who may reassign.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ReassignServiceRequestTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string REMARK = 'Ishara handles network hardware.';

    private const int UNKNOWN_USER_ID = 999999;

    public static function reassignableStatuses(): array
    {
        return [
            'assigned' => [ServiceRequestStatus::Assigned],
            'in progress' => [ServiceRequestStatus::InProgress],
            'resolved' => [ServiceRequestStatus::Resolved],
        ];
    }

    public static function invalidAssignees(): array
    {
        return [
            'requester' => ['requester'],
            'unknown user' => [self::UNKNOWN_USER_ID],
            'not a number' => ['abc'],
        ];
    }

    #[DataProvider('reassignableStatuses')]
    public function test_current_assignee_reassigns_in_every_status_without_a_status_record(ServiceRequestStatus $status): void
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus($status, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $newAssignee->id, 'remark' => '  '.self::REMARK.'  '])
            ->assertOk()
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.assignee', ['id' => $newAssignee->id, 'name' => $newAssignee->name])
            ->assertJsonPath('data.available_actions', [
                'can_assign_to_self' => false,
                'can_reassign' => false,
                'allowed_statuses' => [],
            ]);

        $this->assertSame(2, ServiceRequestAssigneeHistory::query()->count());

        $record = ServiceRequestAssigneeHistory::query()->latest('id')->first();

        $this->assertSame($assignee->id, $record->assigned_from_user_id);
        $this->assertSame($newAssignee->id, $record->assigned_to_user_id);
        $this->assertSame(self::REMARK, $record->remark);
        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
        $this->assertSame($status, $serviceRequest->refresh()->status);
    }

    public function test_reassigning_to_an_admin_works(): void
    {
        $assignee = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $admin->id, 'remark' => self::REMARK])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $admin->id);
    }

    public function test_after_a_reassignment_only_the_new_assignee_can_change_the_status(): void
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $statusUri = '/api/service-requests/'.$serviceRequest->id.'/status';
        $payload = ['status' => ServiceRequestStatus::InProgress->value];

        $this->signInAs($assignee);
        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $newAssignee->id, 'remark' => self::REMARK])->assertOk();
        $this->patchJson($statusUri, $payload)->assertForbidden()->assertJsonPath('code', ErrorCode::Forbidden->value);

        $this->switchToFreshClient();
        $this->signInAs($newAssignee);
        $this->patchJson($statusUri, $payload)->assertOk()->assertJsonPath('data.status', ServiceRequestStatus::InProgress->value);
    }

    public function test_reassigning_to_the_current_assignee_returns_a_conflict(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $assignee->id, 'remark' => self::REMARK])
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::AssigneeUnchanged->value);

        $this->assertSame(1, ServiceRequestAssigneeHistory::query()->count());
    }

    #[DataProvider('invalidAssignees')]
    public function test_invalid_assignee_is_rejected_without_writing_anything(int|string $assigneeId): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        if ($assigneeId === 'requester') {
            $assigneeId = User::factory()->requester()->create()->id;
        }

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $assigneeId, 'remark' => self::REMARK])
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['assignee_id']);

        $this->assertSame(1, ServiceRequestAssigneeHistory::query()->count());
    }

    public function test_missing_remark_is_rejected_without_writing_anything(): void
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest), ['assignee_id' => $newAssignee->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['remark']);

        $this->assertSame(1, ServiceRequestAssigneeHistory::query()->count());
    }

    public function test_anyone_who_is_not_the_current_assignee_is_forbidden(): void
    {
        $assignee = User::factory()->technician()->create();
        $target = User::factory()->technician()->create();
        $assignedRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $openRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);
        $attempts = [
            [User::factory()->technician()->create(), $assignedRequest],
            [User::factory()->admin()->create(), $assignedRequest],
            [User::factory()->technician()->create(), $openRequest],
        ];

        foreach ($attempts as [$user, $serviceRequest]) {
            $this->switchToFreshClient();
            $this->signInAs($user);

            $this->postJson($this->uri($serviceRequest), ['assignee_id' => $target->id, 'remark' => self::REMARK])
                ->assertForbidden()
                ->assertJsonPath('code', ErrorCode::Forbidden->value);
        }

        $this->assertSame(1, ServiceRequestAssigneeHistory::query()->count());
    }

    private function uri(ServiceRequest $serviceRequest): string
    {
        return '/api/service-requests/'.$serviceRequest->id.'/reassignment';
    }
}
