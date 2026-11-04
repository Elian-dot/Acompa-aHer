<?php

namespace App\Data;

use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeographicCoverage
{
    /** @param array<string, mixed> $data */
    public function import(array $data): Zone
    {
        $features = $data['features'] ?? [];
        if (($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($features) || count($features) !== 1
            || ($features[0]['properties']['code'] ?? null) !== config('safety.coverage_code')
            || ! in_array($features[0]['geometry']['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            throw ValidationException::withMessages(['boundary' => 'El archivo debe contener únicamente el límite de Girardot, código 25307.']);
        }

        $geometry = json_encode($features[0]['geometry'], JSON_THROW_ON_ERROR);
        $check = DB::selectOne('SELECT ST_IsValid(g) AND NOT ST_IsEmpty(g) AND ST_CoveredBy(g, ST_MakeEnvelope(-75, 4, -74, 5, 4326)) AND ST_Area(g::geography) BETWEEN 10000000 AND 500000000 AS valid FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g) boundary', [$geometry]);
        if (! $check->valid) {
            throw ValidationException::withMessages(['boundary' => 'El límite geográfico no es válido para Girardot.']);
        }

        $boundary = DB::selectOne('SELECT ST_AsEWKT(ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))) AS geometry', [$geometry]);

        return Zone::query()->updateOrCreate(['code' => config('safety.coverage_code')], ['name' => 'Girardot', 'boundary' => $boundary->geometry]);
    }

    /**
     * Configuración que necesita cualquier página con mapa.
     *
     * @return array<string, mixed>
     */
    public function mapConfiguration(): array
    {
        return [
            'center' => [4.303, -74.8039], 'zoom' => 14,
            'tileUrl' => config('safety.tile_url'),
            'coverageAvailable' => $this->isAvailable(),
            'coverage' => $this->featureCollection(),
            'coverageSource' => config('safety.coverage_source'),
            'coverageSourceUrl' => config('safety.coverage_source_url'),
            'coverageRetrievedAt' => config('safety.coverage_retrieved_at'),
        ];
    }

    public function isAvailable(): bool
    {
        return Zone::query()->where('code', config('safety.coverage_code'))->exists();
    }

    public function contains(float $longitude, float $latitude): bool
    {
        return Zone::query()->where('code', config('safety.coverage_code'))
            ->whereRaw('ST_Covers(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$longitude, $latitude])->exists();
    }

    public function validatePoint(float $longitude, float $latitude, string $field): void
    {
        abort_unless($this->isAvailable(), 503, 'La cobertura geográfica todavía no está disponible.');

        if (! $this->contains($longitude, $latitude)) {
            throw ValidationException::withMessages([$field => 'Selecciona una ubicación dentro de Girardot.']);
        }
    }

    /** @return array{type: string, features: list<array<string, mixed>>} */
    public function featureCollection(): array
    {
        $zones = Zone::query()->where('code', config('safety.coverage_code'))
            ->select(['id', 'code', 'name'])->selectRaw('ST_AsGeoJSON(boundary) AS geometry_json')->get();

        $features = [];
        foreach ($zones as $zone) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($zone->getAttribute('geometry_json'), true, flags: JSON_THROW_ON_ERROR),
                'properties' => ['code' => $zone->code, 'name' => $zone->name],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
