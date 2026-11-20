<?php

// Entry point for seeding data required in every environment.
// Seeds the initial admin, and Phase 5 adds the predefined tags.

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(InitialAdminSeeder::class);
    }
}
