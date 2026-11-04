<?php

use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;

function eligibleReports(int $count, ReportCategory $category, CarbonImmutable $occurredAt, array $overrides = []): void
{
    $moderator = User::factory()->create();
    Report::factory()->count($count)->verified()->create(array_replace([
        'report_category_id' => $category->id, 'occurred_at' => $occurredAt, 'moderated_by' => $moderator->id,
    ], $overrides));
}

test('hourly incidence reports insufficient data instead of inventing a pattern', function () {
    Zone::factory()->create(['code' => '25307']);
    eligibleReports(10, ReportCategory::factory()->create(), CarbonImmutable::now('America/Bogota')->subDays(3)->setTime(21, 30));

    $this->getJson(route('api.v1.hourly_incidence'))->assertOk()
        ->assertJsonPath('meta.status', 'insufficient_data')->assertJsonPath('meta.confidence', 'insufficient')
        ->assertJsonPath('hours', [])->assertJsonPath('meta.sample_size', 10);
});

test('hourly incidence groups verified reports by local hour and hides small groups', function () {
    Zone::factory()->create(['code' => '25307']);
    $category = ReportCategory::factory()->create();
    $day = CarbonImmutable::now('America/Bogota')->subDays(3);
    eligibleReports(25, $category, $day->setTime(21, 30));
    eligibleReports(6, $category, $day->setTime(22, 10));
    eligibleReports(4, $category, $day->setTime(10, 5));

    $response = $this->getJson(route('api.v1.hourly_incidence'))->assertOk()->assertJsonPath('meta.status', 'available')
        ->assertJsonPath('meta.sample_size', 35)->assertJsonPath('meta.confidence', 'low');

    $hours = collect($response->json('hours'))->keyBy('hour');
    expect($hours[21]['count'])->toBe(25)->and($hours[22]['count'])->toBe(6)->and($hours[10]['count'])->toBeNull();
    expect($response->json('highest_hours'))->toBe([21, 22]);
    expect(collect($response->json('lowest_ranges'))->contains(fn ($range) => $range['from'] <= 10 && $range['to'] >= 10))->toBeTrue();
    expect(collect($response->json('bands'))->firstWhere('id', 'noche')['count'])->toBe(31);
    expect($response->json('meta.notice'))->toContain('No mide cuánta gente circula');
});

test('statistics exclude pending, discarded, outside-coverage and old reports', function () {
    Zone::factory()->create(['code' => '25307']);
    $category = ReportCategory::factory()->create();
    $when = CarbonImmutable::now('America/Bogota')->subDays(2)->setTime(21, 0);
    eligibleReports(31, $category, $when);
    Report::factory()->count(6)->create(['report_category_id' => $category->id, 'occurred_at' => $when]);
    eligibleReports(6, $category, $when, ['location' => 'SRID=4326;POINT(-74.5 4.9)']);
    eligibleReports(6, $category, CarbonImmutable::now()->subDays(200));

    $this->getJson(route('api.v1.community_statistics', ['period' => 30]))->assertOk()
        ->assertJsonPath('totals.verified', 31)->assertJsonPath('meta.status', 'available');
});

test('community statistics group categories under the minimum and compare with the previous period', function () {
    Zone::factory()->create(['code' => '25307']);
    [$a, $b, $c, $d] = ReportCategory::factory()->count(4)->sequence(['name' => 'Alpha'], ['name' => 'Beta'], ['name' => 'Gamma'], ['name' => 'Delta'])->create()->all();
    $recent = CarbonImmutable::now('America/Bogota')->subDays(10)->setTime(20, 0);
    eligibleReports(20, $a, $recent);
    eligibleReports(8, $b, $recent);
    eligibleReports(3, $c, $recent);
    eligibleReports(2, $d, $recent);
    eligibleReports(6, $a, CarbonImmutable::now('America/Bogota')->subDays(45)->setTime(20, 0));

    $response = $this->getJson(route('api.v1.community_statistics', ['period' => 30]))->assertOk()
        ->assertJsonPath('totals.verified', 33)->assertJsonPath('totals.previous_period', 6)->assertJsonPath('totals.change_percent', 450);

    expect(collect($response->json('categories'))->map(fn ($item) => [$item['name'], $item['count']])->all())
        ->toBe([['Alpha', 20], ['Beta', 8], ['Otras categorías (agrupadas)', 5]]);
    expect($response->json('months'))->toHaveCount(6);
    expect(json_encode($response->json()))->not->toContain('Gamma')->not->toContain('Delta');
});

test('community statistics report insufficient data and validate the period', function () {
    Zone::factory()->create(['code' => '25307']);
    eligibleReports(5, ReportCategory::factory()->create(), CarbonImmutable::now()->subDays(2));

    $this->getJson(route('api.v1.community_statistics'))->assertOk()->assertJsonPath('meta.status', 'insufficient_data')->assertJsonPath('categories', [])->assertJsonPath('months', []);
    $this->getJson(route('api.v1.community_statistics', ['period' => 7]))->assertUnprocessable();
});
