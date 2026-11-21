<?php

// Seeds the predefined service request tags and priority tags in the order their enums define them.
// Running it again changes nothing, because rows are upserted on the name.

namespace Database\Seeders;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestTag;
use App\Models\PriorityTag;
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

        PriorityTag::query()->upsert(
            array_map(
                fn (ServiceRequestPriority $priority): array => [self::NAME_COLUMN => $priority->value],
                ServiceRequestPriority::cases(),
            ),
            [self::NAME_COLUMN],
        );
    }
}
