<?php

// Represents one authentication event in the append only audit trail.
// Rows can only be created, and any attempt to update or delete one throws.

namespace App\Models;

use App\Enums\AuthAuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuthAuditLog extends Model
{
    public const UPDATED_AT = null;

    public const string APPEND_ONLY_MESSAGE = 'Authentication audit records are append only and cannot be updated or deleted.';

    protected $fillable = [
        'event',
        'actor_user_id',
        'target_user_id',
        'attempted_email',
        'ip_address',
        'user_agent',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException(self::APPEND_ONLY_MESSAGE));
        static::deleting(fn (): never => throw new LogicException(self::APPEND_ONLY_MESSAGE));
    }

    protected function casts(): array
    {
        return [
            'event' => AuthAuditEvent::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
