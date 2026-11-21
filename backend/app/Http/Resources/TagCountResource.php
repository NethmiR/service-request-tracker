<?php

// Shapes the number of service requests that carry one tag for the overview tag distribution graph.
// The tag is returned as a value item, so no tag name is ever written as a JSON key.

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TagCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tag' => $this->tag->value,
            'count' => $this->count,
        ];
    }
}
