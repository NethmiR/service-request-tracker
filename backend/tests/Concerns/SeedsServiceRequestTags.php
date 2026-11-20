<?php

// Seeds the predefined service request tags inside each test's database transaction.
// RefreshDatabase seeds only once per process for whichever class runs first, so tags are seeded per test instead.

namespace Tests\Concerns;

use Database\Seeders\TagSeeder;

trait SeedsServiceRequestTags
{
    protected function setUpSeedsServiceRequestTags(): void
    {
        $this->seed(TagSeeder::class);
    }
}
