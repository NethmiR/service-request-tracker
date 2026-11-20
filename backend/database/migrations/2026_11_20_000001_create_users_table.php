<?php

// Creates the users table with the role and the pending password change marker.
// An empty password_changed_at means the user must change their password before doing anything else.

use App\Enums\UserRole;
use App\Support\Database\CheckConstraint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string TABLE = 'users';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 20)->index();
            $table->timestampTz('password_changed_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        CheckConstraint::allowedValues(self::TABLE, 'role', UserRole::values());
        CheckConstraint::lowercase(self::TABLE, 'email');
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
