<?php

// Verifies the non reversible identifiers that stand in for raw session ids in the API.
// Identifiers must be stable, keyed with the application key and safe to compare.

namespace Tests\Unit\Services\Sessions;

use App\Services\Sessions\SessionIdentifier;
use PHPUnit\Framework\TestCase;

class SessionIdentifierTest extends TestCase
{
    private const string APPLICATION_KEY = 'first-application-key-for-identifier-tests';

    private const string OTHER_APPLICATION_KEY = 'second-application-key-for-identifier-tests';

    private const string SESSION_ID = 'FirstSessionIdForIdentifierTests12345678';

    private const string OTHER_SESSION_ID = 'OtherSessionIdForIdentifierTests87654321';

    public function test_identifier_is_lowercase_hexadecimal_matching_the_route_pattern(): void
    {
        $identifier = $this->identifier()->forSessionId(self::SESSION_ID);

        $this->assertSame(64, strlen($identifier));
        $this->assertMatchesRegularExpression('/^'.SessionIdentifier::PATTERN.'$/', $identifier);
    }

    public function test_same_session_id_and_key_always_give_the_same_identifier(): void
    {
        $this->assertSame(
            $this->identifier()->forSessionId(self::SESSION_ID),
            $this->identifier()->forSessionId(self::SESSION_ID),
        );
    }

    public function test_different_session_ids_give_different_identifiers(): void
    {
        $this->assertNotSame(
            $this->identifier()->forSessionId(self::SESSION_ID),
            $this->identifier()->forSessionId(self::OTHER_SESSION_ID),
        );
    }

    public function test_different_keys_give_different_identifiers_for_the_same_session_id(): void
    {
        $this->assertNotSame(
            $this->identifier()->forSessionId(self::SESSION_ID),
            $this->identifier(self::OTHER_APPLICATION_KEY)->forSessionId(self::SESSION_ID),
        );
    }

    public function test_identifier_never_contains_the_session_id(): void
    {
        $identifier = $this->identifier()->forSessionId(self::SESSION_ID);

        $this->assertStringNotContainsString(self::SESSION_ID, $identifier);
        $this->assertStringNotContainsStringIgnoringCase(self::SESSION_ID, $identifier);
    }

    public function test_matches_accepts_only_the_identifier_of_the_given_session(): void
    {
        $identifier = $this->identifier()->forSessionId(self::SESSION_ID);

        $this->assertTrue($this->identifier()->matches($identifier, self::SESSION_ID));
        $this->assertFalse($this->identifier()->matches($identifier, self::OTHER_SESSION_ID));
        $this->assertFalse($this->identifier()->matches(strtoupper($identifier), self::SESSION_ID));
        $this->assertFalse($this->identifier(self::OTHER_APPLICATION_KEY)->matches($identifier, self::SESSION_ID));
    }

    private function identifier(string $applicationKey = self::APPLICATION_KEY): SessionIdentifier
    {
        return new SessionIdentifier($applicationKey);
    }
}
