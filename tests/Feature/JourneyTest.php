<?php

use App\Enums\AlertStatus;
use App\Enums\DeliveryStatus;
use App\Enums\JourneyStatus;
use App\Enums\UserRole;
use App\Jobs\DeliverAlertNotification;
use App\Models\AlertDelivery;
use App\Models\EmergencyContact;
use App\Models\JourneyAlert;
use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPush\PushResult;
use Illuminate\Support\Facades\Queue;

test('an authorized user starts a journey and receives one private link per contact without storing the token', function () {
    [$user, $contacts] = journeyOwner();

    $response = $this->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id, $contacts[1]->id]))
        ->assertCreated()->assertJsonPath('data.status', 'active')->assertJsonCount(2, 'share')->assertJsonCount(2, 'data.participants');

    $url = $response->json('share.0.tracking_url');
    $token = basename($url);
    expect($token)->toHaveLength(48);
    expect($response->json('share.0.sms_url'))->toStartWith('sms:+573001234567?body=');
    expect(JourneyParticipant::query()->where('access_token_hash', hash('sha256', $token))->exists())->toBeTrue();
    expect(JourneyParticipant::query()->where('access_token_hash', $token)->exists())->toBeFalse();
    expect($response->json('data.inactivity_seconds'))->toBe(600);
});

test('starting requires consent, ownership of the contacts, coverage and no other active journey', function () {
    [$user, $contacts] = journeyOwner();
    $foreign = EmergencyContact::factory()->create();

    $this->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id], ['consent' => false]))->assertUnprocessable()->assertJsonValidationErrors('consent');
    $this->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload([$foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('contact_ids');
    $this->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id], ['origin' => ['latitude' => 4.9, 'longitude' => -74.5]]))->assertUnprocessable()->assertJsonValidationErrors('origin');

    startJourneyFor($user, [$contacts[0]]);
    $this->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id]))->assertUnprocessable()->assertJsonValidationErrors('journey');
});

test('guests and users without the journey permission cannot start a journey', function () {
    [, $contacts] = journeyOwner();
    $this->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id]))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('api.v1.journeys.store'), startPayload([$contacts[0]->id]))->assertForbidden();
});

test('the current endpoint returns the active journey or null', function () {
    [$user, $contacts] = journeyOwner(1);
    $this->actingAs($user)->getJson(route('api.v1.journeys.current'))->assertOk()->assertJsonPath('data', null);
    [$journey] = startJourneyFor($user, $contacts);
    $this->getJson(route('api.v1.journeys.current'))->assertOk()->assertJsonPath('data.id', $journey->id)->assertJsonPath('data.location', null);
});

test('only samples that move beyond the threshold with good accuracy count as activity', function () {
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);
    $this->travel(2)->minutes();

    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.3031, -74.8039))->assertOk()->assertJsonPath('meta.counted_as_activity', false);
    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.3045, -74.8039, 250))->assertOk()->assertJsonPath('meta.counted_as_activity', false);
    expect($journey->fresh()->last_activity_at->equalTo($journey->started_at))->toBeTrue();

    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.3045, -74.8039))->assertOk()->assertJsonPath('meta.counted_as_activity', true)
        ->assertJsonPath('data.location.accuracy_meters', 10);
    expect($journey->fresh()->last_activity_at->greaterThan($journey->started_at))->toBeTrue();
});

test('locations from another user or for a closed journey are rejected', function () {
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);
    $stranger = User::factory()->create()->assignRole(UserRole::RegisteredUser);

    $this->actingAs($stranger)->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.304, -74.8039))->assertNotFound();
    $this->actingAs($user)->postJson(route('api.v1.journeys.finish', $journey), ['outcome' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.304, -74.8039))->assertUnprocessable()->assertJsonValidationErrors('journey');
    $this->postJson(route('api.v1.journeys.finish', $journey), ['outcome' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'completed');
});

test('stale or future samples are rejected', function () {
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);

    $this->postJson(route('api.v1.journeys.locations.store', $journey), [...sample(4.304, -74.8039), 'recorded_at' => now()->subHour()->toIso8601String()])->assertUnprocessable();
    $this->postJson(route('api.v1.journeys.locations.store', $journey), [...sample(4.304, -74.8039), 'recorded_at' => now()->addHour()->toIso8601String()])->assertUnprocessable();
});

test('a journey without valid activity raises one preliminary alert, then escalates to the contacts', function () {
    Queue::fake();
    [$user, $contacts] = journeyOwner();
    [$journey] = startJourneyFor($user, $contacts);

    $this->travel(9)->minutes();
    $this->artisan('journeys:process-alerts')->assertSuccessful();
    expect(JourneyAlert::query()->count())->toBe(0);

    $this->travel(2)->minutes();
    $this->artisan('journeys:process-alerts')->assertSuccessful();
    $this->artisan('journeys:process-alerts')->assertSuccessful();

    $alert = JourneyAlert::query()->sole();
    expect($alert->status)->toBe(AlertStatus::Preliminary);
    expect(AlertDelivery::query()->count())->toBe(1);
    expect(AlertDelivery::query()->sole()->user_id)->toBe($user->id);
    Queue::assertPushed(DeliverAlertNotification::class, 1);

    $this->travel(3)->minutes();
    $this->artisan('journeys:process-alerts')->assertSuccessful();
    $this->artisan('journeys:process-alerts')->assertSuccessful();

    expect($alert->fresh()->status)->toBe(AlertStatus::Escalated);
    expect(AlertDelivery::query()->count())->toBe(4);
    expect(AlertDelivery::query()->whereNotNull('journey_participant_id')->count())->toBe(2);
});

test('activity or a manual confirmation resolves the open alert and restarts the cycle', function () {
    Queue::fake();
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);
    $this->travel(11)->minutes();
    $this->artisan('journeys:process-alerts');
    expect(JourneyAlert::query()->sole()->status)->toBe(AlertStatus::Preliminary);

    $this->postJson(route('api.v1.journeys.activity.store', $journey), [])->assertOk()->assertJsonPath('data.alert', null);
    expect(JourneyAlert::query()->sole()->status)->toBe(AlertStatus::Resolved);

    $this->travel(3)->minutes();
    $this->artisan('journeys:process-alerts');
    expect(JourneyAlert::query()->count())->toBe(1);
    expect(AlertDelivery::query()->count())->toBe(1);

    $this->travel(8)->minutes();
    $this->artisan('journeys:process-alerts');
    expect(JourneyAlert::query()->count())->toBe(2);
});

test('a voluntary pause delays the inactivity alert', function () {
    Queue::fake();
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);

    $this->postJson(route('api.v1.journeys.activity.store', $journey), ['pause_minutes' => 20])->assertOk()->assertJsonPath('data.paused_until', fn ($value) => $value !== null);
    $this->travel(25)->minutes();
    $this->artisan('journeys:process-alerts');
    expect(JourneyAlert::query()->count())->toBe(0);

    $this->travel(10)->minutes();
    $this->artisan('journeys:process-alerts');
    expect(JourneyAlert::query()->count())->toBe(1);

    $this->postJson(route('api.v1.journeys.activity.store', $journey), ['pause_minutes' => 99])->assertUnprocessable();
});

test('movement before the escalation due time prevents escalation', function () {
    Queue::fake();
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);
    $this->travel(11)->minutes();
    $this->artisan('journeys:process-alerts');

    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.3045, -74.8039))->assertOk()->assertJsonPath('meta.counted_as_activity', true);
    $this->travel(3)->minutes();
    $this->artisan('journeys:process-alerts');

    expect(JourneyAlert::query()->sole()->status)->toBe(AlertStatus::Resolved);
    expect(AlertDelivery::query()->count())->toBe(1);
});

test('abandoned journeys are closed automatically so retention can start', function () {
    Queue::fake();
    [$user, $contacts] = journeyOwner(1);
    [$journey] = startJourneyFor($user, $contacts);

    $this->travel(13)->hours();
    $this->artisan('journeys:process-alerts')->assertSuccessful();

    expect($journey->fresh()->status)->toBe(JourneyStatus::Cancelled);
    expect($journey->fresh()->ended_at)->not->toBeNull();
});

test('revoking a participant stops its access and renewing issues a new link', function () {
    [$user, $contacts] = journeyOwner(2);
    [$journey, $share] = startJourneyFor($user, $contacts);
    $participant = JourneyParticipant::query()->findOrFail($share[0]['participant_id']);
    $oldToken = basename($share[0]['tracking_url']);

    $renewed = $this->postJson(route('api.v1.journeys.participants.renew', [$journey, $participant]))->assertOk();
    $newToken = basename($renewed->json('share.0.tracking_url'));
    expect($newToken)->not->toBe($oldToken);
    $this->getJson(route('api.v1.tracking.show', $oldToken))->assertNotFound();
    $this->getJson(route('api.v1.tracking.show', $newToken))->assertOk()->assertJsonPath('data.state', 'active');

    $this->deleteJson(route('api.v1.journeys.participants.destroy', [$journey, $participant]))->assertOk();
    $this->getJson(route('api.v1.tracking.show', $newToken))->assertOk()->assertJsonPath('data.state', 'revoked');
    $this->postJson(route('api.v1.journeys.participants.renew', [$journey, $participant]))->assertUnprocessable();

    $other = JourneyParticipant::query()->findOrFail($share[1]['participant_id']);
    $stranger = User::factory()->create()->assignRole(UserRole::RegisteredUser);
    $this->actingAs($stranger)->deleteJson(route('api.v1.journeys.participants.destroy', [$journey, $other]))->assertNotFound();
});

function alertWithDelivery(string $stage, bool $toParticipant): array
{
    [$user, $contacts] = journeyOwner(1);
    [$journey, $share] = startJourneyFor($user, $contacts);
    $participant = JourneyParticipant::query()->findOrFail($share[0]['participant_id']);
    $alert = new JourneyAlert;
    $alert->forceFill([
        'journey_id' => $journey->id, 'kind' => 'inactivity', 'status' => $stage,
        'inactivity_started_at' => $journey->last_activity_at, 'triggered_at' => now(), 'escalation_due_at' => now()->addMinutes(2),
    ])->save();
    $delivery = new AlertDelivery;
    $delivery->forceFill([
        'journey_alert_id' => $alert->id, 'user_id' => $toParticipant ? null : $user->id,
        'journey_participant_id' => $toParticipant ? $participant->id : null, 'channel' => 'push', 'stage' => $stage,
    ])->save();

    return [$user, $participant, $alert, $delivery];
}

test('a delivery is marked as sent when at least one subscription accepts the notification', function () {
    [$user, , , $delivery] = alertWithDelivery('preliminary', false);
    PushSubscription::factory()->create(['user_id' => $user->id, 'journey_participant_id' => null]);
    $sender = new FakePushSender;

    (new DeliverAlertNotification($delivery->id))->handle($sender);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sent);
    expect($delivery->fresh()->attempts)->toBe(1);
    expect($sender->sent[0]['title'])->toBe('¿Estás bien?');
});

test('a delivery without any subscription fails honestly instead of pretending it was sent', function () {
    [, , , $delivery] = alertWithDelivery('preliminary', false);
    $sender = new FakePushSender;

    (new DeliverAlertNotification($delivery->id))->handle($sender);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed);
    expect($delivery->fresh()->failure_code)->toBe('no_subscription');
    expect($sender->sent)->toBe([]);
});

test('an expired subscription is removed and the delivery fails', function () {
    [$user, , , $delivery] = alertWithDelivery('preliminary', false);
    PushSubscription::factory()->create(['user_id' => $user->id, 'journey_participant_id' => null]);

    (new DeliverAlertNotification($delivery->id))->handle(new FakePushSender(new PushResult(false, true, 'push_rejected')));

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed);
    expect($delivery->fresh()->failure_code)->toBe('subscription_expired');
    expect(PushSubscription::query()->count())->toBe(0);
});

test('a notification is not sent when the alert was resolved in the meantime', function () {
    [$user, , $alert, $delivery] = alertWithDelivery('preliminary', false);
    PushSubscription::factory()->create(['user_id' => $user->id, 'journey_participant_id' => null]);
    $alert->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();
    $sender = new FakePushSender;

    (new DeliverAlertNotification($delivery->id))->handle($sender);

    expect($sender->sent)->toBe([]);
    expect($delivery->fresh()->failure_code)->toBe('alert_resolved');
});

test('contacts receive an escalated notification that mentions the owner first name only', function () {
    [$user, $participant, $alert, $delivery] = alertWithDelivery('escalated', true);
    PushSubscription::factory()->create(['user_id' => null, 'journey_participant_id' => $participant->id]);
    $sender = new FakePushSender;

    (new DeliverAlertNotification($delivery->id))->handle($sender);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sent);
    expect($sender->sent[0]['title'])->toBe(explode(' ', $user->name)[0].' no ha respondido');
    expect($sender->sent[0]['participant'])->toBe($participant->id);
    expect(json_encode($sender->sent[0]))->not->toContain('3001234567');
});
