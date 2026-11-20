<?php

// Carries the client address and user agent of the current request.
// Services receive this object instead of the whole request so they stay independent of HTTP.

namespace App\Data\Auth;

use Illuminate\Http\Request;

final readonly class RequestContext
{
    public const int USER_AGENT_MAX_LENGTH = 512;

    public function __construct(
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $userAgent = $request->userAgent();

        return new self(
            $request->ip(),
            $userAgent === null ? null : mb_substr($userAgent, 0, self::USER_AGENT_MAX_LENGTH),
        );
    }
}
