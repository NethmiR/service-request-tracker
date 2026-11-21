<?php

// Shapes every figure of the overview page in one response, with each part delegated to its own resource.
// Whitelists the fields, so no email, role or internal column ever reaches the response.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $highlights = $this->highlights;

        return [
            'generated_at' => $this->generatedAt->toIso8601ZuluString(),
            'time_zone' => $this->timeZone,
            'status_counts' => StatusCountResource::collection($this->statusCounts),
            'highlights' => [
                'stale_after_days' => $highlights->staleAfterDays,
                'oldest_open' => OverviewRequestResource::collection($highlights->oldestOpen),
                'assigned_to_viewer' => OverviewRequestResource::collection($highlights->assignedToViewer),
                'stale' => OverviewRequestResource::collection($highlights->stale),
                'recently_resolved' => OverviewRequestResource::collection($highlights->recentlyResolved),
            ],
            'monthly_trends' => MonthlyTrendResource::collection($this->monthlyTrends),
            'tag_counts' => TagCountResource::collection($this->tagCounts),
            'staff_workload' => StaffWorkloadResource::collection($this->staffWorkload),
        ];
    }
}
