<?php

// Creates the table of predefined service request tags.
// The rows come from the tag enum and never change, so the table has no timestamps.

use App\Enums\ServiceRequestTag;
use App\Support\Database\CheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'tags';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique();
        });

        CheckConstraint::allowedValues(self::TABLE, 'name', ServiceRequestTag::values());
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
