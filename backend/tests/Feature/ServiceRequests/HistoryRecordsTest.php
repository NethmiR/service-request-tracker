<?php

// Verifies that the status history and the assignee history of a service request are append only.
// Updating or deleting a record through Eloquent must throw.

namespace Tests\Feature\ServiceRequests;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\ServiceRequestStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\SeedsServiceRequestTags;
use Tests\TestCase;

class HistoryRecordsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsServiceRequestTags;

    private const string CHANGED_REMARK = 'Changed remark';

    public function test_updating_a_status_history_record_throws(): void
    {
        $record = $this->statusHistoryRecord();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ServiceRequestStatusHistory::APPEND_ONLY_MESSAGE);

        $record->update(['remark' => self::CHANGED_REMARK]);
    }

    public function test_deleting_a_status_history_record_throws(): void
    {
        $record = $this->statusHistoryRecord();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ServiceRequestStatusHistory::APPEND_ONLY_MESSAGE);

        $record->delete();
    }

    public function test_updating_an_assignee_history_record_throws(): void
    {
        $record = ServiceRequestAssigneeHistory::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ServiceRequestAssigneeHistory::APPEND_ONLY_MESSAGE);

        $record->update(['remark' => self::CHANGED_REMARK]);
    }

    public function test_deleting_an_assignee_history_record_throws(): void
    {
        $record = ServiceRequestAssigneeHistory::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ServiceRequestAssigneeHistory::APPEND_ONLY_MESSAGE);

        $record->delete();
    }

    public function test_failed_changes_leave_the_records_untouched(): void
    {
        $record = $this->statusHistoryRecord();
        $assigneeRecord = ServiceRequestAssigneeHistory::factory()->create();
        $originalRemark = $assigneeRecord->remark;

        foreach ([fn () => $record->update(['remark' => self::CHANGED_REMARK]), fn () => $assigneeRecord->delete()] as $change) {
            try {
                $change();
            } catch (LogicException) {
            }
        }

        $this->assertNull($record->refresh()->remark);
        $this->assertSame($originalRemark, $assigneeRecord->refresh()->remark);
    }

    private function statusHistoryRecord(): ServiceRequestStatusHistory
    {
        return ServiceRequest::factory()->create()->statusHistories()->sole();
    }
}
