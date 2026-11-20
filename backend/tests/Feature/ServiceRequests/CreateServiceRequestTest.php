<?php

// Verifies creating a service request through the API.
// Covers the response shape, the initial status record, trimming, ignored client fields and every validation failure.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\Tag;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class CreateServiceRequestTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string SERVICE_REQUESTS_ROUTE = '/api/service-requests';

    private const string TAG_PIVOT_TABLE = 'service_request_tag';

    private const string TITLE = 'Laptop does not connect to the office Wi-Fi';

    private const string DESCRIPTION = 'Since this morning the laptop cannot see the office network.';

    private const array DETAIL_FIELDS = [
        'id',
        'title',
        'status',
        'tags',
        'requested_by',
        'assignee',
        'created_at',
        'available_actions',
        'description',
        'status_history',
        'assignee_history',
    ];

    private const array STATUS_HISTORY_FIELDS = ['id', 'from_status', 'to_status', 'changed_by', 'remark', 'created_at'];

    private const int CLIENT_SUPPLIED_ID = 999999;

    private const string CLIENT_SUPPLIED_CREATED_AT = '2000-01-01T00:00:00Z';

    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
            'requester' => ['requester'],
        ];
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing title' => [['title' => null], 'title'],
            'title too long' => [['title' => str_repeat('a', ServiceRequestLimits::TITLE_MAX_LENGTH + 1)], 'title'],
            'blank title' => [['title' => '   '], 'title'],
            'missing description' => [['description' => null], 'description'],
            'description too long' => [
                ['description' => str_repeat('a', ServiceRequestLimits::DESCRIPTION_MAX_LENGTH + 1)],
                'description',
            ],
            'missing tags' => [['tags' => null], 'tags'],
            'empty tag list' => [['tags' => []], 'tags'],
            'tags not a list' => [['tags' => ['first' => ServiceRequestTag::Hardware->value]], 'tags'],
            'tags as a string' => [['tags' => ServiceRequestTag::Hardware->value], 'tags'],
            'duplicate tags' => [
                ['tags' => [ServiceRequestTag::Hardware->value, ServiceRequestTag::Hardware->value]],
                'tags.0',
            ],
            'unknown tag' => [['tags' => ['PRINTER']], 'tags.0'],
        ];
    }

    public function test_valid_request_returns_the_created_request(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());

        $response = $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payload(tags: [
            ServiceRequestTag::Network->value,
            ServiceRequestTag::Hardware->value,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.title', self::TITLE)
            ->assertJsonPath('data.description', self::DESCRIPTION)
            ->assertJsonPath('data.status', ServiceRequestStatus::Open->value)
            ->assertJsonPath('data.tags', [ServiceRequestTag::Hardware->value, ServiceRequestTag::Network->value])
            ->assertJsonPath('data.requested_by', ['id' => $requester->id, 'name' => $requester->name])
            ->assertJsonPath('data.assignee', null)
            ->assertJsonPath('data.assignee_history', [])
            ->assertJsonPath('data.available_actions', ['can_assign_to_self' => false, 'can_reassign' => false, 'allowed_statuses' => []]);

        $this->assertEqualsCanonicalizing(self::DETAIL_FIELDS, array_keys($response->json('data')));

        $serviceRequest = ServiceRequest::query()->sole();

        $this->assertSame($serviceRequest->id, $response->json('data.id'));
        $this->assertSame($serviceRequest->created_at->toIso8601ZuluString(), $response->json('data.created_at'));
    }

    public function test_creation_writes_exactly_one_initial_status_record(): void
    {
        $this->signInAs(User::factory()->requester()->create());

        $response = $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payload())->assertCreated();

        $response->assertJsonCount(1, 'data.status_history')
            ->assertJsonPath('data.status_history.0.from_status', null)
            ->assertJsonPath('data.status_history.0.to_status', ServiceRequestStatus::Open->value)
            ->assertJsonPath('data.status_history.0.changed_by', null)
            ->assertJsonPath('data.status_history.0.remark', null);

        $this->assertEqualsCanonicalizing(
            self::STATUS_HISTORY_FIELDS,
            array_keys($response->json('data.status_history.0')),
        );

        $history = ServiceRequestStatusHistory::query()->sole();

        $this->assertSame($response->json('data.id'), $history->service_request_id);
        $this->assertTrue($history->isInitialRecord());
        $this->assertNull($history->changed_by_user_id);
        $this->assertSame(ServiceRequestStatus::Open, $history->to_status);
    }

    public function test_title_and_description_are_stored_trimmed(): void
    {
        $this->signInAs(User::factory()->requester()->create());

        $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payload(
            title: '  '.self::TITLE.'  ',
            description: "\n ".self::DESCRIPTION." \t",
        ))->assertCreated();

        $serviceRequest = ServiceRequest::query()->sole();

        $this->assertSame(self::TITLE, $serviceRequest->title);
        $this->assertSame(self::DESCRIPTION, $serviceRequest->description);
    }

    #[DataProvider('roles')]
    public function test_each_role_can_create_a_request(string $role): void
    {
        $user = $this->signInAs(User::factory()->{$role}()->create());

        $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.requested_by.id', $user->id);

        $this->assertSame($user->id, ServiceRequest::query()->sole()->requested_by_user_id);
    }

    public function test_client_supplied_server_fields_are_ignored(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $otherUser = User::factory()->technician()->create();

        $response = $this->postJson(self::SERVICE_REQUESTS_ROUTE, [
            ...$this->payload(),
            'id' => self::CLIENT_SUPPLIED_ID,
            'status' => ServiceRequestStatus::Resolved->value,
            'requested_by_user_id' => $otherUser->id,
            'requested_by' => ['id' => $otherUser->id, 'name' => $otherUser->name],
            'created_at' => self::CLIENT_SUPPLIED_CREATED_AT,
        ])->assertCreated();

        $serviceRequest = ServiceRequest::query()->sole();

        $this->assertNotSame(self::CLIENT_SUPPLIED_ID, $serviceRequest->id);
        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->status);
        $this->assertSame($requester->id, $serviceRequest->requested_by_user_id);
        $this->assertNotSame(self::CLIENT_SUPPLIED_CREATED_AT, $response->json('data.created_at'));
        $response->assertJsonPath('data.requested_by.id', $requester->id);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_without_writing_anything(array $overrides, string $field): void
    {
        $this->signInAs(User::factory()->requester()->create());

        $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payloadWith($overrides))
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors([$field]);

        $this->assertNothingWasWritten();
    }

    public function test_tag_without_a_stored_row_is_rejected(): void
    {
        $this->signInAs(User::factory()->requester()->create());

        Tag::query()->where('name', ServiceRequestTag::Other->value)->delete();

        $this->postJson(self::SERVICE_REQUESTS_ROUTE, $this->payload(tags: [ServiceRequestTag::Other->value]))
            ->assertUnprocessable()
            ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
            ->assertJsonValidationErrors(['tags.0']);

        $this->assertNothingWasWritten();
    }

    private function payload(
        string $title = self::TITLE,
        string $description = self::DESCRIPTION,
        array $tags = [ServiceRequestTag::Hardware->value],
    ): array {
        return ['title' => $title, 'description' => $description, 'tags' => $tags];
    }

    private function payloadWith(array $overrides): array
    {
        return array_filter(
            array_replace($this->payload(), $overrides),
            fn (mixed $value): bool => $value !== null,
        );
    }

    private function assertNothingWasWritten(): void
    {
        $this->assertSame(0, ServiceRequest::query()->count());
        $this->assertSame(0, DB::table(self::TAG_PIVOT_TABLE)->count());
        $this->assertSame(0, ServiceRequestStatusHistory::query()->count());
    }
}
