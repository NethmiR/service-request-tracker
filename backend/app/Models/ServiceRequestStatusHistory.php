<?php

// Represents one append only status change of a service request.
// The initial record written on creation has no from status and no acting user.

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequestStatusHistory extends Model
{
    use AppendOnly;
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'from_status',
        'to_status',
        'changed_by_user_id',
        'remark',
    ];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function isInitialRecord(): bool
    {
        return $this->from_status === null;
    }

    protected function casts(): array
    {
        return [
            'from_status' => ServiceRequestStatus::class,
            'to_status' => ServiceRequestStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
