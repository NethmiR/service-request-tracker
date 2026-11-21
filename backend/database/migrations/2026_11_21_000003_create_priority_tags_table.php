<?php

// Creates the table of predefined service request priorities.
// The rows come from the priority enum and never change, so the table has no timestamps.

use App\Enums\ServiceRequestPriority;
use App\Support\Database\CheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'priority_tags';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique();
        });

        CheckConstraint::allowedValues(self::TABLE, 'name', ServiceRequestPriority::values());
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
