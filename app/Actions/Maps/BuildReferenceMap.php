<?php

namespace App\Actions\Maps;

use App\Models\GeographicFeature;
use Illuminate\Support\Facades\Cache;

class BuildReferenceMap
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return Cache::remember('girardot-reference-map:v1', 600, function (): array {
            $rows = GeographicFeature::query()
                ->select(['id', 'osm_type', 'osm_id', 'kind', 'name', 'classification', 'lit', 'foot', 'access', 'source_retrieved_at'])
                ->selectRaw('ST_AsGeoJSON(ST_SimplifyPreserveTopology(geometry, 0.00001), 6) AS geometry_json')
                ->whereRaw('EXISTS (SELECT 1 FROM zones WHERE code = ? AND ST_Covers(boundary, geographic_features.geometry))', [config('safety.coverage_code')])
                ->orderBy('id')->get();

            return [
                'type' => 'FeatureCollection',
                'features' => $rows->map(fn (GeographicFeature $feature): array => [
                    'type' => 'Feature',
                    'geometry' => json_decode($feature->getAttribute('geometry_json'), true, flags: JSON_THROW_ON_ERROR),
                    'properties' => [
                        'kind' => $feature->kind, 'name' => $feature->name,
                        'classification' => $feature->classification, 'lit' => $feature->lit,
                        'foot' => $feature->foot, 'access' => $feature->access,
                        'source_url' => 'https://www.openstreetmap.org/'.$feature->osm_type.'/'.$feature->osm_id,
                    ],
                ])->all(),
                'meta' => [
                    'source' => 'OpenStreetMap', 'license' => 'ODbL-1.0',
                    'retrieved_at' => $rows->max('source_retrieved_at')?->toIso8601String(),
                    'roads' => $rows->where('kind', 'road')->count(),
                    'places' => $rows->where('kind', '!=', 'road')->count(),
                    'notice' => 'Calles y lugares cartografiados por la comunidad. Su presencia no acredita iluminación operativa, apertura ni seguridad.',
                ],
            ];
        });
    }
}
