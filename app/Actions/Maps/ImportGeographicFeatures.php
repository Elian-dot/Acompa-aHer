<?php

namespace App\Actions\Maps;

use App\Data\GeographicCoverage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportGeographicFeatures
{
    public function __construct(private GeographicCoverage $coverage) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): int
    {
        Validator::make($data, [
            'type' => ['required', 'in:FeatureCollection'],
            'source.license' => ['required', 'in:ODbL-1.0'],
            'source.retrieved_at' => ['required', 'date', 'before_or_equal:now'],
            'features' => ['required', 'array', 'min:1', 'max:30000'],
            'features.*.properties.osm_type' => ['required', 'in:node,way,relation'],
            'features.*.properties.osm_id' => ['required', 'integer', 'min:1'],
            'features.*.properties.kind' => ['required', 'in:road,shop,amenity,street_lamp'],
            'features.*.properties.name' => ['nullable', 'string', 'max:250'],
            'features.*.properties.classification' => ['required', 'string', 'max:80'],
            'features.*.properties.lit' => ['nullable', 'string', 'max:30'],
            'features.*.properties.foot' => ['nullable', 'string', 'max:30'],
            'features.*.properties.access' => ['nullable', 'string', 'max:30'],
            'features.*.geometry.type' => ['required', 'in:Point,LineString,Polygon'],
            'features.*.geometry.coordinates' => ['required', 'array'],
        ])->validate();

        if (! $this->coverage->isAvailable()) {
            throw ValidationException::withMessages(['features' => 'Importa primero el límite de Girardot.']);
        }

        $rows = [];
        $keys = [];
        foreach ($data['features'] as $feature) {
            $properties = $feature['properties'];
            $key = $properties['osm_type'].'/'.$properties['osm_id'];
            if (isset($keys[$key]) || ($properties['kind'] === 'road' && $feature['geometry']['type'] !== 'LineString')) {
                throw ValidationException::withMessages(['features' => 'El archivo contiene identificadores duplicados o vías sin geometría lineal.']);
            }
            $keys[$key] = true;
            $rows[] = [
                'osm_type' => $properties['osm_type'], 'osm_id' => $properties['osm_id'],
                'kind' => $properties['kind'], 'name' => $properties['name'] ?? null,
                'classification' => $properties['classification'], 'lit' => $properties['lit'] ?? null,
                'foot' => $properties['foot'] ?? null, 'access' => $properties['access'] ?? null,
                'geometry' => $feature['geometry'],
            ];
        }

        $count = DB::transaction(function () use ($rows, $data): int {
            $count = 0;
            foreach (array_chunk($rows, 500) as $chunk) {
                $json = json_encode($chunk, JSON_THROW_ON_ERROR);
                $valid = DB::selectOne(<<<'SQL'
                    SELECT bool_and(ST_IsValid(g) AND NOT ST_IsEmpty(g) AND ST_CoveredBy(g, ST_MakeEnvelope(-75, 4, -74, 5, 4326))) AS valid
                    FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(value->'geometry'), 4326) AS g FROM jsonb_array_elements(?::jsonb)) input
                    SQL, [$json]);
                if (! $valid->valid) {
                    throw ValidationException::withMessages(['features' => 'Hay geometrías vacías, inválidas o fuera de la región de Girardot.']);
                }

                $count += DB::affectingStatement(<<<'SQL'
                    WITH input AS (
                        SELECT r.*, ST_SetSRID(ST_GeomFromGeoJSON(r.geometry), 4326) AS geom
                        FROM jsonb_to_recordset(?::jsonb) AS r(osm_type text, osm_id bigint, kind text, name text, classification text, lit text, foot text, access text, geometry jsonb)
                    ), clipped AS (
                        SELECT input.*, CASE WHEN kind = 'road' THEN ST_CollectionExtract(ST_Intersection(geom, boundary), 2) ELSE ST_PointOnSurface(geom) END AS clipped_geometry
                        FROM input JOIN zones ON zones.code = ? AND ST_Intersects(geom, boundary)
                        WHERE kind = 'road' OR ST_Covers(boundary, ST_PointOnSurface(geom))
                    )
                    INSERT INTO geographic_features (osm_type, osm_id, kind, name, classification, lit, foot, access, geometry, source_retrieved_at, created_at, updated_at)
                    SELECT osm_type, osm_id, kind, name, classification, lit, foot, access, clipped_geometry, ?::timestamptz, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    FROM clipped WHERE NOT ST_IsEmpty(clipped_geometry)
                    ON CONFLICT (osm_type, osm_id) DO UPDATE SET kind = EXCLUDED.kind, name = EXCLUDED.name, classification = EXCLUDED.classification,
                        lit = EXCLUDED.lit, foot = EXCLUDED.foot, access = EXCLUDED.access, geometry = EXCLUDED.geometry,
                        source_retrieved_at = EXCLUDED.source_retrieved_at, updated_at = CURRENT_TIMESTAMP
                    WHERE EXCLUDED.source_retrieved_at >= geographic_features.source_retrieved_at
                    SQL, [$json, config('safety.coverage_code'), $data['source']['retrieved_at']]);
            }

            return $count;
        });

        Cache::forget('girardot-reference-map:v1');

        return $count;
    }
}
