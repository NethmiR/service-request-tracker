<?php

// Creates the database session table used by the session driver.
// Each row is linked to its user so sessions can be listed and cleared.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'sessions';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
