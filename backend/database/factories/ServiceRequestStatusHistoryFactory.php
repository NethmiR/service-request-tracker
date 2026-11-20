<?php

// Builds status history records directly, so tests can prepare histories that the workflow writes in Phase 6.
// The application itself writes status history only through the history recorder.

namespace Database\Factories;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestStatusHistoryFactory extends Factory
{
    protected $model = ServiceRequestStatusHistory::class;

    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'from_status' => ServiceRequestStatus::Open,
            'to_status' => ServiceRequestStatus::Assigned,
            'changed_by_user_id' => User::factory()->technician(),
            'remark' => null,
        ];
    }

    public function initial(): static
    {
        return $this->state(fn (): array => [
            'from_status' => null,
            'to_status' => ServiceRequestStatus::Open,
            'changed_by_user_id' => null,
            'remark' => null,
        ]);
    }

    public function transition(ServiceRequestStatus $from, ServiceRequestStatus $to, User $changedBy): static
    {
        return $this->state(fn (): array => [
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $changedBy->getKey(),
        ]);
    }

    public function withRemark(string $remark): static
    {
        return $this->state(fn (): array => ['remark' => $remark]);
    }

    public function createdAt(CarbonInterface $time): static
    {
        return $this->state(fn (): array => ['created_at' => $time]);
    }
}
