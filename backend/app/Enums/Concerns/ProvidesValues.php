<?php

// Gives string backed enums a list of their backing values.
// Used by validation rules and database check constraints.

namespace App\Enums\Concerns;

trait ProvidesValues
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
