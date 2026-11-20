<?php

// Entry point for seeding data required in every environment.
// Seeds the predefined tags first, then the initial admin.

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(TagSeeder::class);
        $this->call(InitialAdminSeeder::class);
    }
}
