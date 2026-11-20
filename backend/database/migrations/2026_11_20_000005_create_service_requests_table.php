<?php

// Creates the service requests table with the requester and the current status.
// The current assignee is not stored here and comes from the latest assignee history record.

use App\Enums\ServiceRequestStatus;
use App\Support\Database\CheckConstraint;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_requests';

    private const string USERS_TABLE = 'users';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('title', ServiceRequestLimits::TITLE_MAX_LENGTH);
            $table->text('description');
            $table->foreignId('requested_by_user_id')->constrained(self::USERS_TABLE)->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->timestampsTz();

            $table->index(['requested_by_user_id', 'created_at']);
            $table->index('created_at');
        });

        CheckConstraint::allowedValues(self::TABLE, 'status', ServiceRequestStatus::values());
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
