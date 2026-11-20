<?php

// Represents an IT service request raised by a user, with its tags and both histories.
// The current assignee is derived from the latest assignee history record and is never stored on the request.

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    use HasFactory;

    private const string TAG_PIVOT_TABLE = 'service_request_tag';

    protected $fillable = [
        'title',
        'description',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, self::TAG_PIVOT_TABLE)->orderBy((new Tag)->qualifyColumn('id'));
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestStatusHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function assigneeHistories(): HasMany
    {
        return $this->hasMany(ServiceRequestAssigneeHistory::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function currentAssigneeHistory(): HasOne
    {
        return $this->hasOne(ServiceRequestAssigneeHistory::class)->latestOfMany();
    }

    public function currentAssignee(): ?User
    {
        return $this->currentAssigneeHistory?->assignedTo;
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requested_by_user_id === $user->getKey();
    }

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
