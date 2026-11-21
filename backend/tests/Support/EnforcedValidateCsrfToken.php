<?php

// Test only CSRF middleware that keeps validating tokens while unit tests run.
// It exists only so tests can prove CSRF protection, because Laravel skips the check in tests by default.

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

final class EnforcedValidateCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
