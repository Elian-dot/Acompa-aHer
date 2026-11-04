<?php

use App\Enums\JourneyStatus;
use App\Models\AlertDelivery;
use App\Models\Journey;
use App\Models\JourneyAlert;
use App\Models\JourneyLocation;
use App\Models\JourneyParticipant;
use App\Models\PushSubscription;

test('pruning permanently deletes an expired journey and all its dependent data', function () {
    $this->freezeTime();
    $journey = Journey::factory()->create([
        'started_at' => now()->subHours(26),
        'last_activity_at' => now()->subHours(25),
    ]);
    $participant = JourneyParticipant::factory()->for($journey)->create();
    $location = JourneyLocation::factory()->for($journey)->create(['recorded_at' => now()->subHours(25)]);
    $subscription = PushSubscription::factory()->forParticipant($participant)->create();
    $personalSubscription = PushSubscription::factory()->for($journey->user)->create();
    $alert = JourneyAlert::factory()->for($journey)->create();
    $delivery = AlertDelivery::factory()->for($alert)->create();
    $journey->forceFill(['status' => JourneyStatus::Completed, 'ended_at' => now()->subHours(25)])->save();

    $this->artisan('model:prune', ['--model' => [Journey::class]])->assertSuccessful();

    foreach ([$journey, $participant, $location, $subscription, $alert, $delivery] as $record) {
        $this->assertModelMissing($record);
    }
    $this->assertModelExists($participant->emergencyContact);
    $this->assertModelExists($journey->user);
    $this->assertModelExists($personalSubscription);
});

test('retention deletes exactly at 24 hours and preserves recent or active journeys', function () {
    $this->freezeTime();
    $expired = Journey::factory()->completedAt(now()->subHours(24))->create();
    $recent = Journey::factory()->completedAt(now()->subHours(24)->addSecond())->create();
    $active = Journey::factory()->create([
        'started_at' => now()->subDays(2),
        'last_activity_at' => now()->subDays(2),
    ]);

    $this->artisan('model:prune', ['--model' => [Journey::class]])->assertSuccessful();

    $this->assertModelMissing($expired);
    $this->assertModelExists($recent);
    $this->assertModelExists($active);
});
