<?php

// Represents one authentication event in the append only audit trail.
// The AppendOnly trait makes any attempt to update or delete a row throw.

namespace App\Models;

use App\Enums\AuthAuditEvent;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAuditLog extends Model
{
    use AppendOnly;

    public const UPDATED_AT = null;

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

    protected function casts(): array
    {
        return [
            'event' => AuthAuditEvent::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
