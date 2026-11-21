<?php

// Creates the link table between service requests and their tags.
// The composite primary key prevents the same tag from being attached twice.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_request_tag';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('tag_id')->index()->constrained()->restrictOnDelete();

            $table->primary(['service_request_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
