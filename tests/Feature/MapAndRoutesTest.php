<?php

use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\GirardotCoverageSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function walkingRoutePayload(array $overrides = []): array
{
    return array_replace([
        'origin' => ['latitude' => 4.303, 'longitude' => -74.8039],
        'destination' => ['latitude' => 4.31, 'longitude' => -74.80],
        'provider_consent' => true,
    ], $overrides);
}

/** @return array<string, mixed> */
function walkingProviderResponse(): array
{
    return ['code' => 'Ok', 'routes' => [[
        'distance' => 1200, 'duration' => 900,
        'geometry' => ['type' => 'LineString', 'coordinates' => [[-74.8039, 4.303], [-74.802, 4.307], [-74.8, 4.31]]],
    ]]];
}

test('the public can open a real map and pedestrian routing pages without a team or account', function () {
    $this->get(route('public.mapa'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/mapa')->has('categories')->has('mapConfiguration'));
    $this->get(route('public.rutas'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/rutas')->has('routingProvider'));
    $this->getJson(route('api.v1.map.index'))->assertOk()->assertJsonPath('heatmap.meta.status', 'insufficient_data')->assertJsonCount(0, 'heatmap.features');
});

test('heatmap aggregates only verified recent nocturnal reports in the municipal coverage and suppresses small groups', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(3, 0));
    Zone::factory()->create(['code' => '25307']);
    $category = ReportCategory::factory()->create();
    $otherCategory = ReportCategory::factory()->create();
    $moderator = User::factory()->create();
    Report::factory()->count(5)->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => now()->subDay()]);
    Report::factory()->count(4)->verified()->for($otherCategory, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => now()->subDay(), 'location' => 'SRID=4326;POINT(-74.79 4.32)']);
    Report::factory()->for($category, 'category')->create(['occurred_at' => now()->subDay()]);
    Report::factory()->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => now()->subDays(31)]);
    Report::factory()->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => now()->subDay()->setTime(17, 0)]);
    Report::factory()->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => now()->subDay(), 'location' => 'SRID=4326;POINT(-74 5)']);
    $response = $this->getJson(route('api.v1.map.index'))->assertOk()->assertJsonCount(1, 'heatmap.features')
        ->assertJsonPath('heatmap.features.0.properties.count_range', '5–9')->assertJsonPath('heatmap.meta.status', 'available');
    expect(array_keys($response->json('heatmap.features.0.properties')))->toBe(['count_range', 'level']);
    expect($response->json('heatmap.features.0.geometry.type'))->toBe('Polygon');
    expect($response->getContent())->not->toContain('description', 'user_id', 'moderated_by', 'location');
    $this->getJson(route('api.v1.map.index', ['report_category_id' => $otherCategory->id]))->assertOk()->assertJsonCount(0, 'heatmap.features')->assertJsonPath('heatmap.meta.status', 'insufficient_data');
});

test('nighttime filtering handles both sides of midnight and the thirty day boundary', function () {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    Zone::factory()->create(['code' => '25307']);
    $category = ReportCategory::factory()->create();
    $moderator = User::factory()->create();
    Report::factory()->count(5)->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => '2026-11-03T20:00:00-05:00']);
    Report::factory()->count(5)->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => '2026-11-04T01:59:59-05:00']);
    Report::factory()->count(5)->verified()->for($category, 'category')->create(['moderated_by' => $moderator->id, 'occurred_at' => '2026-11-04T02:00:00-05:00']);
    $this->getJson(route('api.v1.map.index'))->assertOk()->assertJsonPath('heatmap.features.0.properties.count_range', '10–14');
});

test('municipal boundary imports real geometry repeatedly without inventing incidents', function () {
    $this->seed(GirardotCoverageSeeder::class);
    $this->artisan('safety:import-girardot')->assertSuccessful();
    $this->assertDatabaseCount('zones', 1);
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseHas('zones', ['code' => '25307', 'name' => 'Girardot']);
    $this->getJson(route('api.v1.map.index'))->assertOk()->assertJsonCount(1, 'coverage.features');
});

test('pedestrian routing sends a foot profile request with consent and returns validated geometry without a fabricated score', function () {
    Http::preventStrayRequests();
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response(walkingProviderResponse())]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertOk()
        ->assertJsonPath('data.0.distance_meters', 1200)->assertJsonPath('data.0.duration_seconds', 900)
        ->assertJsonPath('data.0.safety_score', null)->assertJsonPath('data.0.evidence_status', 'insufficient_data')
        ->assertJsonPath('meta.profile', 'foot');
    $this->assertDatabaseCount('journeys', 0);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/routed-foot/route/v1/foot/-74.803900,4.303000;-74.800000,4.310000') && $request['geometries'] === 'geojson' && $request['radiuses'] === '50;50');
});

test('routing rejects no consent out of coverage or identical points before contacting a provider', function (array $changes, string $field) {
    Http::preventStrayRequests();
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload($changes))->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    'no consent' => [['provider_consent' => false], 'provider_consent'],
    'outside coverage' => [['destination' => ['latitude' => 5, 'longitude' => -74]], 'destination'],
    'same point' => [['destination' => ['latitude' => 4.303, 'longitude' => -74.8039]], 'destination'],
]);

test('routing reports provider outages without returning fictitious alternatives', function () {
    Http::preventStrayRequests();
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::failedConnection()]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertServiceUnavailable()->assertJsonMissingPath('data');
    Http::assertSentCount(1);
});

test('routing handles an actual no route result as an empty result', function () {
    Http::preventStrayRequests();
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response(['code' => 'NoRoute'], 400)]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.status', 'no_route');
    Http::assertSentCount(1);
});

test('routing rejects malformed geometry returned by the provider', function () {
    Http::preventStrayRequests();
    $response = walkingProviderResponse();
    $response['routes'][0]['geometry']['coordinates'] = [['wrong', 4], [-74.8, 4.31]];
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response($response)]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertServiceUnavailable();
    Http::assertSentCount(1);
});

test('routing excludes alternatives which leave the municipal boundary', function () {
    Http::preventStrayRequests();
    $response = walkingProviderResponse();
    $response['routes'][0]['geometry']['coordinates'][1] = [-74.0, 5.0];
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response($response)]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertOk()->assertJsonCount(0, 'data');
    Http::assertSentCount(1);
});

test('routing enforces the provider request limit across users', function () {
    Http::preventStrayRequests();
    Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response(walkingProviderResponse())]);
    Zone::factory()->create(['code' => '25307']);
    $this->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertOk();
    $this->actingAs(User::factory()->create())->postJson(route('api.v1.walking_routes.store'), walkingRoutePayload())->assertTooManyRequests();
    Http::assertSentCount(1);
});
