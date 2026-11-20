<?php

// Signs users in and out with rate limiting, enumeration protection and audit records.
// Controllers call this service and never touch the guard or the limiter directly.

namespace App\Services\Auth;

use App\Data\Audit\AuthAuditEntry;
use App\Data\Auth\RequestContext;
use App\Data\Auth\SignInCredentials;
use App\Enums\AuthAuditEvent;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use App\Services\Audit\AuthAuditLogger;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;

final class AuthenticationService
{
    public function __construct(
        #[Auth('web')] private readonly StatefulGuard $guard,
        private readonly SignInRateLimiter $rateLimiter,
        private readonly DummyPasswordHash $dummyPasswordHash,
        private readonly AuthAuditLogger $auditLogger,
    ) {}

    public function signIn(SignInCredentials $credentials, RequestContext $context): User
    {
        $this->ensureNotLimited($credentials, $context);

        $user = User::query()->where('email', $credentials->email)->first();
        $hash = $user === null ? $this->dummyPasswordHash->hash() : $user->password;
        $passwordMatches = Hash::check($credentials->password, $hash);

        if ($user === null || ! $passwordMatches) {
            $this->rateLimiter->recordFailure($credentials->email, $context->ipAddress);
            $this->auditLogger->record(new AuthAuditEntry(
                AuthAuditEvent::SignInFailed,
                null,
                $user,
                $credentials->email,
                $context,
            ));

            throw new InvalidCredentialsException;
        }

        $this->rateLimiter->clearFailures($credentials->email, $context->ipAddress);

        if (Hash::needsRehash($user->password)) {
            $user->password = $credentials->password;
            $user->save();
        }

        $this->guard->login($user);

        $this->auditLogger->record(new AuthAuditEntry(
            AuthAuditEvent::SignInSucceeded,
            $user,
            null,
            null,
            $context,
        ));

        return $user;
    }

    public function signOut(User $user, Session $session, RequestContext $context): void
    {
        $this->auditLogger->record(new AuthAuditEntry(
            AuthAuditEvent::SignedOut,
            $user,
            null,
            null,
            $context,
        ));

        $this->guard->logout();
        $session->invalidate();
        $session->regenerateToken();
    }

    private function ensureNotLimited(SignInCredentials $credentials, RequestContext $context): void
    {
        try {
            $this->rateLimiter->ensureNotLimited($credentials->email, $context->ipAddress);
        } catch (ThrottleRequestsException $exception) {
            $this->auditLogger->record(new AuthAuditEntry(
                AuthAuditEvent::SignInThrottled,
                null,
                null,
                $credentials->email,
                $context,
            ));

            throw $exception;
        }
    }
}
