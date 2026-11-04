<?php

namespace App\Console\Commands;

use App\Data\GeographicCoverage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use JsonException;

#[Signature('safety:import-girardot {--file= : Archivo GeoJSON con el límite de Girardot}')]
#[Description('Importa el límite geográfico real de Girardot sin crear reportes de ejemplo')]
class ImportGirardotBoundary extends Command
{
    public function handle(GeographicCoverage $coverage): int
    {
        $path = $this->option('file') ?: database_path('seeders/data/girardot.geojson');
        if (! is_file($path) || filesize($path) > 5000000) {
            $this->error('Indica un archivo GeoJSON válido de hasta 5 MB.');

            return self::FAILURE;
        }
        try {
            $coverage->import(json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException|ValidationException $exception) {
            $this->error('No se pudo importar el límite: revisa el formato y la cobertura del archivo.');

            return self::FAILURE;
        }
        $this->info('Cobertura de Girardot importada. No se crearon incidentes ni datos personales.');

        return self::SUCCESS;
    }
}
