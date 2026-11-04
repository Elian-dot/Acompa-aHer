<?php

namespace Database\Seeders;

use App\Actions\Maps\ImportGeographicFeatures;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class GeographicFeatureSeeder extends Seeder
{
    public function run(ImportGeographicFeatures $import): void
    {
        $this->call(GirardotCoverageSeeder::class);
        $count = $import->handle(json_decode(File::get(database_path('seeders/data/girardot-features.geojson')), true, flags: JSON_THROW_ON_ERROR));
        $this->command?->info("Referencias geográficas importadas o actualizadas: {$count}.");
    }
}
