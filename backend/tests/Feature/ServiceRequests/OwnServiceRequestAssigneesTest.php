<?php

namespace Tests\Feature\ServiceRequests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class OwnServiceRequestAssigneesTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string MY_ASSIGNEES_ROUTE = '/api/service-requests/mine/assignees';

    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'technician' => ['technician'],
            'requester' => ['requester'],
        ];
    }

    #[DataProvider('roles')]
    public function test_list_holds_only_the_current_assignees_of_own_requests(string $role): void
    {
        $user = $this->signInAs(User::factory()->{$role}()->create());
        $previousAssignee = User::factory()->technician()->create();
        $currentAssignee = User::factory()->admin()->create();
        $otherUsersAssignee = User::factory()->technician()->create();

        $reassignedRequest = $this->createServiceRequestFor($user);
        $this->assignThroughHistory($reassignedRequest, $previousAssignee);
        $this->assignThroughHistory($reassignedRequest, $currentAssignee, $previousAssignee);
        $this->createServiceRequestFor($user);
        $this->assignThroughHistory($this->createServiceRequestFor(User::factory()->requester()->create()), $otherUsersAssignee);

        $this->getJson(self::MY_ASSIGNEES_ROUTE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $currentAssignee->id);
    }

    public function test_each_assignee_is_listed_once_ordered_by_name_then_id_with_only_id_and_name(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $beta = User::factory()->technician()->create(['name' => 'Beta']);
        $firstAlpha = User::factory()->technician()->create(['name' => 'Alpha']);
        $secondAlpha = User::factory()->admin()->create(['name' => 'Alpha']);

        foreach ([$beta, $beta, $secondAlpha, $firstAlpha] as $assignee) {
            $this->assignThroughHistory($this->createServiceRequestFor($requester), $assignee);
        }

        $data = $this->getJson(self::MY_ASSIGNEES_ROUTE)->assertOk()->json('data');

        $this->assertSame([$firstAlpha->id, $secondAlpha->id, $beta->id], array_column($data, 'id'));

        foreach ($data as $item) {
            $this->assertSame(['id', 'name'], array_keys($item));
        }
    }

    public function test_list_is_empty_without_assigned_requests(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->createServiceRequestFor($requester);

        $this->getJson(self::MY_ASSIGNEES_ROUTE)
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_query_count_does_not_grow_with_the_number_of_requests(): void
    {
        $requester = $this->signInAs(User::factory()->requester()->create());
        $this->assignThroughHistory($this->createServiceRequestFor($requester), User::factory()->technician()->create());
        $queriesForOneAssignee = $this->countQueriesForAssignees(1);

        foreach (range(1, 4) as $ignored) {
            $this->assignThroughHistory($this->createServiceRequestFor($requester), User::factory()->technician()->create());
        }

        $this->assertSame($queriesForOneAssignee, $this->countQueriesForAssignees(5));
    }

    private function countQueriesForAssignees(int $expectedCount): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(self::MY_ASSIGNEES_ROUTE)->assertOk()->assertJsonCount($expectedCount, 'data');

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }
}
