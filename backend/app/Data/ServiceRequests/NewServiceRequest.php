<?php

// Carries the validated title, description, tags and priority of a service request being created.
// Keeps the service request service independent of the HTTP request.

namespace App\Data\ServiceRequests;

use App\Enums\ServiceRequestPriority;
use InvalidArgumentException;

final readonly class NewServiceRequest
{
    private const string EMPTY_TAGS_MESSAGE = 'A service request needs at least one tag.';

    public function __construct(
        public string $title,
        public string $description,
        public array $tags,
        public ServiceRequestPriority $priority,
    ) {
        if ($tags === []) {
            throw new InvalidArgumentException(self::EMPTY_TAGS_MESSAGE);
        }
    }
}
