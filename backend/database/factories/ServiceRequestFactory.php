<?php

// Builds open service requests for tests and local development, each with a priority, tags and its initial status record.
// The predefined tags and priority tags must be seeded first, because requests reference existing rows.

namespace Database\Factories;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestTag;
use App\Models\PriorityTag;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestStatusHistory;
use App\Models\Tag;
use App\Models\User;
use App\Support\ServiceRequests\ServiceRequestLimits;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LogicException;

class ServiceRequestFactory extends Factory
{
    public const string MISSING_TAGS_MESSAGE = 'Service request tags are missing. Run the TagSeeder before creating service requests.';

    public const string MISSING_PRIORITIES_MESSAGE = 'Service request priority tags are missing. Run the TagSeeder before creating service requests.';

    private const string TAGS_RELATION = 'tags';

    private const string NAME_COLUMN = 'name';

    private const int DESCRIPTION_PARAGRAPHS = 3;

    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'title' => Str::limit(fake()->sentence(), ServiceRequestLimits::TITLE_MAX_LENGTH, ''),
            'description' => Str::limit(fake()->paragraphs(self::DESCRIPTION_PARAGRAPHS, true), ServiceRequestLimits::DESCRIPTION_MAX_LENGTH, ''),
            'requested_by_user_id' => User::factory(),
            'priority_tag_id' => fn (): int => $this->randomPriorityTag()->getKey(),
            'status' => ServiceRequestStatus::Open,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ServiceRequest $serviceRequest): void {
            if (! $serviceRequest->tags()->exists()) {
                $serviceRequest->tags()->attach($this->randomTag());
            }

            ServiceRequestStatusHistory::factory()
                ->for($serviceRequest)
                ->initial()
                ->createdAt($serviceRequest->created_at)
                ->create();
        });
    }

    public function requestedBy(User $user): static
    {
        return $this->state(fn (): array => ['requested_by_user_id' => $user->getKey()]);
    }

    public function withTags(ServiceRequestTag ...$tags): static
    {
        $tagValues = array_values(array_unique(array_map(fn (ServiceRequestTag $tag): string => $tag->value, $tags)));
        $tagRows = Tag::query()->whereIn(self::NAME_COLUMN, $tagValues)->get();

        if ($tagValues === [] || $tagRows->count() !== count($tagValues)) {
            throw new LogicException(self::MISSING_TAGS_MESSAGE);
        }

        return $this->hasAttached($tagRows, [], self::TAGS_RELATION);
    }

    public function withPriority(ServiceRequestPriority $priority): static
    {
        $priorityTag = PriorityTag::query()->where(self::NAME_COLUMN, $priority->value)->first();

        if ($priorityTag === null) {
            throw new LogicException(self::MISSING_PRIORITIES_MESSAGE);
        }

        return $this->state(fn (): array => ['priority_tag_id' => $priorityTag->getKey()]);
    }

    public function createdAt(CarbonInterface $time): static
    {
        return $this->state(fn (): array => [
            'created_at' => $time,
            'updated_at' => $time,
        ]);
    }

    private function randomTag(): Tag
    {
        $tag = Tag::query()->inRandomOrder()->first();

        if ($tag === null) {
            throw new LogicException(self::MISSING_TAGS_MESSAGE);
        }

        return $tag;
    }

    private function randomPriorityTag(): PriorityTag
    {
        $priorityTag = PriorityTag::query()->inRandomOrder()->first();

        if ($priorityTag === null) {
            throw new LogicException(self::MISSING_PRIORITIES_MESSAGE);
        }

        return $priorityTag;
    }
}
