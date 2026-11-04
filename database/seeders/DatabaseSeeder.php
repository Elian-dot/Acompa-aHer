<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ReportCategorySeeder::class);
        $this->call(GirardotCoverageSeeder::class);
        $this->call(GeographicFeatureSeeder::class);
    }
}
