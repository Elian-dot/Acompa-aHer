<?php

namespace Database\Seeders;

use App\Models\ReportCategory;
use Illuminate\Database\Seeder;

class ReportCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'other' => 'Otro incidente',
            'lighting' => 'Poca iluminación',
            'harassment' => 'Acoso verbal',
            'pursuit' => 'Seguimiento o persecución',
            'theft' => 'Robo o intento de robo',
            'low_foot_traffic' => 'Calle sin tránsito',
        ] as $code => $name) {
            ReportCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
