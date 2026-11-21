<?php

// Verifies viewing one service request with its status history and assignee history.
// Covers who may view it, the not found response for other requesters, history order and the exposed fields.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ShowServiceRequestTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string SERVICE_REQUESTS_ROUTE = '/api/service-requests/';

    private const int UNKNOWN_SERVICE_REQUEST_ID = 999999;

    private const int MINUTES_BETWEEN_ASSIGNMENTS = 5;

    private const array SENSITIVE_KEYS = ['email', 'password', 'remember_token', 'requested_by_user_id'];

    public static function staffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
        ];
    }

    public function test_requester_receives_exactly_the_detail_fields_for_their_own_request(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $serviceRequest = $this->createServiceRequestFor($requester, ServiceRequestTag::Network, ServiceRequestTag::Software);
        $initialRecord = ServiceRequestStatusHistory::query()->sole();

        $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $serviceRequest->id,
                    'title' => $serviceRequest->title,
                    'description' => $serviceRequest->description,
                    'status' => ServiceRequestStatus::Open->value,
                    'tags' => [ServiceRequestTag::Software->value, ServiceRequestTag::Network->value],
                    'requested_by' => ['id' => $requester->id, 'name' => $requester->name],
                    'assignee' => null,
                    'created_at' => $serviceRequest->created_at->toIso8601ZuluString(),
                    'available_actions' => [
                        'can_assign_to_self' => false,
                        'can_reassign' => false,
                        'allowed_statuses' => [],
                    ],
                    'status_history' => [
                        [
                            'id' => $initialRecord->id,
                            'from_status' => null,
                            'to_status' => ServiceRequestStatus::Open->value,
                            'changed_by' => null,
                            'remark' => null,
                            'created_at' => $initialRecord->created_at->toIso8601ZuluString(),
                        ],
                    ],
                    'assignee_history' => [],
                ],
            ]);
    }

    public function test_other_requester_receives_the_same_not_found_body_as_an_unknown_id(): void
    {
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());

        $this->signInAs(User::factory()->requester()->create());

        $deniedBody = $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)
            ->assertNotFound()
            ->assertJsonPath('code', ErrorCode::NotFound->value)
            ->json();

        $unknownBody = $this->getJson(self::SERVICE_REQUESTS_ROUTE.self::UNKNOWN_SERVICE_REQUEST_ID)
            ->assertNotFound()
            ->json();

        $this->assertSame($unknownBody, $deniedBody);
    }

    #[DataProvider('staffRoles')]
    public function test_staff_can_open_a_request_raised_by_someone_else(string $role): void
    {
        $requester = User::factory()->requester()->create();
        $serviceRequest = $this->createServiceRequestFor($requester);

        $this->signInAs(User::factory()->{$role}()->create());

        $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)
            ->assertOk()
            ->assertJsonPath('data.id', $serviceRequest->id)
            ->assertJsonPath('data.requested_by.id', $requester->id);
    }

    public function test_histories_are_listed_newest_first_with_the_latest_assignee(): void
    {
        $requester = User::factory()->requester()->create();
        $firstTechnician = User::factory()->technician()->create();
        $secondTechnician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestFor($requester);

        $this->travel(self::MINUTES_BETWEEN_ASSIGNMENTS)->minutes();
        $this->assignThroughHistory($serviceRequest, $firstTechnician);
        $this->travel(self::MINUTES_BETWEEN_ASSIGNMENTS)->minutes();
        $this->assignThroughHistory($serviceRequest, $secondTechnician, $firstTechnician);

        $this->signInAs($requester);

        $data = $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)->assertOk()->json('data');

        $this->assertSame(['id' => $secondTechnician->id, 'name' => $secondTechnician->name], $data['assignee']);

        $this->assertCount(2, $data['assignee_history']);
        $this->assertSame($secondTechnician->id, $data['assignee_history'][0]['assigned_to']['id']);
        $this->assertSame($firstTechnician->id, $data['assignee_history'][0]['assigned_from']['id']);
        $this->assertSame($firstTechnician->id, $data['assignee_history'][1]['assigned_to']['id']);
        $this->assertNull($data['assignee_history'][1]['assigned_from']);

        $this->assertSame(ServiceRequestStatus::Assigned->value, $data['status']);
        $this->assertCount(2, $data['status_history']);
        $this->assertSame($firstTechnician->id, $data['status_history'][0]['changed_by']['id']);
        $this->assertSame(ServiceRequestStatus::Open->value, $data['status_history'][0]['from_status']);
        $this->assertSame(ServiceRequestStatus::Assigned->value, $data['status_history'][0]['to_status']);
        $this->assertNull($data['status_history'][1]['from_status']);
        $this->assertNull($data['status_history'][1]['changed_by']);
        $this->assertSame(ServiceRequestStatus::Open->value, $data['status_history'][1]['to_status']);

        $this->assertDescendingTimes(array_column($data['status_history'], 'created_at'));
        $this->assertDescendingTimes(array_column($data['assignee_history'], 'created_at'));
    }

    public function test_history_records_written_in_the_same_second_are_ordered_by_id(): void
    {
        $this->freezeSecond();

        $requester = User::factory()->requester()->create();
        $firstTechnician = User::factory()->technician()->create();
        $secondTechnician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestFor($requester);

        $this->assignThroughHistory($serviceRequest, $firstTechnician);
        $this->assignThroughHistory($serviceRequest, $secondTechnician, $firstTechnician);

        $this->signInAs($requester);

        $data = $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)->assertOk()->json('data');

        $statusIds = array_column($data['status_history'], 'id');
        $assigneeIds = array_column($data['assignee_history'], 'id');

        $this->assertCount(1, array_unique(array_column($data['status_history'], 'created_at')));
        $this->assertSame(array_values(collect($statusIds)->sortDesc()->all()), $statusIds);
        $this->assertSame(array_values(collect($assigneeIds)->sortDesc()->all()), $assigneeIds);
        $this->assertSame($secondTechnician->id, $data['assignee']['id']);
    }

    public function test_body_never_contains_sensitive_fields(): void
    {
        $requester = User::factory()->requester()->create();
        $technician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestFor($requester);
        $this->assignThroughHistory($serviceRequest, $technician);

        $this->signInAs($requester);

        $response = $this->getJson(self::SERVICE_REQUESTS_ROUTE.$serviceRequest->id)->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString($requester->email, $content);
        $this->assertStringNotContainsString($technician->email, $content);
        $this->assertStringNotContainsString($requester->password, $content);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            $this->assertStringNotContainsString('"'.$sensitiveKey.'"', $content);
        }
    }

    private function assertDescendingTimes(array $times): void
    {
        $sorted = $times;
        rsort($sorted);

        $this->assertSame($sorted, $times);
    }
}
