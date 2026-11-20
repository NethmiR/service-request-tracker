<?php

// Creates the append only authentication audit table.
// Rows are only inserted, so the table has a creation time and no update time.

use App\Enums\AuthAuditEvent;
use App\Support\Database\CheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'auth_audit_logs';

    private const string USERS_TABLE = 'users';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('event', 50)->index();
            $table->foreignId('actor_user_id')->nullable()->index()->constrained(self::USERS_TABLE)->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->index()->constrained(self::USERS_TABLE)->restrictOnDelete();
            $table->string('attempted_email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        CheckConstraint::allowedValues(self::TABLE, 'event', AuthAuditEvent::values());
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
