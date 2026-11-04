<?php

namespace Database\Seeders;

use App\Models\ReportCategory;
use Illuminate\Database\Seeder;

class ReportCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ReportCategory::query()->firstOrCreate(['code' => 'other'], [
            'name' => 'Otro incidente',
            'is_active' => true,
        ]);
    }
}
