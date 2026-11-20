<?php

// Creates the append only status history of service requests.
// The from status and the acting user are empty together only for the initial record written on creation.

use App\Enums\ServiceRequestStatus;
use App\Support\Database\CheckConstraint;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_request_status_histories';

    private const string USERS_TABLE = 'users';

    private const string REQUEST_CREATED_INDEX = 'status_histories_request_created_index';

    private const string INITIAL_RECORD_CHECK = 'status_histories_initial_record_check';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by_user_id')->nullable()->index()->constrained(self::USERS_TABLE)->restrictOnDelete();
            $table->string('remark', ServiceRequestLimits::REMARK_MAX_LENGTH)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['service_request_id', 'created_at'], self::REQUEST_CREATED_INDEX);
        });

        CheckConstraint::allowedValues(self::TABLE, 'from_status', ServiceRequestStatus::values());
        CheckConstraint::allowedValues(self::TABLE, 'to_status', ServiceRequestStatus::values());
        CheckConstraint::nullTogether(self::TABLE, self::INITIAL_RECORD_CHECK, 'from_status', 'changed_by_user_id');
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
