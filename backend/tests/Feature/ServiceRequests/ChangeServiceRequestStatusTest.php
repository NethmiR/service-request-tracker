<?php

// Verifies changing the status of a request by its current assignee.
// Covers every allowed transition, remark handling, no op and prohibited transitions, and who may change the status.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ChangeServiceRequestStatusTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string REMARK = 'Replacing the network adapter.';

    public static function allowedTransitions(): array
    {
        return [
            'assigned to in progress' => [ServiceRequestStatus::Assigned, ServiceRequestStatus::InProgress],
            'assigned to resolved' => [ServiceRequestStatus::Assigned, ServiceRequestStatus::Resolved],
            'in progress to assigned' => [ServiceRequestStatus::InProgress, ServiceRequestStatus::Assigned],
            'in progress to resolved' => [ServiceRequestStatus::InProgress, ServiceRequestStatus::Resolved],
            'resolved to assigned' => [ServiceRequestStatus::Resolved, ServiceRequestStatus::Assigned],
            'resolved to in progress' => [ServiceRequestStatus::Resolved, ServiceRequestStatus::InProgress],
        ];
    }

    public static function emptyRemarks(): array
    {
        return [
            'missing' => [[]],
            'null' => [['remark' => null]],
            'blank' => [['remark' => '   ']],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_assignee_makes_every_allowed_transition(ServiceRequestStatus $from, ServiceRequestStatus $to): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus($from, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), ['status' => $to->value, 'remark' => '  '.self::REMARK.'  '])
            ->assertOk()
            ->assertJsonPath('data.status', $to->value)
            ->assertJsonPath('data.available_actions.allowed_statuses', array_map(
                fn (ServiceRequestStatus $status): string => $status->value,
                $to->allowedTargets(),
            ));

        $this->assertSame($statusRecordCount + 1, ServiceRequestStatusHistory::query()->count());

        $record = ServiceRequestStatusHistory::query()->latest('id')->first();

        $this->assertSame($from, $record->from_status);
        $this->assertSame($to, $record->to_status);
        $this->assertSame($assignee->id, $record->changed_by_user_id);
        $this->assertSame(self::REMARK, $record->remark);
        $this->assertSame($to, $serviceRequest->refresh()->status);
    }

    #[DataProvider('emptyRemarks')]
    public function test_empty_remark_is_stored_as_null(array $payload): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), ['status' => ServiceRequestStatus::InProgress->value, ...$payload])
            ->assertOk();

        $this->assertNull(ServiceRequestStatusHistory::query()->latest('id')->first()->remark);
    }

    public function test_remark_over_the_limit_is_rejected(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), [
            'status' => ServiceRequestStatus::InProgress->value,
            'remark' => str_repeat('a', ServiceRequestLimits::REMARK_MAX_LENGTH + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors(['remark']);

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
    }

    public function test_setting_the_current_status_is_a_conflict(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), ['status' => ServiceRequestStatus::Assigned->value])
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::StatusUnchanged->value);

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
    }

    public function test_setting_open_is_a_prohibited_transition(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::InProgress, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), ['status' => ServiceRequestStatus::Open->value])
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::InvalidStatusTransition->value);

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
        $this->assertSame(ServiceRequestStatus::InProgress, $serviceRequest->refresh()->status);
    }

    public function test_unknown_status_value_is_rejected(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest), ['status' => 'DONE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
    }

    public function test_user_who_is_not_the_assignee_is_forbidden(): void
    {
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned);
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();

        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create()] as $user) {
            $this->switchToFreshClient();
            $this->signInAs($user);

            $this->patchJson($this->uri($serviceRequest), ['status' => ServiceRequestStatus::InProgress->value])
                ->assertForbidden()
                ->assertJsonPath('code', ErrorCode::Forbidden->value);
        }

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
        $this->assertSame(ServiceRequestStatus::Assigned, $serviceRequest->refresh()->status);
    }

    private function uri(ServiceRequest $serviceRequest): string
    {
        return '/api/service-requests/'.$serviceRequest->id.'/status';
    }
}
