<?php

// Makes an Eloquent model append only, so its rows can be created but never updated or deleted.
// Laravel boots this trait automatically on every model that uses it.

namespace App\Models\Concerns;

use LogicException;

trait AppendOnly
{
    public const string APPEND_ONLY_MESSAGE = 'This record is append only and cannot be updated or deleted.';

    protected static function bootAppendOnly(): void
    {
        static::updating(fn (): never => throw new LogicException(self::APPEND_ONLY_MESSAGE));
        static::deleting(fn (): never => throw new LogicException(self::APPEND_ONLY_MESSAGE));
    }
}
