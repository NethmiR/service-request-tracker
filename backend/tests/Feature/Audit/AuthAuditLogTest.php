<?php

// Verifies that the authentication audit trail is append only.
// Updating or deleting a record through Eloquent must throw.

namespace Tests\Feature\Audit;

use App\Enums\AuthAuditEvent;
use App\Models\AuthAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_record_can_be_created(): void
    {
        $log = AuthAuditLog::query()->create(['event' => AuthAuditEvent::SignedOut]);

        $this->assertSame(AuthAuditEvent::SignedOut, $log->refresh()->event);
    }

    public function test_updating_a_record_throws(): void
    {
        $log = AuthAuditLog::query()->create(['event' => AuthAuditEvent::SignedOut]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AuthAuditLog::APPEND_ONLY_MESSAGE);

        $log->update(['attempted_email' => 'changed@example.com']);
    }

    public function test_deleting_a_record_throws(): void
    {
        $log = AuthAuditLog::query()->create(['event' => AuthAuditEvent::SignedOut]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AuthAuditLog::APPEND_ONLY_MESSAGE);

        $log->delete();
    }
}
