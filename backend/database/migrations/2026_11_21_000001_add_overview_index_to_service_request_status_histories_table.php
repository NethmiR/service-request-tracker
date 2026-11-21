<?php

// Adds the index that serves the latest move to resolved of every resolved request on the overview.
// The name is set explicitly because the generated name would exceed the PostgreSQL identifier limit.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_request_status_histories';

    private const string STATUS_REQUEST_CREATED_INDEX = 'status_histories_status_request_created_index';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['to_status', 'service_request_id', 'created_at'], self::STATUS_REQUEST_CREATED_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::STATUS_REQUEST_CREATED_INDEX);
        });
    }
};
