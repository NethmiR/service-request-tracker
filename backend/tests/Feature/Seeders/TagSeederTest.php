<?php

// Verifies the tag seeder.
// It creates exactly the predefined tags in enum order and never duplicates them.
// Tags seeded by earlier test classes on the shared in memory connection are removed first.

namespace Tests\Feature\Seeders;

use App\Enums\ServiceRequestTag;
use App\Models\Tag;
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

    private function storedTags(): array
    {
        return Tag::query()->orderBy('id')->get()->map(fn (Tag $tag): ServiceRequestTag => $tag->name)->all();
    }
}
