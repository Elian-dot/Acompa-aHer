<?php

use App\Enums\UserRole;
use App\Models\EmergencyContact;
use App\Models\Journey;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Zone;
use App\Services\WebPush\PushResult;
use App\Services\WebPush\PushSender;
use Database\Seeders\RolesAndPermissionsSeeder;

class FakePushSender implements PushSender
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function __construct(private PushResult $result = new PushResult(true)) {}

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $this->sent[] = $payload;

        return $this->result;
    }
}

/** @return array{0: User, 1: list<EmergencyContact>} */
function journeyOwner(int $contacts = 2): array
{
    test()->seed(RolesAndPermissionsSeeder::class);
    Zone::factory()->create(['code' => '25307']);
    $user = User::factory()->create()->assignRole(UserRole::RegisteredUser);

    return [$user, EmergencyContact::factory()->count($contacts)->for($user)->create()->all()];
}

/** @param array<string, mixed> $overrides */
function startPayload(array $contactIds, array $overrides = []): array
{
    return array_replace([
        'origin' => ['latitude' => 4.303, 'longitude' => -74.8039],
        'destination' => ['latitude' => 4.31, 'longitude' => -74.80],
        'contact_ids' => $contactIds,
        'inactivity_minutes' => 10,
        'share_hours' => 2,
        'consent' => true,
    ], $overrides);
}

function startJourneyFor(User $user, array $contacts, array $overrides = []): array
{
    $response = test()->actingAs($user)->postJson(route('api.v1.journeys.store'), startPayload(array_map(fn ($c) => $c->id, $contacts), $overrides))->assertCreated();

    return [Journey::query()->findOrFail($response->json('data.id')), $response->json('share')];
}

function sample(float $latitude, float $longitude, float $accuracy = 10): array
{
    return ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy_meters' => $accuracy, 'recorded_at' => now()->toIso8601String()];
}
