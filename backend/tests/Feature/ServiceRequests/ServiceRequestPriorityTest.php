<?php

// Verifies that every request response carries the priority chosen on creation.
// Confirms that self assignment, reassignment and status changes never change the stored priority, even when a different one is sent.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ServiceRequestPriorityTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string SERVICE_REQUESTS_ROUTE = '/api/service-requests';

    private const string MY_REQUESTS_ROUTE = '/api/service-requests/mine';

    private const string REMARK = 'Handled while checking the priority.';

    private const ServiceRequestPriority STORED_PRIORITY = ServiceRequestPriority::Low;

    private const ServiceRequestPriority SENT_PRIORITY = ServiceRequestPriority::Critical;

    public function test_priority_is_returned_in_the_detail_and_both_lists(): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());
        $serviceRequest = $this->createRequestWithStoredPriority($admin);

        $this->getJson(self::SERVICE_REQUESTS_ROUTE.'/'.$serviceRequest->id)
            ->assertOk()
            ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);

        $this->getJson(self::MY_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.0.id', $serviceRequest->id)
            ->assertJsonPath('data.0.priority', self::STORED_PRIORITY->value);

        $this->getJson(self::SERVICE_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.0.id', $serviceRequest->id)
            ->assertJsonPath('data.0.priority', self::STORED_PRIORITY->value);
    }

    public function test_self_assignment_ignores_a_sent_priority(): void
    {
        $technician = $this->signInAs(User::factory()->technician()->create());
        $serviceRequest = $this->createRequestWithStoredPriority(User::factory()->requester()->create());

        $this->postJson($this->uri($serviceRequest, 'assignment'), [
            'remark' => self::REMARK,
            'priority' => self::SENT_PRIORITY->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $technician->id)
            ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);

        $this->assertStoredPriorityIsUnchanged($serviceRequest);
    }

    public function test_reassignment_ignores_a_sent_priority(): void
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createRequestWithStoredPriority(User::factory()->requester()->create());
        $this->assignThroughWorkflow($serviceRequest, $assignee);

        $this->signInAs($assignee);

        $this->postJson($this->uri($serviceRequest, 'reassignment'), [
            'assignee_id' => $newAssignee->id,
            'remark' => self::REMARK,
            'priority' => self::SENT_PRIORITY->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $newAssignee->id)
            ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);

        $this->assertStoredPriorityIsUnchanged($serviceRequest);
    }

    public function test_status_change_ignores_a_sent_priority(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createRequestWithStoredPriority(User::factory()->requester()->create());
        $this->assignThroughWorkflow($serviceRequest, $assignee);

        $this->signInAs($assignee);

        $this->patchJson($this->uri($serviceRequest, 'status'), [
            'status' => ServiceRequestStatus::InProgress->value,
            'remark' => self::REMARK,
            'priority' => self::SENT_PRIORITY->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceRequestStatus::InProgress->value)
            ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);

        $this->assertStoredPriorityIsUnchanged($serviceRequest);
    }

    public function test_request_keeps_its_priority_after_it_is_resolved(): void
    {
        $assignee = User::factory()->admin()->create();
        $serviceRequest = $this->createRequestWithStoredPriority(User::factory()->requester()->create());
        $this->assignThroughWorkflow($serviceRequest, $assignee);

        $this->signInAs($assignee);

        foreach ([ServiceRequestStatus::InProgress, ServiceRequestStatus::Resolved] as $status) {
            $this->patchJson($this->uri($serviceRequest, 'status'), ['status' => $status->value])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value)
                ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);
        }

        $this->getJson(self::SERVICE_REQUESTS_ROUTE.'/'.$serviceRequest->id)
            ->assertOk()
            ->assertJsonPath('data.status', ServiceRequestStatus::Resolved->value)
            ->assertJsonPath('data.priority', self::STORED_PRIORITY->value);

        $this->assertStoredPriorityIsUnchanged($serviceRequest);
    }

    private function createRequestWithStoredPriority(User $requester): ServiceRequest
    {
        return $this->serviceRequestFactoryFor($requester)->withPriority(self::STORED_PRIORITY)->create();
    }

    private function assertStoredPriorityIsUnchanged(ServiceRequest $serviceRequest): void
    {
        $this->assertSame(self::STORED_PRIORITY, $serviceRequest->refresh()->priorityTag->name);
    }

    private function uri(ServiceRequest $serviceRequest, string $action): string
    {
        return self::SERVICE_REQUESTS_ROUTE.'/'.$serviceRequest->id.'/'.$action;
    }
}
