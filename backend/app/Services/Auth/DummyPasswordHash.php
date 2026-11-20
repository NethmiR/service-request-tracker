<?php

// Provides a hash that is checked when the email is unknown.
// This keeps the response time the same for existing and unknown accounts.

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DummyPasswordHash
{
    private const string CACHE_KEY = 'auth.dummy_password_hash';

    private const int RANDOM_SOURCE_LENGTH = 40;

    public function hash(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && ! Hash::needsRehash($cached)) {
            return $cached;
        }

        $hash = Hash::make(Str::random(self::RANDOM_SOURCE_LENGTH));
        Cache::forever(self::CACHE_KEY, $hash);

        return $hash;
    }
}
