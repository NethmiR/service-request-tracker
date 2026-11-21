<?php

// Verifies the paginated request dashboard for technicians and admins.
// Covers every requester's requests, the newest first order, the exposed fields, the available actions and the eager loading rule.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ServiceRequestStatus;
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

class ListServiceRequestsTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string DASHBOARD_ROUTE = '/api/service-requests';

    private const array SUMMARY_FIELDS = ['id', 'title', 'status', 'priority', 'tags', 'requested_by', 'assignee', 'created_at', 'available_actions'];

    private const int REQUESTS_ON_SECOND_PAGE = 2;

    private const int PAGE_BEYOND_THE_LAST = 99;

    public static function staffRoles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_receive_every_request_from_every_requester(string $role): void
    {
        $this->signInAs(User::factory()->{$role}()->create());

        foreach (range(1, 3) as $ignored) {
            $this->createServiceRequestFor(User::factory()->requester()->create());
        }

        $this->getJson(self::DASHBOARD_ROUTE)
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', ServiceRequestService::PAGE_SIZE);
    }

    public function test_requests_are_listed_newest_first_across_pages(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $requester = User::factory()->requester()->create();
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

        $firstPage = $this->getJson(self::DASHBOARD_ROUTE)
            ->assertOk()
            ->assertJsonCount(ServiceRequestService::PAGE_SIZE, 'data')
            ->json('data.*.id');

        $secondPage = $this->getJson(self::DASHBOARD_ROUTE.'?page=2')
            ->assertOk()
            ->assertJsonCount(self::REQUESTS_ON_SECOND_PAGE, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->json('data.*.id');

        $this->assertSame(array_slice($expectedIds, 0, ServiceRequestService::PAGE_SIZE), $firstPage);
        $this->assertSame(array_slice($expectedIds, ServiceRequestService::PAGE_SIZE), $secondPage);
    }

    public function test_each_request_exposes_only_the_summary_fields(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $this->createServiceRequestFor(User::factory()->requester()->create(), ServiceRequestTag::Software);

        $item = $this->getJson(self::DASHBOARD_ROUTE)->assertOk()->json('data.0');

        $this->assertEqualsCanonicalizing(self::SUMMARY_FIELDS, array_keys($item));
        $this->assertEqualsCanonicalizing(
            ['can_assign_to_self', 'can_reassign', 'allowed_statuses'],
            array_keys($item['available_actions']),
        );
    }

    public function test_available_actions_follow_the_status_and_the_assignee(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create());
        $openRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);
        $ownRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $viewer);
        $otherRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned);

        $items = collect($this->getJson(self::DASHBOARD_ROUTE)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(
            ['can_assign_to_self' => true, 'can_reassign' => false, 'allowed_statuses' => []],
            $items[$openRequest->id]['available_actions'],
        );
        $this->assertSame(
            [
                'can_assign_to_self' => false,
                'can_reassign' => true,
                'allowed_statuses' => [ServiceRequestStatus::InProgress->value, ServiceRequestStatus::Resolved->value],
            ],
            $items[$ownRequest->id]['available_actions'],
        );
        $this->assertSame(
            ['can_assign_to_self' => false, 'can_reassign' => false, 'allowed_statuses' => []],
            $items[$otherRequest->id]['available_actions'],
        );
    }

    public function test_page_beyond_the_last_is_empty(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $this->createServiceRequestFor(User::factory()->requester()->create());

        $this->getJson(self::DASHBOARD_ROUTE.'?page='.self::PAGE_BEYOND_THE_LAST)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_query_count_does_not_grow_with_the_number_of_requests(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();
        $requester = User::factory()->requester()->create();

        $this->assignThroughHistory($this->createServiceRequestFor($requester), $technician);
        $queriesForOneRequest = $this->countQueriesForDashboard(1);

        foreach (range(2, ServiceRequestService::PAGE_SIZE) as $ignored) {
            $this->assignThroughHistory(
                $this->createServiceRequestFor($requester, ServiceRequestTag::Hardware, ServiceRequestTag::Network),
                $technician,
            );
        }
        $queriesForFullPage = $this->countQueriesForDashboard(ServiceRequestService::PAGE_SIZE);

        $this->assertSame($queriesForOneRequest, $queriesForFullPage);
    }

    private function countQueriesForDashboard(int $expectedCount): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(self::DASHBOARD_ROUTE)->assertOk()->assertJsonCount($expectedCount, 'data');

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }
}
