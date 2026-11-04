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
use Carbon\CarbonImmutable;
use Database\Seeders\ReportCategorySeeder;
use Illuminate\Support\Facades\DB;

test('reports use UUID v4 and expose usable category and attachment relations', function () {
    $report = Report::factory()->create();
    $attachment = ReportAttachment::factory()->for($report)->create();

    $stored = Report::query()->selectRaw('reports.*, ST_SRID(location) AS srid, ST_X(location) AS longitude')->findOrFail($report->id);

    expect($stored->id)->toBeUuid();
    expect($stored->is_anonymous)->toBeTrue();
    expect($stored->user)->toBeNull();
    expect($stored->category->is_active)->toBeTrue();
    expect($stored->attachments->sole()->is($attachment))->toBeTrue();
    expect($stored->srid)->toBe(4326);
    expect((float) $stored->longitude)->toBe(-74.8039);
});

test('contact identity and push secrets are encrypted and excluded from serialization', function () {
    $contact = EmergencyContact::factory()->create(['name' => 'Contacto privado', 'phone' => '+573001234567']);
    $subscription = PushSubscription::factory()->create();
    $report = Report::factory()->create(['description' => 'Descripción privada']);

    $rawContact = DB::table('emergency_contacts')->where('id', $contact->id)->first();
    $rawSubscription = DB::table('push_subscriptions')->where('id', $subscription->id)->first();

    expect($rawContact->name)->not->toBe('Contacto privado');
    expect($rawContact->phone)->not->toBe('+573001234567');
    expect($contact->fresh()->phone)->toBe('+573001234567');
    expect($contact->toArray())->not->toHaveKey('phone');
    expect($rawSubscription->endpoint)->not->toBe($subscription->endpoint);
    expect($rawSubscription->auth_token)->not->toBe('test-auth-token');
    expect($subscription->fresh()->auth_token)->toBe('test-auth-token');
    expect($subscription->toArray())->not->toHaveKey('endpoint');
    expect($subscription->toArray())->not->toHaveKey('auth_token');
    expect(DB::table('reports')->where('id', $report->id)->value('description'))->not->toBe('Descripción privada');
    expect($report->fresh()->description)->toBe('Descripción privada');
});

test('journeys snapshot the configured 15 minute inactivity and 2 minute escalation intervals', function () {
    $journey = Journey::factory()->create();

    config()->set('safety.inactivity_seconds', 1800);
    config()->set('safety.escalation_delay_seconds', 240);
    $laterJourney = Journey::factory()->create();

    expect($journey->fresh()->inactivity_seconds)->toBe(900);
    expect($journey->fresh()->escalation_delay_seconds)->toBe(120);
    expect($laterJourney->inactivity_seconds)->toBe(1800);
    expect($laterJourney->escalation_delay_seconds)->toBe(240);
});

test('tracking requires the correct secret and stores only its hash', function () {
    $participant = JourneyParticipant::factory()->create(['access_token_hash' => hash('sha256', 'tracking-secret')]);

    expect($participant->allowsTracking('tracking-secret'))->toBeTrue();
    expect($participant->allowsTracking('wrong-secret'))->toBeFalse();
    expect($participant->toArray())->not->toHaveKey('access_token_hash');
    $this->assertDatabaseMissing('journey_participants', ['access_token_hash' => 'tracking-secret']);
});

test('an escalated emergency can address its authorized contact without requiring a user account', function () {
    $participant = JourneyParticipant::factory()->create();
    $alert = JourneyAlert::factory()->emergency()->for($participant->journey)->create();
    $subscription = PushSubscription::factory()->forParticipant($participant)->create();

    $delivery = AlertDelivery::factory()->for($alert)->create([
        'user_id' => null,
        'journey_participant_id' => $participant->id,
        'stage' => 'escalated',
        'channel' => 'push',
    ]);

    expect($delivery->journeyParticipant->is($participant))->toBeTrue();
    expect($subscription->user)->toBeNull();
    expect($participant->pushSubscriptions->sole()->is($subscription))->toBeTrue();
});

test('expired or revoked grants deny tracking', function (Closure $invalidGrant) {
    $this->freezeTime();
    $participant = JourneyParticipant::factory()->create([
        'access_token_hash' => hash('sha256', 'tracking-secret'),
        'created_at' => now()->subHour(),
    ]);

    $participant->forceFill($invalidGrant())->save();

    expect($participant->allowsTracking('tracking-secret'))->toBeFalse();
})->with([
    'expires at the current instant' => [fn (): array => ['expires_at' => now()]],
    'revoked' => [fn (): array => ['revoked_at' => now()]],
]);

test('closing a journey immediately denies tracking through an otherwise valid grant', function () {
    $participant = JourneyParticipant::factory()->create(['access_token_hash' => hash('sha256', 'tracking-secret')]);
    $journey = $participant->journey;
    expect($participant->allowsTracking('tracking-secret'))->toBeTrue();

    Journey::query()->findOrFail($journey->id)->forceFill(['status' => JourneyStatus::Completed, 'ended_at' => now()])->save();

    expect($participant->allowsTracking('tracking-secret'))->toBeFalse();
});

test('a cached grant cannot keep tracking after revocation by another request', function () {
    $participant = JourneyParticipant::factory()->create(['access_token_hash' => hash('sha256', 'tracking-secret')]);
    expect($participant->allowsTracking('tracking-secret'))->toBeTrue();

    JourneyParticipant::query()->findOrFail($participant->id)->forceFill(['revoked_at' => now()])->save();

    expect($participant->allowsTracking('tracking-secret'))->toBeFalse();
});

test('only verified reports are eligible for public aggregations', function () {
    Report::factory()->create();
    $verified = Report::factory()->verified()->create();
    Report::factory()->verified()->create(['status' => 'discarded']);

    $reports = Report::query()->verified()->get();

    expect($reports)->toHaveCount(1);
    expect($reports->sole()->is($verified))->toBeTrue();
});

test('deleting an account removes private dependents while retaining reports without authorship', function () {
    $user = User::factory()->create();
    $report = Report::factory()->identified($user)->create();
    $contact = EmergencyContact::factory()->for($user)->create();
    $journey = Journey::factory()->for($user)->create();

    $user->delete();

    expect($report->fresh()->user_id)->toBeNull();
    $this->assertModelMissing($contact);
    $this->assertModelMissing($journey);
});

test('a zone spatially contains reports without persisting a redundant zone assignment', function () {
    $zone = Zone::factory()->create();

    $zones = Zone::query()->whereRaw('ST_Covers(boundary, ST_GeomFromEWKT(?))', ['SRID=4326;POINT(-74.8039 4.303)'])->get();

    expect($zones->sole()->is($zone))->toBeTrue();
});

test('location samples preserve multiple recording times within the same second', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04T21:00:00Z'));
    $journey = Journey::factory()->create();

    $first = JourneyLocation::factory()->for($journey)->create(['recorded_at' => now()->addMicroseconds(100000)]);
    $second = JourneyLocation::factory()->for($journey)->create(['recorded_at' => now()->addMicroseconds(200000)]);

    expect($first->fresh()->recorded_at->format('u'))->toBe('100000');
    expect($second->fresh()->recorded_at->format('u'))->toBe('200000');
    expect($journey->locations()->count())->toBe(2);
});

test('timestamps preserve the instant and microseconds when an input includes a timezone offset', function () {
    $report = Report::factory()->create(['occurred_at' => '2026-10-01T21:30:00.123456-05:00']);

    expect($report->fresh()->occurred_at->utc()->format('Y-m-d H:i:s.u'))->toBe('2026-10-02 02:30:00.123456');
});

test('the initial category catalog can be seeded repeatedly without fabricated reports or zones', function () {
    $this->seed(ReportCategorySeeder::class);
    $this->seed(ReportCategorySeeder::class);

    $this->assertDatabaseCount('report_categories', 6);
    $this->assertDatabaseHas('report_categories', ['code' => 'other', 'is_active' => true]);
    $this->assertDatabaseCount('reports', 0);
    $this->assertDatabaseCount('zones', 0);
});
