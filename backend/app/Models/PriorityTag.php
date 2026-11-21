<?php

// Represents one of the predefined priorities a service request can carry.
// The rows are seeded from the priority enum and never change.

namespace App\Models;

use App\Enums\ServiceRequestPriority;
use Illuminate\Database\Eloquent\Model;

class PriorityTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'name' => ServiceRequestPriority::class,
        ];
    }
}
