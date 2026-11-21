<?php

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Models\User;
use App\Services\ServiceRequests\ServiceRequestService;
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

    public static function listRoutes(): array
    {
        return [
            'dashboard' => [self::DASHBOARD_ROUTE],
            'my requests' => [self::MY_REQUESTS_ROUTE],
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
    public function test_empty_filters_list_every_request(string $route): void
    {
        $requester = $this->signInAs(User::factory()->admin()->create());
        $this->createServiceRequestFor($requester);
        $this->createServiceRequestFor($requester);

        $this->getJson($route.'?status=&assignee_id=')
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

    private function routeWith(string $route, array $query): string
    {
        return $route.'?'.http_build_query($query);
    }
}
