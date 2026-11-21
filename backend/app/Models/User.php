<?php

// Represents a person who signs in to the system with one of the three roles.
// Tracks whether the user still has to change their password and links the service requests they raised.

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'password_changed_at' => null,
    ];

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'requested_by_user_id');
    }

    public function requiresPasswordChange(): bool
    {
        return $this->password_changed_at === null;
    }

    public function markPasswordChanged(): void
    {
        $this->password_changed_at = now();
    }

    public function markPasswordChangeRequired(): void
    {
        $this->password_changed_at = null;
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'password' => 'hashed',
            'password_changed_at' => 'immutable_datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }
}
