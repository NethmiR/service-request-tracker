<?php

// Verifies the list of technicians and admins a request can be reassigned to.
// Covers who is included, the name then id order and the exposed fields.

namespace Tests\Feature\ServiceRequests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class ServiceRequestAssigneesTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string ASSIGNEES_ROUTE = '/api/service-requests/assignees';

    public function test_list_holds_every_technician_and_admin_but_no_requester(): void
    {
        $viewer = $this->signInAs(User::factory()->technician()->create(['name' => 'Viewer']));
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $pending = User::factory()->technician()->withPendingPasswordChange()->create(['name' => 'Pending']);
        User::factory()->requester()->create(['name' => 'Requester']);

        $ids = $this->getJson(self::ASSIGNEES_ROUTE)->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$viewer->id, $admin->id, $pending->id], $ids);
    }

    public function test_list_is_ordered_by_name_then_id_with_only_id_and_name(): void
    {
        $this->signInAs(User::factory()->technician()->create(['name' => 'Zed']));
        $firstAlpha = User::factory()->technician()->create(['name' => 'Alpha']);
        $secondAlpha = User::factory()->admin()->create(['name' => 'Alpha']);
        $beta = User::factory()->technician()->create(['name' => 'Beta']);

        $data = $this->getJson(self::ASSIGNEES_ROUTE)->assertOk()->json('data');

        $this->assertSame([$firstAlpha->id, $secondAlpha->id, $beta->id], array_slice(array_column($data, 'id'), 0, 3));

        foreach ($data as $item) {
            $this->assertSame(['id', 'name'], array_keys($item));
        }
    }
}
