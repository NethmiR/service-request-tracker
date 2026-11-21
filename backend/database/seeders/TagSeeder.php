<?php

// Seeds the predefined service request tags in the order the tag enum defines them.
// Running it again changes nothing, because rows are upserted on the tag name.

namespace Database\Seeders;

use App\Enums\ServiceRequestTag;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    private const string NAME_COLUMN = 'name';

    public function run(): void
    {
        Tag::query()->upsert(
            array_map(
                fn (ServiceRequestTag $tag): array => [self::NAME_COLUMN => $tag->value],
                ServiceRequestTag::cases(),
            ),
            [self::NAME_COLUMN],
        );
    }
}
