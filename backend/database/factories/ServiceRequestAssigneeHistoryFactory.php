<?php

// Builds assignee history records directly, so tests can prepare histories that the workflow writes in Phase 6.
// The application itself writes assignee history only through the history recorder.

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestAssigneeHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestAssigneeHistoryFactory extends Factory
{
    protected $model = ServiceRequestAssigneeHistory::class;

    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'assigned_from_user_id' => null,
            'assigned_to_user_id' => User::factory()->technician(),
            'remark' => fake()->sentence(),
        ];
    }

    public function assignedTo(User $assignee): static
    {
        return $this->state(fn (): array => ['assigned_to_user_id' => $assignee->getKey()]);
    }

    public function assignedFrom(?User $previousAssignee): static
    {
        return $this->state(fn (): array => ['assigned_from_user_id' => $previousAssignee?->getKey()]);
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
