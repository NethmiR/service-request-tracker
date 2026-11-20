<?php

// Verifies the transition rules held by the service request status enum.
// Every pair of current and target status is checked against the transition table of the Phase 6 plan.

namespace Tests\Unit\Enums;

use App\Enums\ServiceRequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServiceRequestStatusTest extends TestCase
{
    private const array ALLOWED_TARGETS = [
        'OPEN' => [],
        'ASSIGNED' => ['IN_PROGRESS', 'RESOLVED'],
        'IN_PROGRESS' => ['ASSIGNED', 'RESOLVED'],
        'RESOLVED' => ['ASSIGNED', 'IN_PROGRESS'],
    ];

    public static function statusPairs(): array
    {
        $pairs = [];

        foreach (ServiceRequestStatus::cases() as $current) {
            foreach (ServiceRequestStatus::cases() as $target) {
                $pairs[$current->value.' to '.$target->value] = [
                    $current,
                    $target,
                    in_array($target->value, self::ALLOWED_TARGETS[$current->value], true),
                ];
            }
        }

        return $pairs;
    }

    #[DataProvider('statusPairs')]
    public function test_transition_matches_the_table(ServiceRequestStatus $current, ServiceRequestStatus $target, bool $expected): void
    {
        $this->assertSame($expected, $current->canTransitionTo($target));
    }

    public function test_only_open_is_assignable(): void
    {
        foreach (ServiceRequestStatus::cases() as $status) {
            $this->assertSame($status === ServiceRequestStatus::Open, $status->isAssignable());
        }
    }

    public function test_no_status_lists_itself_or_open_as_a_target(): void
    {
        foreach (ServiceRequestStatus::cases() as $status) {
            $this->assertNotContains($status, $status->allowedTargets());
            $this->assertNotContains(ServiceRequestStatus::Open, $status->allowedTargets());
        }
    }
}
