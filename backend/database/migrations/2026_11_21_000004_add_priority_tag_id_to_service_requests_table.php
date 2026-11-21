<?php

// Links every service request to exactly one priority tag through a required, indexed foreign key.
// Requests that already exist receive the Medium priority, so the column can be made required afterwards.

use App\Enums\ServiceRequestPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'service_requests';

    private const string PRIORITY_TAGS_TABLE = 'priority_tags';

    private const string COLUMN = 'priority_tag_id';

    private const string NAME_COLUMN = 'name';

    private const ServiceRequestPriority EXISTING_REQUEST_PRIORITY = ServiceRequestPriority::Medium;

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->foreignId(self::COLUMN)->nullable()->index()->constrained(self::PRIORITY_TAGS_TABLE)->restrictOnDelete();
        });

        if (DB::table(self::TABLE)->exists()) {
            DB::table(self::PRIORITY_TAGS_TABLE)->upsert(
                array_map(
                    fn (ServiceRequestPriority $priority): array => [self::NAME_COLUMN => $priority->value],
                    ServiceRequestPriority::cases(),
                ),
                [self::NAME_COLUMN],
            );

            $priorityTagId = DB::table(self::PRIORITY_TAGS_TABLE)
                ->where(self::NAME_COLUMN, self::EXISTING_REQUEST_PRIORITY->value)
                ->value('id');

            DB::table(self::TABLE)->update([self::COLUMN => $priorityTagId]);
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->foreignId(self::COLUMN)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropForeign([self::COLUMN]);
            $table->dropIndex([self::COLUMN]);
            $table->dropColumn(self::COLUMN);
        });
    }
};
