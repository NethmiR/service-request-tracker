<?php

// Creates the append only assignee history of service requests.
// The latest record of each request identifies its current assignee.

use App\Support\ServiceRequests\ServiceRequestLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_request_assignee_histories';

    private const string USERS_TABLE = 'users';

    private const string ASSIGNED_FROM_FOREIGN_KEY = 'assignee_histories_assigned_from_foreign';

    private const string REQUEST_CREATED_INDEX = 'assignee_histories_request_created_index';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_from_user_id')->nullable()->constrained(self::USERS_TABLE, indexName: self::ASSIGNED_FROM_FOREIGN_KEY)->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->index()->constrained(self::USERS_TABLE)->restrictOnDelete();
            $table->string('remark', ServiceRequestLimits::REMARK_MAX_LENGTH);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['service_request_id', 'created_at'], self::REQUEST_CREATED_INDEX);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
