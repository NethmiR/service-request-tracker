<?php

// Verifies the tag seeder and the database seeder that runs it.
// They create exactly the predefined tags and priority tags in enum order and never duplicate them.
// Rows seeded by earlier test classes on the shared in memory connection are removed first.

namespace Tests\Feature\Seeders;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestTag;
use App\Models\PriorityTag;
use App\Models\Tag;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tag::query()->delete();
        PriorityTag::query()->delete();
    }

    public function test_it_creates_the_predefined_tags_in_enum_order(): void
    {
        $this->seed(TagSeeder::class);

        $this->assertSame(ServiceRequestTag::cases(), $this->storedTags());
    }

    public function test_running_it_twice_creates_no_duplicates(): void
    {
        $this->seed(TagSeeder::class);
        $this->seed(TagSeeder::class);

        $this->assertSame(ServiceRequestTag::cases(), $this->storedTags());
    }

    public function test_it_creates_the_predefined_priority_tags_in_enum_order(): void
    {
        $this->seed(TagSeeder::class);

        $this->assertSame(ServiceRequestPriority::cases(), $this->storedPriorities());
    }

    public function test_running_it_twice_creates_no_duplicate_priority_tags(): void
    {
        $this->seed(TagSeeder::class);
        $this->seed(TagSeeder::class);

        $this->assertSame(ServiceRequestPriority::cases(), $this->storedPriorities());
    }

    public function test_database_seeder_creates_the_tags_and_priority_tags_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(ServiceRequestTag::cases(), $this->storedTags());
        $this->assertSame(ServiceRequestPriority::cases(), $this->storedPriorities());
    }

    private function storedTags(): array
    {
        return Tag::query()->orderBy('id')->get()->map(fn (Tag $tag): ServiceRequestTag => $tag->name)->all();
    }

    private function storedPriorities(): array
    {
        return PriorityTag::query()
            ->orderBy('id')
            ->get()
            ->map(fn (PriorityTag $priorityTag): ServiceRequestPriority => $priorityTag->name)
            ->all();
    }
}
