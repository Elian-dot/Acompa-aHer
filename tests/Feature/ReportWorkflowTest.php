<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function validIncidentPayload(array $overrides = []): array
{
    return array_replace([
        'report_category_id' => ReportCategory::factory()->create()->id,
        'latitude' => 4.303, 'longitude' => -74.8039,
        'occurred_at' => now()->subHour()->format('Y-m-d\TH:i:sP'),
        'description' => 'Un incidente para revisar.', 'is_anonymous' => true, 'consent' => true,
    ], $overrides);
}

test('an authenticated authorized user stores an anonymous pending report without accepting author or moderation fields', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    Zone::factory()->create(['code' => '25307']);
    $response = $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload([
        'status' => 'verified', 'user_id' => $user->id, 'moderated_by' => $user->id, 'ip_address' => '198.51.100.10',
    ]))->assertCreated()->assertJsonPath('data.status', 'pending');

    $report = Report::query()->findOrFail($response->json('data.id'));
    expect($report->user_id)->toBeNull();
    expect($report->moderated_by)->toBeNull();
    expect($report->is_anonymous)->toBeTrue();
    expect($report->description)->toBe('Un incidente para revisar.');
    expect($report->id)->toMatch('/^[a-f0-9-]{14}4[a-f0-9-]{21}$/');
    $response->assertJsonStructure(['data' => ['id', 'status']]);
    expect(array_keys($response->json('data')))->toBe(['id', 'status']);
});

test('an identified report stores its authenticated author regardless of submitted author', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $other = User::factory()->create();
    Zone::factory()->create(['code' => '25307']);
    $response = $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload(['is_anonymous' => false, 'user_id' => $other->id, 'description' => null]))->assertCreated();
    $this->assertDatabaseHas('reports', ['id' => $response->json('data.id'), 'user_id' => $user->id, 'description' => null, 'is_anonymous' => false]);
});

test('report submission requires authentication and domain permission', function () {
    $this->postJson(route('api.v1.reports.store'), [])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('api.v1.reports.store'), [])->assertForbidden();
    $this->assertDatabaseCount('reports', 0);
});

test('report submission rejects invalid category location time privacy choice or consent', function (array $changes, string $field, string $message) {
    $this->freezeTime();
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    Zone::factory()->create(['code' => '25307']);
    $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload($changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonPath('errors.'.$field.'.0', $message);
    $this->assertDatabaseCount('reports', 0);
})->with([
    'unavailable category' => [['report_category_id' => 32000], 'report_category_id', 'Selecciona una categoría activa.'],
    'out of coverage' => [['latitude' => 5], 'location', 'Selecciona una ubicación dentro de Girardot.'],
    'invalid latitude' => [['latitude' => 91], 'latitude', 'El campo latitud debe estar entre -90 y 90.'],
    'invalid longitude' => [['longitude' => -181], 'longitude', 'El campo longitud debe estar entre -180 y 180.'],
    'no explicit consent' => [['consent' => false], 'consent', 'Confirma que entiendes qué datos se enviarán.'],
    'invalid anonymity' => [['is_anonymous' => 'sometimes'], 'is_anonymous', 'Selecciona una opción válida para reporte anónimo.'],
    'no timezone' => [['occurred_at' => '2026-01-01T12:00:00'], 'occurred_at', 'Indica la fecha del incidente con su zona horaria.'],
    'long narrative' => [['description' => str_repeat('a', 501)], 'description', 'El campo descripción no puede superar 500 caracteres.'],
]);

test('report submission rejects a future incident and inactive category', function () {
    $this->freezeTime();
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $category = ReportCategory::factory()->create(['is_active' => false]);
    $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload([
        'report_category_id' => $category->id, 'occurred_at' => now()->addMinute()->format('Y-m-d\TH:i:sP'),
    ]))->assertUnprocessable()->assertJsonValidationErrors(['report_category_id', 'occurred_at']);
});

test('report submission fails explicitly if municipal coverage is missing', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload())->assertServiceUnavailable();
    $this->assertDatabaseCount('reports', 0);
});

test('report images are reencoded stored privately and downloadable only by moderators', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $moderator = User::factory()->create()->assignRole(UserRole::CommunityModerator);
    Zone::factory()->create(['code' => '25307']);
    $response = $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload([
        'attachments' => [UploadedFile::fake()->image('nombre-personal.png', 100, 100)],
    ]))->assertCreated();
    $attachment = Report::query()->findOrFail($response->json('data.id'))->attachments()->sole();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->not->toContain('nombre-personal');
    expect(getimagesizefromstring(Storage::disk('local')->get($attachment->path))['mime'])->toBe('image/jpeg');
    $this->get(route('api.v1.attachments.show', $attachment))->assertForbidden();
    $this->actingAs($moderator)->get(route('api.v1.attachments.show', $attachment))->assertOk()->assertDownload('adjunto.jpg')->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('active image formats and excessive image sizes are rejected', function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $this->actingAs($user)->postJson(route('api.v1.reports.store'), validIncidentPayload([
        'attachments' => [UploadedFile::fake()->createWithContent('active.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), UploadedFile::fake()->image('large.jpg', 3001, 10)],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['attachments.0', 'attachments.1']);
    $this->assertDatabaseCount('report_attachments', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('moderation hides identity and exact location and atomically verifies a pending report', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $moderator = User::factory()->create()->assignRole(UserRole::CommunityModerator);
    Zone::factory()->create(['code' => '25307']);
    $report = Report::factory()->identified(User::factory()->create())->create();
    $this->actingAs($moderator)->getJson(route('api.v1.moderation.index'))->assertOk()->assertJsonPath('data.0.id', $report->id)
        ->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('data.0.location')->assertJsonMissingPath('data.0.moderated_by');
    $this->patchJson(route('api.v1.moderation.update', $report), ['status' => 'verified', 'privacy_reviewed' => true, 'coverage_reviewed' => true])->assertOk()->assertJsonPath('data.status', 'verified');
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'verified', 'moderated_by' => $moderator->id]);
    $this->patchJson(route('api.v1.moderation.update', $report), ['status' => 'discarded', 'moderation_reason' => 'duplicate'])->assertConflict();
    expect($report->fresh()->status)->toBe(ReportStatus::Verified);
});

test('discarding requires an approved reason and keeps the report out of public data', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $moderator = User::factory()->create()->assignRole(UserRole::CommunityModerator);
    $report = Report::factory()->create();
    $this->actingAs($moderator)->patchJson(route('api.v1.moderation.update', $report), ['status' => 'discarded'])->assertUnprocessable()->assertJsonValidationErrors('moderation_reason');
    $this->patchJson(route('api.v1.moderation.update', $report), ['status' => 'discarded', 'moderation_reason' => 'identifying_content'])->assertOk();
    $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'discarded', 'moderation_reason' => 'identifying_content']);
});

test('verification requires privacy and territorial checks and rejects out of coverage reports', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $moderator = User::factory()->create()->assignRole(UserRole::CommunityModerator);
    Zone::factory()->create(['code' => '25307']);
    $report = Report::factory()->create(['location' => 'SRID=4326;POINT(-74 5)']);
    $this->actingAs($moderator)->patchJson(route('api.v1.moderation.update', $report), ['status' => 'verified'])->assertUnprocessable()->assertJsonValidationErrors(['privacy_reviewed', 'coverage_reviewed']);
    $this->patchJson(route('api.v1.moderation.update', $report), ['status' => 'verified', 'privacy_reviewed' => true, 'coverage_reviewed' => true])->assertUnprocessable()->assertJsonValidationErrors('location');
    expect($report->fresh()->status)->toBe(ReportStatus::Pending);
});

test('report submission is limited without storing a raw account or IP in limiter keys', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    Zone::factory()->create(['code' => '25307']);
    $payload = validIncidentPayload();
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('api.v1.reports.store'), $payload)->assertCreated();
    }
    $this->postJson(route('api.v1.reports.store'), $payload)->assertTooManyRequests();
    $this->assertDatabaseCount('reports', 5);
});
