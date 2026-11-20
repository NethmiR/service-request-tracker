<?php

// Represents one of the predefined tags a service request can carry.
// The rows are seeded from the tag enum and never change.

namespace App\Models;

use App\Enums\ServiceRequestTag;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'name' => ServiceRequestTag::class,
        ];
    }
}
