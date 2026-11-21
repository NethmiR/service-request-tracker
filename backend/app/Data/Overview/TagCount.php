<?php

// Carries the number of service requests in the reporting window that carry one tag.
// A request with several tags counts under each of them.

namespace App\Data\Overview;

use App\Enums\ServiceRequestTag;

final readonly class TagCount
{
    public function __construct(
        public ServiceRequestTag $tag,
        public int $count,
    ) {}
}
