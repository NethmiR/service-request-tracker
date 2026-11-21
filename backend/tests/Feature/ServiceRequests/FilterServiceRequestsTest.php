<?php

// Verifies the server side filters of the dashboard and My Requests.
// Covers the status, assignee and priority filters, the title search, their combination, the pagination links and invalid values.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestService;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class FilterServiceRequestsTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string DASHBOARD_ROUTE = '/api/service-requests';

    private const string MY_REQUESTS_ROUTE = '/api/service-requests/mine';

    private const int UNKNOWN_USER_ID = 999999;

    private const int REQUESTS_ON_SECOND_PAGE = 1;

    private const string PRINTER_TITLE = 'Printer on the second floor jams';

    private const string LAPTOP_TITLE = 'Laptop battery drains quickly';

    private const string MONITOR_TITLE = 'Monitor flickers after the update';

    public static function listRoutes(): array
    {
        return [
            'dashboard' => [self::DASHBOARD_ROUTE],
            'my requests' => [self::MY_REQUESTS_ROUTE],
        ];
    }

    public static function searchTermsPerList(): array
    {
        $terms = [
            'start of the title' => 'Printer',
            'middle of the title' => 'second fl',
            'end of the title' => 'jams',
            'different case' => 'pRiNtEr ON THE',
            'surrounding spaces' => '   second floor   ',
        ];
        $cases = [];

        foreach (self::listRoutes() as $list => [$route]) {
            foreach ($terms as $description => $term) {
                $cases["{$list}, {$description}"] = [$route, $term];
            }
        }

        return $cases;
    }

    public static function literalSearchCharacters(): array
    {
        return [
            'percent sign' => ['100%', 'Disk at 100% on the file server', 'Upgrade 1000 users to the new client'],
            'underscore' => ['user_id', 'Report shows user_id instead of the name', 'Report shows userXid in the header'],
            'backslash' => ['C:\\Temp', 'Cannot write to C:\\Temp folder', 'Cannot write to C:Temp folder'],
        ];
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown status' => [['status' => 'CLOSED'], 'status'],
            'lowercase status' => [['status' => 'open'], 'status'],
            'status list' => [['status' => [ServiceRequestStatus::Open->value]], 'status'],
            'text assignee' => [['assignee_id' => 'abc'], 'assignee_id'],
            'decimal assignee' => [['assignee_id' => '1.5'], 'assignee_id'],
            'assignee list' => [['assignee_id' => ['1']], 'assignee_id'],
            'unknown assignee' => [['assignee_id' => self::UNKNOWN_USER_ID], 'assignee_id'],
            'zero page' => [['page' => 0], 'page'],
            'text page' => [['page' => 'abc'], 'page'],
            'unknown priority' => [['priority' => 'URGENT'], 'priority'],
            'lowercase priority' => [['priority' => 'high'], 'priority'],
            'priority list' => [['priority' => [ServiceRequestPriority::High->value]], 'priority'],
            'search too long' => [['search' => str_repeat('a', ServiceRequestLimits::TITLE_MAX_LENGTH + 1)], 'search'],
            'search list' => [['search' => ['printer']], 'search'],
        ];
    }

    public function test_dashboard_is_filtered_by_status(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $this->createServiceRequestInStatus(ServiceRequestStatus::Open);
        $inProgress = $this->createServiceRequestInStatus(ServiceRequestStatus::InProgress);
        $this->createServiceRequestInStatus(ServiceRequestStatus::Resolved);

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, ['status' => ServiceRequestStatus::InProgress->value]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inProgress->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_dashboard_is_filtered_by_the_current_assignee(): void
    {
        $this->signInAs(User::factory()->admin()->create());
        $technician = User::factory()->technician()->create();
        $otherTechnician = User::factory()->technician()->create();
        $assignedRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $technician);
        $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $otherTechnician);
        $this->createServiceRequestInStatus(ServiceRequestStatus::Open);

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, ['assignee_id' => $technician->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedRequest->id)
            ->assertJsonPath('data.0.assignee.id', $technician->id);
    }

    public function test_assignee_filter_ignores_previous_assignees(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $previousAssignee = User::factory()->technician()->create();
        $currentAssignee = User::factory()->admin()->create();
        $serviceRequest = $this->createServiceRequestFor(User::factory()->requester()->create());
        $this->assignThroughHistory($serviceRequest, $previousAssignee);
        $this->assignThroughHistory($serviceRequest, $currentAssignee, $previousAssignee);

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, ['assignee_id' => $previousAssignee->id]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, ['assignee_id' => $currentAssignee->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $serviceRequest->id);
    }

    public function test_status_and_assignee_filters_are_combined(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();
        $resolved = $this->createServiceRequestInStatus(ServiceRequestStatus::Resolved, $technician);
        $this->createServiceRequestInStatus(ServiceRequestStatus::InProgress, $technician);
        $this->createServiceRequestInStatus(ServiceRequestStatus::Resolved);

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, [
            'status' => ServiceRequestStatus::Resolved->value,
            'assignee_id' => $technician->id,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $resolved->id);
    }

    public function test_my_requests_are_filtered_within_the_signed_in_users_requests(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $technician = User::factory()->technician()->create();
        $ownAssigned = $this->createServiceRequestFor($requester);
        $this->assignThroughHistory($ownAssigned, $technician);
        $this->createServiceRequestFor($requester);
        $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $technician);

        $this->getJson($this->routeWith(self::MY_REQUESTS_ROUTE, ['assignee_id' => $technician->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownAssigned->id);

        $this->getJson($this->routeWith(self::MY_REQUESTS_ROUTE, ['status' => ServiceRequestStatus::Assigned->value]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownAssigned->id);
    }

    #[DataProvider('listRoutes')]
    public function test_list_is_filtered_by_priority(string $route): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());
        $high = $this->createRequest($admin, priority: ServiceRequestPriority::High);
        $this->createRequest($admin, priority: ServiceRequestPriority::Low);
        $this->createRequest($admin, priority: ServiceRequestPriority::Critical);

        $this->getJson($this->routeWith($route, ['priority' => ServiceRequestPriority::High->value]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $high->id)
            ->assertJsonPath('data.0.priority', ServiceRequestPriority::High->value)
            ->assertJsonPath('meta.total', 1);
    }

    #[DataProvider('searchTermsPerList')]
    public function test_search_matches_any_part_of_the_title(string $route, string $term): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());
        $printer = $this->createRequest($admin, self::PRINTER_TITLE);
        $this->createRequest($admin, self::LAPTOP_TITLE);
        $this->createRequest($admin, self::MONITOR_TITLE);

        $this->getJson($this->routeWith($route, ['search' => $term]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $printer->id);
    }

    #[DataProvider('literalSearchCharacters')]
    public function test_search_matches_wildcard_characters_literally(string $term, string $matchingTitle, string $otherTitle): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());
        $matching = $this->createRequest($admin, $matchingTitle);
        $this->createRequest($admin, $otherTitle);

        foreach (self::listRoutes() as [$route]) {
            $this->getJson($this->routeWith($route, ['search' => $term]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $matching->id);
        }
    }

    public function test_priority_search_status_and_assignee_are_combined_on_the_dashboard(): void
    {
        $this->signInAs(User::factory()->technician()->create());
        $technician = User::factory()->technician()->create();
        $requester = User::factory()->requester()->create();
        $match = $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::High);
        $this->assignThroughHistory($match, $technician);
        $wrongPriority = $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::Low);
        $this->assignThroughHistory($wrongPriority, $technician);
        $wrongTitle = $this->createRequest($requester, self::LAPTOP_TITLE, ServiceRequestPriority::High);
        $this->assignThroughHistory($wrongTitle, $technician);
        $wrongAssignee = $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::High);
        $this->assignThroughHistory($wrongAssignee, User::factory()->technician()->create());
        $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::High);

        $this->getJson($this->routeWith(self::DASHBOARD_ROUTE, [
            'status' => ServiceRequestStatus::Assigned->value,
            'assignee_id' => $technician->id,
            'priority' => ServiceRequestPriority::High->value,
            'search' => 'printer',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_priority_and_search_on_my_requests_stay_within_the_signed_in_users_requests(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $technician = User::factory()->technician()->create();
        $own = $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::Critical);
        $this->assignThroughHistory($own, $technician);
        $this->createRequest($requester, self::PRINTER_TITLE, ServiceRequestPriority::Low);
        $others = $this->createRequest(User::factory()->requester()->create(), self::PRINTER_TITLE, ServiceRequestPriority::Critical);
        $this->assignThroughHistory($others, $technician);

        $this->getJson($this->routeWith(self::MY_REQUESTS_ROUTE, [
            'status' => ServiceRequestStatus::Assigned->value,
            'assignee_id' => $technician->id,
            'priority' => ServiceRequestPriority::Critical->value,
            'search' => 'second floor',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.requested_by.id', $requester->id);
    }

    #[DataProvider('listRoutes')]
    public function test_pagination_links_keep_the_priority_and_the_search(string $route): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());

        foreach (range(1, ServiceRequestService::PAGE_SIZE + self::REQUESTS_ON_SECOND_PAGE) as $ignored) {
            $this->createRequest($admin, self::PRINTER_TITLE, ServiceRequestPriority::High);
        }

        $this->createRequest($admin, self::LAPTOP_TITLE, ServiceRequestPriority::High);
        $this->createRequest($admin, self::PRINTER_TITLE, ServiceRequestPriority::Low);

        $filters = ['priority' => ServiceRequestPriority::High->value, 'search' => '  printer  '];
        $response = $this->getJson($this->routeWith($route, $filters))
            ->assertOk()
            ->assertJsonPath('meta.total', ServiceRequestService::PAGE_SIZE + self::REQUESTS_ON_SECOND_PAGE)
            ->assertJsonPath('meta.last_page', 2);

        $nextLink = (string) $response->json('links.next');

        $this->assertStringContainsString('priority='.ServiceRequestPriority::High->value, $nextLink);
        $this->assertStringContainsString('search=printer', $nextLink);
        $this->assertStringNotContainsString('search=+', $nextLink);
        $this->assertStringNotContainsString('search=%20', $nextLink);
        $this->assertStringContainsString('page=2', $nextLink);

        $this->getJson($this->routeWith($route, [...$filters, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(self::REQUESTS_ON_SECOND_PAGE, 'data');
    }

    #[DataProvider('listRoutes')]
    public function test_blank_search_lists_every_request(string $route): void
    {
        $admin = $this->signInAs(User::factory()->admin()->create());
        $this->createRequest($admin, self::PRINTER_TITLE);
        $this->createRequest($admin, self::LAPTOP_TITLE);

        $this->getJson($this->routeWith($route, ['search' => '   ']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[DataProvider('listRoutes')]
    public function test_search_at_the_title_limit_is_accepted(string $route): void
    {
        $this->signInAs(User::factory()->admin()->create());

        $this->getJson($this->routeWith($route, ['search' => str_repeat('a', ServiceRequestLimits::TITLE_MAX_LENGTH)]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[DataProvider('listRoutes')]
    public function test_empty_filters_list_every_request(string $route): void
    {
        $requester = $this->signInAs(User::factory()->admin()->create());
        $this->createServiceRequestFor($requester);
        $this->createServiceRequestFor($requester);

        $this->getJson($route.'?status=&assignee_id=&priority=&search=')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[DataProvider('listRoutes')]
    public function test_pagination_links_keep_the_filters(string $route): void
    {
        $requester = $this->signInAs(User::factory()->admin()->create());

        foreach (range(1, ServiceRequestService::PAGE_SIZE + self::REQUESTS_ON_SECOND_PAGE) as $ignored) {
            $this->createServiceRequestFor($requester);
        }

        $response = $this->getJson($this->routeWith($route, ['status' => ServiceRequestStatus::Open->value, 'unknown' => 'value']))
            ->assertOk()
            ->assertJsonPath('meta.last_page', 2);

        $nextLink = (string) $response->json('links.next');

        $this->assertStringContainsString('status='.ServiceRequestStatus::Open->value, $nextLink);
        $this->assertStringContainsString('page=2', $nextLink);
        $this->assertStringNotContainsString('unknown', $nextLink);

        $this->getJson($this->routeWith($route, ['status' => ServiceRequestStatus::Open->value, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(self::REQUESTS_ON_SECOND_PAGE, 'data');
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $query, string $field): void
    {
        $this->signInAs(User::factory()->admin()->create());

        foreach (self::listRoutes() as [$route]) {
            $this->getJson($this->routeWith($route, $query))
                ->assertUnprocessable()
                ->assertJsonPath('code', ErrorCode::ValidationFailed->value)
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_requester_is_rejected_as_an_assignee(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());

        $this->getJson($this->routeWith(self::MY_REQUESTS_ROUTE, ['assignee_id' => $requester->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);
    }

    private function createRequest(
        User $requester,
        ?string $title = null,
        ServiceRequestPriority $priority = ServiceRequestPriority::Medium,
    ): ServiceRequest {
        $factory = $this->serviceRequestFactoryFor($requester)->withPriority($priority);

        return ($title === null ? $factory : $factory->state(['title' => $title]))->create();
    }

    private function routeWith(string $route, array $query): string
    {
        return $route.'?'.http_build_query($query);
    }
}
