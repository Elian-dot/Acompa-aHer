<?php

use App\Enums\JourneyStatus;
use App\Models\AlertDelivery;
use App\Models\EmergencyContact;
use App\Models\Journey;
use App\Models\JourneyAlert;
use App\Models\JourneyLocation;
use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('invalid report data is rejected by PostgreSQL', function (array $attributes) {
    expect(fn () => Report::factory()->create($attributes))->toThrow(QueryException::class);
})->with([
    'UUID v7' => [['id' => '019a4c00-0000-7000-8000-000000000001']],
    'empty point' => [['location' => 'SRID=4326;POINT EMPTY']],
    'latitude outside range' => [['location' => 'SRID=4326;POINT(-74 95)']],
    'wrong SRID' => [['location' => 'SRID=3857;POINT(-74 4)']],
    'unmoderated verified report' => [['status' => 'verified']],
    'missing category' => [['report_category_id' => 32767]],
]);

test('anonymous reports cannot store an author even when bypassing Eloquent guards', function () {
    $user = User::factory()->create();

    expect(fn () => Report::factory()->create(['user_id' => $user->id]))->toThrow(QueryException::class);
});

test('anonymous authorship cannot be restored by a direct SQL update', function () {
    $report = Report::factory()->create();
    $user = User::factory()->create();

    expect(fn () => DB::table('reports')->where('id', $report->id)->update(['is_anonymous' => false, 'user_id' => $user->id]))->toThrow(QueryException::class);
});

test('a category referenced by historical reports cannot be deleted', function () {
    $report = Report::factory()->create();

    expect(fn () => $report->category->delete())->toThrow(QueryException::class);
});

test('invalid attachment sizes and geometrically invalid zones are rejected', function (Closure $write) {
    expect($write)->toThrow(QueryException::class);
})->with([
    'empty attachment' => [fn () => ReportAttachment::factory()->create(['size_bytes' => 0])],
    'self intersecting zone' => [fn () => Zone::factory()->create(['boundary' => 'SRID=4326;MULTIPOLYGON(((-74.82 4.28,-74.78 4.33,-74.82 4.33,-74.78 4.28,-74.82 4.28)))'])],
]);

test('a user cannot start two active journeys concurrently', function () {
    $journey = Journey::factory()->create();

    expect(fn () => Journey::factory()->for($journey->user)->create())->toThrow(QueryException::class);
});

test('invalid journey states and alert intervals are rejected', function (array $attributes) {
    expect(fn () => Journey::factory()->create($attributes))->toThrow(QueryException::class);
})->with([
    'closed without ending time' => [['status' => 'completed']],
    'zero inactivity interval' => [['inactivity_seconds' => 0]],
    'negative escalation interval' => [['escalation_delay_seconds' => -1]],
]);

test('PostgreSQL rejects unknown states even when enum casts are bypassed', function (string $model) {
    $record = $model::factory()->create();

    expect(fn () => DB::table($record->getTable())->where('id', $record->id)->update(['status' => 'unknown']))->toThrow(QueryException::class);
})->with([Report::class, Journey::class]);

test('a closed journey cannot be reopened', function () {
    $journey = Journey::factory()->completedAt(now()->subHour())->create();

    expect(fn () => $journey->forceFill(['status' => JourneyStatus::Active, 'ended_at' => null])->save())->toThrow(QueryException::class);
});

test('a closed journey cannot have its retention extended', function () {
    $journey = Journey::factory()->completedAt(now()->subHours(25))->create();

    expect(fn () => $journey->forceFill(['ended_at' => now()])->save())->toThrow(QueryException::class);
});

test('journey interval snapshots cannot be changed after creation', function () {
    $journey = Journey::factory()->create();

    expect(fn () => $journey->forceFill(['inactivity_seconds' => 1])->save())->toThrow(QueryException::class);
});

test('a journey cannot include another users emergency contact', function () {
    $journey = Journey::factory()->create();
    $contact = EmergencyContact::factory()->create();

    expect(fn () => JourneyParticipant::factory()->for($journey)->for($contact)->create())->toThrow(QueryException::class);
});

test('existing journey and contact ownership cannot be reassigned', function (string $model) {
    $record = $model::factory()->create();
    $otherUser = User::factory()->create();

    expect(fn () => $record->forceFill(['user_id' => $otherUser->id])->save())->toThrow(QueryException::class);
})->with([Journey::class, EmergencyContact::class]);

test('a contact cannot be granted access twice to the same journey', function () {
    $participant = JourneyParticipant::factory()->create();

    expect(fn () => JourneyParticipant::factory()->for($participant->journey)->for($participant->emergencyContact)->create())->toThrow(QueryException::class);
});

test('a closed journey rejects new location samples', function () {
    $journey = Journey::factory()->completedAt(now()->subMinute())->create();

    expect(fn () => JourneyLocation::factory()->for($journey)->create())->toThrow(QueryException::class);
});

test('a closed journey rejects new participant grants', function () {
    $journey = Journey::factory()->completedAt(now()->subMinute())->create();

    expect(fn () => JourneyParticipant::factory()->for($journey)->create())->toThrow(QueryException::class);
});

test('location samples reject duplicate recording times', function () {
    $location = JourneyLocation::factory()->create();

    expect(fn () => JourneyLocation::factory()->for($location->journey)->create(['recorded_at' => $location->recorded_at]))->toThrow(QueryException::class);
});

test('location accuracy cannot be negative', function () {
    expect(fn () => JourneyLocation::factory()->create(['accuracy_meters' => -1]))->toThrow(QueryException::class);
});

test('a location cannot be recorded before its journey started', function () {
    $journey = Journey::factory()->create();

    expect(fn () => JourneyLocation::factory()->for($journey)->create(['recorded_at' => $journey->started_at->subSecond()]))->toThrow(QueryException::class);
});

test('location history cannot be rewritten after recording', function () {
    $location = JourneyLocation::factory()->create();

    expect(fn () => $location->forceFill(['accuracy_meters' => 100])->save())->toThrow(QueryException::class);
});

test('each inactivity cycle has only one alert', function () {
    $alert = JourneyAlert::factory()->create();

    expect(fn () => JourneyAlert::factory()->for($alert->journey)->create(['inactivity_started_at' => $alert->inactivity_started_at]))->toThrow(QueryException::class);
});

test('an emergency alert requires explicit confirmation', function () {
    expect(fn () => JourneyAlert::factory()->emergency()->create(['confirmed_at' => null]))->toThrow(QueryException::class);
});

test('the idempotency key cannot create a second emergency alert', function () {
    $key = (string) Str::uuid();
    $alert = JourneyAlert::factory()->emergency()->create(['idempotency_key' => $key]);

    expect(fn () => JourneyAlert::factory()->emergency()->for($alert->journey)->create(['idempotency_key' => $key]))->toThrow(QueryException::class);
});

test('closed journeys cannot trigger new alerts', function () {
    $journey = Journey::factory()->create();
    $journey->forceFill(['status' => JourneyStatus::Completed, 'ended_at' => now()])->save();

    expect(fn () => JourneyAlert::factory()->for($journey)->create())->toThrow(QueryException::class);
});

test('closed journeys cannot escalate existing alerts', function () {
    $alert = JourneyAlert::factory()->create();
    $alert->journey->forceFill(['status' => JourneyStatus::Completed, 'ended_at' => now()])->save();

    expect(fn () => $alert->forceFill(['status' => 'escalated'])->save())->toThrow(QueryException::class);
});

test('alerts cannot be triggered before the journey started', function () {
    $journey = Journey::factory()->create();

    expect(fn () => JourneyAlert::factory()->emergency()->for($journey)->create([
        'triggered_at' => $journey->started_at->subMinute(),
        'confirmed_at' => $journey->started_at->subMinute(),
    ]))->toThrow(QueryException::class);
});

test('alert delivery cannot address a participant in another journey', function () {
    $participant = JourneyParticipant::factory()->create();

    expect(fn () => AlertDelivery::factory()->create([
        'user_id' => null,
        'journey_participant_id' => $participant->id,
        'stage' => 'escalated',
    ]))->toThrow(QueryException::class);
});

test('preliminary alerts cannot notify contacts before escalation', function () {
    $participant = JourneyParticipant::factory()->create();
    $alert = JourneyAlert::factory()->for($participant->journey)->create();

    expect(fn () => AlertDelivery::factory()->for($alert)->create([
        'user_id' => null,
        'journey_participant_id' => $participant->id,
    ]))->toThrow(QueryException::class);
});

test('alert delivery cannot address a different registered user', function () {
    $user = User::factory()->create();

    expect(fn () => AlertDelivery::factory()->create(['user_id' => $user->id]))->toThrow(QueryException::class);
});

test('each recipient channel and stage has only one logical delivery', function () {
    $delivery = AlertDelivery::factory()->create();

    expect(fn () => AlertDelivery::factory()->for($delivery->journeyAlert)->create(['user_id' => $delivery->user_id]))->toThrow(QueryException::class);
});

test('deliveries cannot be reassigned after they have been queued', function () {
    $delivery = AlertDelivery::factory()->create();

    expect(fn () => $delivery->forceFill(['channel' => 'push'])->save())->toThrow(QueryException::class);
});

test('a preliminary alert cannot enqueue an escalated delivery', function () {
    expect(fn () => AlertDelivery::factory()->create(['stage' => 'escalated']))->toThrow(QueryException::class);
});

test('revoked participants cannot receive new emergency deliveries', function () {
    $participant = JourneyParticipant::factory()->create(['created_at' => now()->subMinute(), 'revoked_at' => now()]);
    $alert = JourneyAlert::factory()->emergency()->for($participant->journey)->create();

    expect(fn () => AlertDelivery::factory()->for($alert)->create([
        'user_id' => null,
        'journey_participant_id' => $participant->id,
        'stage' => 'escalated',
    ]))->toThrow(QueryException::class);
});

test('push subscriptions require exactly one owner', function (bool $both) {
    $participant = JourneyParticipant::factory()->create();
    $user = User::factory()->create();

    expect(fn () => PushSubscription::factory()->create([
        'user_id' => $both ? $user->id : null,
        'journey_participant_id' => $both ? $participant->id : null,
    ]))->toThrow(QueryException::class);
})->with(['neither owner' => false, 'both owner types' => true]);

test('the same push endpoint cannot be registered twice despite randomized encryption', function () {
    $subscription = PushSubscription::factory()->create();

    expect(fn () => PushSubscription::factory()->create(['endpoint' => $subscription->endpoint]))->toThrow(QueryException::class);
});
