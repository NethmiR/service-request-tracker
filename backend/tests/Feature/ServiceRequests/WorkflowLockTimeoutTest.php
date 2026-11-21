<?php

// Verifies that a workflow change rejected by a PostgreSQL lock timeout returns a conflict and writes nothing.
// SQLite cannot produce a lock timeout, so the error is raised when the change writes its history record.

namespace Tests\Feature\ServiceRequests;

use App\Enums\ErrorCode;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PDOException;
use Tests\Concerns\InteractsWithFrontendSession;
use Tests\Concerns\InteractsWithServiceRequests;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class WorkflowLockTimeoutTest extends TestCase
{
    use InteractsWithFrontendSession;
    use InteractsWithServiceRequests;
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string LOCK_NOT_AVAILABLE_STATE = '55P03';

    private const string UNIQUE_VIOLATION_STATE = '23505';

    private const string HISTORY_INSERT_PATTERN = '/^insert into "service_request_(status|assignee)_histories"/i';

    private const string REMARK = 'Taking this request.';

    public function test_self_assignment_that_times_out_is_rejected_as_a_concurrent_update(): void
    {
        $technician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);

        $this->assertRejectedWithoutWrites($serviceRequest, $technician, fn (): TestResponse => $this->postJson(
            "/api/service-requests/{$serviceRequest->id}/assignment",
            ['remark' => self::REMARK],
        ));

        $this->assertNull($serviceRequest->refresh()->currentAssignee());
    }

    public function test_reassignment_that_times_out_is_rejected_as_a_concurrent_update(): void
    {
        $assignee = User::factory()->technician()->create();
        $newAssignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->assertRejectedWithoutWrites($serviceRequest, $assignee, fn (): TestResponse => $this->postJson(
            "/api/service-requests/{$serviceRequest->id}/reassignment",
            ['assignee_id' => $newAssignee->id, 'remark' => self::REMARK],
        ));

        $this->assertTrue($serviceRequest->refresh()->isAssignedTo($assignee));
    }

    public function test_status_change_that_times_out_is_rejected_as_a_concurrent_update(): void
    {
        $assignee = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Assigned, $assignee);

        $this->assertRejectedWithoutWrites($serviceRequest, $assignee, fn (): TestResponse => $this->patchJson(
            "/api/service-requests/{$serviceRequest->id}/status",
            ['status' => ServiceRequestStatus::InProgress->value],
        ));

        $this->assertTrue($serviceRequest->refresh()->isAssignedTo($assignee));
    }

    public function test_other_database_errors_are_not_treated_as_a_lock_timeout(): void
    {
        $technician = User::factory()->technician()->create();
        $serviceRequest = $this->createServiceRequestInStatus(ServiceRequestStatus::Open);

        $this->signInAs($technician);
        $this->failHistoryInsertsWithState(self::UNIQUE_VIOLATION_STATE);

        $this->postJson("/api/service-requests/{$serviceRequest->id}/assignment", ['remark' => self::REMARK])
            ->assertInternalServerError()
            ->assertJsonPath('code', ErrorCode::ServerError->value);

        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->refresh()->status);
    }

    private function assertRejectedWithoutWrites(ServiceRequest $serviceRequest, User $actor, callable $change): void
    {
        $status = $serviceRequest->refresh()->status;
        $statusRecordCount = ServiceRequestStatusHistory::query()->count();
        $assigneeRecordCount = ServiceRequestAssigneeHistory::query()->count();

        $this->signInAs($actor);
        $this->failHistoryInsertsWithState(self::LOCK_NOT_AVAILABLE_STATE);

        $change()
            ->assertConflict()
            ->assertJsonPath('code', ErrorCode::ConcurrentUpdate->value);

        $this->assertSame($statusRecordCount, ServiceRequestStatusHistory::query()->count());
        $this->assertSame($assigneeRecordCount, ServiceRequestAssigneeHistory::query()->count());
        $this->assertSame($status, $serviceRequest->refresh()->status);
    }

    private function failHistoryInsertsWithState(string $state): void
    {
        DB::connection()->beforeExecuting(function (string $query, array $bindings, Connection $connection) use ($state): void {
            if (preg_match(self::HISTORY_INSERT_PATTERN, $query) !== 1) {
                return;
            }

            $previous = new PDOException;
            $previous->errorInfo = [$state, 7, 'simulated database error'];

            throw new QueryException($connection->getName(), $query, $bindings, $previous);
        });
    }
}
