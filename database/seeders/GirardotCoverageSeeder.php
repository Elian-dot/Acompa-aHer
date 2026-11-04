<?php

namespace Database\Seeders;

use App\Data\GeographicCoverage;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class GirardotCoverageSeeder extends Seeder
{
    public function run(GeographicCoverage $coverage): void
    {
        if (! Zone::query()->where('code', config('safety.coverage_code'))->exists()) {
            $coverage->import(json_decode(File::get(database_path('seeders/data/girardot.geojson')), true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
