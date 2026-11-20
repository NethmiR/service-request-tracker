<?php

// Turns raw session ids into the non reversible identifiers exposed by the API.
// Identifiers are keyed with the application key, so they cannot be computed or reversed by clients.

namespace App\Services\Sessions;

use Illuminate\Container\Attributes\Config;

final class SessionIdentifier
{
    public const string PATTERN = '[a-f0-9]{64}';

    private const string ALGORITHM = 'sha256';

    private const string PURPOSE_PREFIX = 'session-identifier:';

    public function __construct(
        #[Config('app.key')]
        private readonly string $applicationKey,
    ) {}

    public function forSessionId(string $sessionId): string
    {
        return hash_hmac(self::ALGORITHM, self::PURPOSE_PREFIX.$sessionId, $this->applicationKey);
    }

    public function matches(string $identifier, string $sessionId): bool
    {
        return hash_equals($this->forSessionId($sessionId), $identifier);
    }
}
