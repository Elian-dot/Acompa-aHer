<?php

use App\Enums\AlertStatus;
use App\Enums\UserRole;
use App\Models\Journey;
use App\Models\JourneyAlert;
use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Journey, 2: JourneyParticipant, 3: string} */
function trackedJourney(): array
{
    [$user, $contacts] = journeyOwner(1);
    [$journey, $share] = startJourneyFor($user, $contacts);

    return [$user, $journey, JourneyParticipant::query()->findOrFail($share[0]['participant_id']), basename($share[0]['tracking_url'])];
}

function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyValue', 'auth' => 'AuthSecretValue'], 'contentEncoding' => 'aes128gcm'];
}

test('the tracking page opens without an account and is not cacheable or indexable by referrer', function () {
    [, , , $token] = trackedJourney();
    auth()->logout();

    $this->get(route('tracking.page', $token))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seguimiento')->where('token', $token))
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('a contact sees the last location, destination and owner first name but nothing private', function () {
    [$user, $journey, $participant, $token] = trackedJourney();
    $this->postJson(route('api.v1.journeys.locations.store', $journey), sample(4.3045, -74.8039))->assertOk();
    auth()->logout();

    $response = $this->getJson(route('api.v1.tracking.show', $token))->assertOk()
        ->assertJsonPath('data.state', 'active')->assertJsonPath('data.owner_name', explode(' ', $user->name)[0])
        ->assertJsonPath('data.location.latitude', 4.3045)->assertJsonPath('data.destination.latitude', 4.31);

    expect(json_encode($response->json()))->not->toContain($user->email)->not->toContain('3001234567');
    expect($participant->fresh()->accepted_at)->not->toBeNull();
});

test('unknown tokens look like missing pages and ended states reveal no location', function () {
    [$user, $journey, $participant, $token] = trackedJourney();
    auth()->logout();

    $this->getJson(route('api.v1.tracking.show', str_repeat('x', 48)))->assertNotFound();

    $participant->forceFill(['expires_at' => now()->addSecond()])->save();
    $this->travel(5)->seconds();
    $this->getJson(route('api.v1.tracking.show', $token))->assertOk()->assertJsonPath('data.state', 'expired')->assertJsonMissingPath('data.location');
    $this->travelBack();

    $participant->forceFill(['expires_at' => now()->addHour()])->save();
    $this->actingAs($user)->postJson(route('api.v1.journeys.finish', $journey), ['outcome' => 'completed'])->assertOk();
    auth()->logout();
    $this->getJson(route('api.v1.tracking.show', $token))->assertOk()->assertJsonPath('data.state', 'ended')->assertJsonMissingPath('data.location');
});

test('a contact acknowledges only an escalated alert and the owner sees it', function () {
    [$user, $journey, , $token] = trackedJourney();
    $alert = new JourneyAlert;
    $alert->forceFill([
        'journey_id' => $journey->id, 'kind' => 'inactivity', 'status' => 'preliminary',
        'inactivity_started_at' => $journey->last_activity_at, 'triggered_at' => now(), 'escalation_due_at' => now()->addMinutes(2),
    ])->save();
    auth()->logout();

    $this->postJson(route('api.v1.tracking.acknowledge', $token))->assertOk();
    expect($alert->fresh()->status)->toBe(AlertStatus::Preliminary);
    $this->getJson(route('api.v1.tracking.show', $token))->assertJsonPath('data.alert', null);

    $alert->forceFill(['status' => 'escalated'])->save();
    $this->getJson(route('api.v1.tracking.show', $token))->assertJsonPath('data.alert.status', 'escalated');
    $this->postJson(route('api.v1.tracking.acknowledge', $token))->assertOk()->assertJsonPath('data.alert.status', 'acknowledged');
    expect($alert->fresh()->acknowledged_at)->not->toBeNull();

    $this->actingAs($user)->getJson(route('api.v1.journeys.current'))->assertJsonPath('data.alert.status', 'acknowledged');
});

test('a contact registers a push device only with a supported push service', function () {
    [, , $participant, $token] = trackedJourney();
    auth()->logout();

    $this->postJson(route('api.v1.tracking.push_subscription.store', $token), subscriptionPayload())->assertCreated()->assertJsonPath('participant', $participant->id);
    expect(PushSubscription::query()->where('journey_participant_id', $participant->id)->count())->toBe(1);
    $this->getJson(route('api.v1.tracking.show', $token))->assertJsonPath('data.push_enabled', true);

    foreach (['http://fcm.googleapis.com/fcm/send/abc', 'https://evil.example.com/push', 'https://fcm.googleapis.com.evil.com/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com:8443/x', 'https://user:pass@fcm.googleapis.com/x', 'file:///etc/passwd'] as $endpoint) {
        $this->postJson(route('api.v1.tracking.push_subscription.store', $token), subscriptionPayload($endpoint))->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    }

    $this->deleteJson(route('api.v1.tracking.push_subscription.destroy', $token), ['endpoint' => subscriptionPayload()['endpoint']])->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

test('push subscriptions of an ended tracking link are refused', function () {
    [$user, $journey, , $token] = trackedJourney();
    $this->actingAs($user)->postJson(route('api.v1.journeys.finish', $journey), ['outcome' => 'cancelled'])->assertOk();
    auth()->logout();

    $this->postJson(route('api.v1.tracking.push_subscription.store', $token), subscriptionPayload())->assertStatus(410);
});

test('the owner manages her own push devices, capped at five, and cannot remove someone else', function () {
    [$user] = journeyOwner(1);
    $other = User::factory()->create()->assignRole(UserRole::RegisteredUser);

    foreach (range(1, 7) as $number) {
        $this->actingAs($user)->postJson(route('api.v1.push_subscriptions.store'), subscriptionPayload("https://fcm.googleapis.com/fcm/send/device{$number}"))->assertCreated();
    }
    expect(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(5);

    $this->actingAs($other)->deleteJson(route('api.v1.push_subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device7'])->assertOk();
    expect(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(5);
    $this->actingAs($user)->deleteJson(route('api.v1.push_subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device7'])->assertOk();
    expect(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(4);
});

test('the tracking endpoint is rate limited per link', function () {
    [, , , $token] = trackedJourney();
    auth()->logout();

    foreach (range(1, 40) as $ignored) {
        $this->getJson(route('api.v1.tracking.show', $token))->assertOk();
    }
    $this->getJson(route('api.v1.tracking.show', $token))->assertStatus(429);
});
