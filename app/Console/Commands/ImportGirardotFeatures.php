<?php

namespace App\Console\Commands;

use App\Actions\Maps\ImportGeographicFeatures;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use JsonException;

#[Signature('safety:import-geography {--file= : Archivo GeoJSON de vías y lugares reales de Girardot}')]
#[Description('Importa masivamente referencias de OpenStreetMap sin crear incidentes ni cuentas')]
class ImportGirardotFeatures extends Command
{
    public function handle(ImportGeographicFeatures $import): int
    {
        $path = $this->option('file') ?: database_path('seeders/data/girardot-features.geojson');
        if (! is_file($path) || filesize($path) > 20000000) {
            $this->error('Indica un archivo GeoJSON válido de hasta 20 MB.');

            return self::FAILURE;
        }
        try {
            $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                $this->error('El archivo debe contener un objeto GeoJSON válido.');

                return self::FAILURE;
            }
            $count = $import->handle($data);
        } catch (JsonException|ValidationException|QueryException) {
            $this->error('No se pudo importar la cartografía. Revisa el formato, la procedencia y las geometrías. No se guardaron cambios.');

            return self::FAILURE;
        }
        $this->info("Referencias geográficas importadas o actualizadas: {$count}. No se crearon reportes ni cuentas.");

        return self::SUCCESS;
    }
}
