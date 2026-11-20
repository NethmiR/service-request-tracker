<?php

// Counts failed sign in attempts per email and IP combination and per IP.
// Only failures count, and a successful sign in clears the email and IP counter.

namespace App\Services\Auth;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class SignInRateLimiter
{
    private const string EMAIL_AND_IP_PREFIX = 'sign-in:email-ip:';

    private const string IP_PREFIX = 'sign-in:ip:';

    private const string RETRY_AFTER_HEADER = 'Retry-After';

    private const string MISSING_IP_ADDRESS = 'unknown';

    public function ensureNotLimited(string $email, ?string $ipAddress): void
    {
        $emailAndIpKey = $this->emailAndIpKey($email, $ipAddress);
        $ipKey = $this->ipKey($ipAddress);

        $emailAndIpLimited = RateLimiter::tooManyAttempts(
            $emailAndIpKey,
            (int) config('rate_limits.sign_in.max_failures_per_email_and_ip'),
        );
        $ipLimited = RateLimiter::tooManyAttempts(
            $ipKey,
            (int) config('rate_limits.sign_in.max_failures_per_ip'),
        );

        if (! $emailAndIpLimited && ! $ipLimited) {
            return;
        }

        $retryAfter = max(
            $emailAndIpLimited ? RateLimiter::availableIn($emailAndIpKey) : 0,
            $ipLimited ? RateLimiter::availableIn($ipKey) : 0,
        );

        throw new ThrottleRequestsException(headers: [self::RETRY_AFTER_HEADER => $retryAfter]);
    }

    public function recordFailure(string $email, ?string $ipAddress): void
    {
        $decaySeconds = (int) config('rate_limits.sign_in.decay_seconds');

        RateLimiter::hit($this->emailAndIpKey($email, $ipAddress), $decaySeconds);
        RateLimiter::hit($this->ipKey($ipAddress), $decaySeconds);
    }

    public function clearFailures(string $email, ?string $ipAddress): void
    {
        RateLimiter::clear($this->emailAndIpKey($email, $ipAddress));
    }

    private function emailAndIpKey(string $email, ?string $ipAddress): string
    {
        return self::EMAIL_AND_IP_PREFIX.sha1(Str::lower($email).'|'.($ipAddress ?? self::MISSING_IP_ADDRESS));
    }

    private function ipKey(?string $ipAddress): string
    {
        return self::IP_PREFIX.($ipAddress ?? self::MISSING_IP_ADDRESS);
    }
}
