<?php

// Verifies the paginated My Requests list.
// Covers ownership, the newest first order, the exposed fields, the current assignee and the eager loading rule.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ServiceRequestTag;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ListOwnServiceRequestsTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string MY_REQUESTS_ROUTE = '/api/service-requests/mine';

    private const array SUMMARY_FIELDS = ['id', 'title', 'status', 'tags', 'requested_by', 'assignee', 'created_at', 'available_actions'];

    private const array USER_REFERENCE_FIELDS = ['id', 'name'];

    private const string ISO_8601_UTC_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const int REQUESTS_ON_SECOND_PAGE = 2;

    private const int PAGE_BEYOND_THE_LAST = 99;

    private const int OTHER_USERS_REQUESTS = 2;

    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
            'requester' => ['requester'],
        ];
    }

    public function test_response_has_the_paginated_envelope(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->createServiceRequestFor($requester);

        $this->getJson(self::MY_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', ServiceRequestService::PAGE_SIZE)
            ->assertJsonPath('meta.total', 1);
    }

    #[DataProvider('roles')]
    public function test_only_the_signed_in_users_requests_are_listed(string $role): void
    {
        $user = $this->signInAs(User::factory()->{$role}()->create());
        $ownRequest = $this->createServiceRequestFor($user);

        foreach (range(1, self::OTHER_USERS_REQUESTS) as $ignored) {
            $this->createServiceRequestFor(User::factory()->requester()->create());
        }

        $this->getJson(self::MY_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownRequest->id)
            ->assertJsonPath('data.0.requested_by.id', $user->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_requests_are_listed_newest_first_across_pages(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $sharedTime = CarbonImmutable::now()->subDay();
        $requestCount = ServiceRequestService::PAGE_SIZE + self::REQUESTS_ON_SECOND_PAGE;
        $createdRequests = [];

        foreach (range(1, $requestCount) as $index) {
            $time = $index % 2 === 0 ? $sharedTime : $sharedTime->subMinutes($index);
            $createdRequests[] = $this->serviceRequestFactoryFor($requester)->createdAt($time)->create();
        }

        $expectedIds = collect($createdRequests)
            ->sort(fn ($first, $second): int => [$second->created_at, $second->id] <=> [$first->created_at, $first->id])
            ->pluck('id')
            ->values()
            ->all();

        $firstPage = $this->getJson(self::MY_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonCount(ServiceRequestService::PAGE_SIZE, 'data')
            ->json('data.*.id');

        $secondPage = $this->getJson(self::MY_REQUESTS_ROUTE.'?page=2')
            ->assertOk()
            ->assertJsonCount(self::REQUESTS_ON_SECOND_PAGE, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->json('data.*.id');

        $this->assertSame(array_slice($expectedIds, 0, ServiceRequestService::PAGE_SIZE), $firstPage);
        $this->assertSame(array_slice($expectedIds, ServiceRequestService::PAGE_SIZE), $secondPage);
    }

    public function test_each_request_exposes_only_the_summary_fields(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->createServiceRequestFor($requester, ServiceRequestTag::Software, ServiceRequestTag::Hardware);

        $item = $this->getJson(self::MY_REQUESTS_ROUTE)->assertOk()->json('data.0');

        $this->assertEqualsCanonicalizing(self::SUMMARY_FIELDS, array_keys($item));
        $this->assertMatchesRegularExpression(self::ISO_8601_UTC_PATTERN, $item['created_at']);
        $this->assertEqualsCanonicalizing(self::USER_REFERENCE_FIELDS, array_keys($item['requested_by']));
        $this->assertSame([ServiceRequestTag::Hardware->value, ServiceRequestTag::Software->value], $item['tags']);
    }

    public function test_requester_is_offered_no_actions(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->createServiceRequestFor($requester);

        $this->getJson(self::MY_REQUESTS_ROUTE)
            ->assertOk()
            ->assertJsonPath('data.0.available_actions', [
                'can_assign_to_self' => false,
                'can_reassign' => false,
                'allowed_statuses' => [],
            ]);
    }

    public function test_current_assignee_is_the_latest_assignee(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $firstTechnician = User::factory()->technician()->create();
        $secondTechnician = User::factory()->technician()->create();
        $unassignedRequest = $this->createServiceRequestFor($requester);
        $assignedRequest = $this->createServiceRequestFor($requester);

        $this->assignThroughHistory($assignedRequest, $firstTechnician);
        $this->assignThroughHistory($assignedRequest, $secondTechnician, $firstTechnician);

        $items = collect($this->getJson(self::MY_REQUESTS_ROUTE)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(
            ['id' => $secondTechnician->id, 'name' => $secondTechnician->name],
            $items[$assignedRequest->id]['assignee'],
        );
        $this->assertNull($items[$unassignedRequest->id]['assignee']);
    }

    public function test_page_beyond_the_last_is_empty(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->createServiceRequestFor($requester);

        $this->getJson(self::MY_REQUESTS_ROUTE.'?page='.self::PAGE_BEYOND_THE_LAST)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_query_count_does_not_grow_with_the_number_of_requests(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $technician = User::factory()->technician()->create();

        $this->assignThroughHistory($this->createServiceRequestFor($requester), $technician);
        $queriesForOneRequest = $this->countQueriesForMyRequests(1);

        foreach (range(2, ServiceRequestService::PAGE_SIZE) as $ignored) {
            $this->assignThroughHistory(
                $this->createServiceRequestFor($requester, ServiceRequestTag::Hardware, ServiceRequestTag::Network),
                $technician,
            );
        }
        $queriesForFullPage = $this->countQueriesForMyRequests(ServiceRequestService::PAGE_SIZE);

        $this->assertSame($queriesForOneRequest, $queriesForFullPage);
    }

    private function countQueriesForMyRequests(int $expectedCount): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(self::MY_REQUESTS_ROUTE)->assertOk()->assertJsonCount($expectedCount, 'data');

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }
}
