<?php

use App\Actions\Maps\ImportGeographicFeatures;
use App\Models\GeographicFeature;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function geographicSnapshot(): array
{
    return [
        'type' => 'FeatureCollection',
        'source' => ['license' => 'ODbL-1.0', 'retrieved_at' => '2026-11-04T10:00:00Z'],
        'features' => [
            ['type' => 'Feature', 'properties' => ['osm_type' => 'way', 'osm_id' => 123, 'kind' => 'road', 'name' => 'Calle de prueba', 'classification' => 'residential'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[-74.83, 4.30], [-74.81, 4.30]]]],
            ['type' => 'Feature', 'properties' => ['osm_type' => 'node', 'osm_id' => 456, 'kind' => 'amenity', 'name' => 'Farmacia de prueba', 'classification' => 'pharmacy'], 'geometry' => ['type' => 'Point', 'coordinates' => [-74.80, 4.30]]],
            ['type' => 'Feature', 'properties' => ['osm_type' => 'node', 'osm_id' => 789, 'kind' => 'shop', 'classification' => 'convenience'], 'geometry' => ['type' => 'Point', 'coordinates' => [-74.9, 4.4]]],
        ],
    ];
}

test('geographic imports clip actual streets to the coverage ignore external places and expose source attribution publicly', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    Zone::factory()->create(['code' => '25307']);
    expect(app(ImportGeographicFeatures::class)->handle(geographicSnapshot()))->toBe(2);
    $this->assertDatabaseCount('geographic_features', 2);
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseCount('users', 0);
    expect(DB::selectOne('SELECT ST_XMin(Box3D(geometry)) AS west FROM geographic_features WHERE osm_id = 123')->west)->toBe(-74.82);
    $this->getJson(route('api.v1.reference_map'))->assertOk()->assertJsonCount(2, 'features')
        ->assertJsonPath('meta.roads', 1)->assertJsonPath('meta.places', 1)
        ->assertJsonPath('meta.license', 'ODbL-1.0')
        ->assertJsonPath('features.0.properties.source_url', 'https://www.openstreetmap.org/way/123')
        ->assertJsonPath('features.0.properties.lit', null);
});

test('repeated geographic imports update by OSM identity invalidate the map cache and keep a newer snapshot', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    Zone::factory()->create(['code' => '25307']);
    $import = app(ImportGeographicFeatures::class);
    $data = geographicSnapshot();
    $import->handle($data);
    $this->getJson(route('api.v1.reference_map'))->assertJsonPath('features.0.properties.name', 'Calle de prueba');
    $data['features'][0]['properties']['name'] = "Calle '); DROP TABLE users; --";
    $data['source']['retrieved_at'] = '2026-11-04T11:00:00Z';
    $import->handle($data);
    $import->handle(geographicSnapshot());
    $this->assertDatabaseCount('geographic_features', 2);
    $this->getJson(route('api.v1.reference_map'))->assertJsonPath('features.0.properties.name', "Calle '); DROP TABLE users; --");
    $this->assertDatabaseCount('users', 0);
});

test('the public reference map has no invented features when there is no imported cartography', function () {
    $this->getJson(route('api.v1.reference_map'))->assertOk()->assertJsonCount(0, 'features')->assertJsonPath('meta.retrieved_at', null);
});

test('reference maps omit features which no longer belong to the current coverage', function () {
    Zone::factory()->create(['code' => '25307']);
    GeographicFeature::factory()->create(['geometry' => 'SRID=4326;LINESTRING(-74.9 4.4,-74.91 4.41)']);
    $this->getJson(route('api.v1.reference_map'))->assertOk()->assertJsonCount(0, 'features');
});

test('geographic imports require coverage before saving data', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    expect(fn () => app(ImportGeographicFeatures::class)->handle(geographicSnapshot()))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('geographic_features', 0);
});

test('invalid source duplicate identities or invalid road geometry prevent the complete import', function (string $invalid) {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    Zone::factory()->create(['code' => '25307']);
    $data = geographicSnapshot();
    match ($invalid) {
        'license' => $data['source']['license'] = 'unknown',
        'future' => $data['source']['retrieved_at'] = '2030-01-01T00:00:00Z',
        'duplicate' => $data['features'][] = $data['features'][0],
        'road' => $data['features'][0]['geometry'] = ['type' => 'Point', 'coordinates' => [-74.80, 4.3]],
    };
    expect(fn () => app(ImportGeographicFeatures::class)->handle($data))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('geographic_features', 0);
})->with(['license', 'future', 'duplicate', 'road']);

test('an invalid geometry in a later batch rolls back earlier imported rows and reports a command failure', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    Zone::factory()->create(['code' => '25307']);
    $data = geographicSnapshot();
    $data['features'] = [];
    for ($index = 1; $index <= 501; $index++) {
        $feature = geographicSnapshot()['features'][1];
        $feature['properties']['osm_id'] = $index;
        $data['features'][] = $feature;
    }
    $data['features'][500]['geometry']['coordinates'] = [-73, 5];
    $path = tempnam(sys_get_temp_dir(), 'girardot-features-');
    file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
    try {
        $this->artisan('safety:import-geography', ['--file' => $path])->assertFailed();
    } finally {
        unlink($path);
    }
    $this->assertDatabaseCount('geographic_features', 0);
});
