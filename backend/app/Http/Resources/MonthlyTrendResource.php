<?php

// Shapes the created and resolved figures of one reporting month for the overview graphs.
// The average resolution time is returned in hours rounded to one decimal, or null for a month with no resolution.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MonthlyTrendResource extends JsonResource
{
    private const int SECONDS_PER_HOUR = 3600;

    private const int HOURS_PRECISION = 1;

    public function toArray(Request $request): array
    {
        $averageSeconds = $this->averageResolutionSeconds;

        return [
            'month' => $this->month,
            'created' => $this->created,
            'resolved' => $this->resolved,
            'average_resolution_hours' => $averageSeconds === null
                ? null
                : round($averageSeconds / self::SECONDS_PER_HOUR, self::HOURS_PRECISION),
        ];
    }
}
