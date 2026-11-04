<?php

use App\Actions\Maps\BuildHeatmap;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DemoDataPurgeSeeder;
use Database\Seeders\DemoDataSeeder;

test('the demo seeder creates reports that make the heatmap available', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Report::query()->where('status', 'verified')->count())->toBe(1200)
        ->and(Report::query()->where('status', 'pending')->count())->toBe(45)
        ->and(Report::query()->where('status', 'discarded')->count())->toBe(70)
        ->and(Report::query()->where('description', 'not like', '[DEMO]%')->count())->toBe(0);

    $heatmap = app(BuildHeatmap::class)->handle();

    expect($heatmap['meta']['status'])->toBe('available')
        ->and(collect($heatmap['features'])->pluck('properties.level')->unique()->count())->toBeGreaterThan(1);
});

test('verified and pending demo reports are inside the municipal coverage', function () {
    $this->seed(DemoDataSeeder::class);

    $outside = Report::query()->whereIn('status', ['verified', 'pending'])
        ->whereRaw("NOT EXISTS (SELECT 1 FROM zones WHERE code = '25307' AND ST_Covers(boundary, reports.location))")
        ->count();

    expect($outside)->toBe(0);
});

test('the demo seeder can run twice without duplicating data and the purge removes it', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(Report::query()->count())->toBe(1315)
        ->and(User::query()->where('email', 'like', '%@'.DemoDataSeeder::EMAIL_DOMAIN)->count())->toBe(31);

    Report::factory()->create(['description' => 'Reporte real que no se debe tocar']);
    User::factory()->create(['email' => 'real@example.com']);

    $this->seed(DemoDataPurgeSeeder::class);

    expect(Report::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});
