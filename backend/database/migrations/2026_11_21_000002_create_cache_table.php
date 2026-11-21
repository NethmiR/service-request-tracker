<?php

// Creates the database cache tables that hold rate limit counters and other shared cached values.
// Storing them in the database keeps the limits consistent across several application servers.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string CACHE_TABLE = 'cache';

    private const string CACHE_LOCKS_TABLE = 'cache_locks';

    public function up(): void
    {
        Schema::create(self::CACHE_TABLE, function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create(self::CACHE_LOCKS_TABLE, function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::CACHE_LOCKS_TABLE);
        Schema::dropIfExists(self::CACHE_TABLE);
    }
};
