<?php

namespace Database\Factories;

use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'journey_participant_id' => null,
            'endpoint' => 'https://push.example.test/'.fake()->unique()->uuid(),
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aes128gcm',
        ];
    }

    public function forParticipant(JourneyParticipant $participant): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'journey_participant_id' => $participant->id,
        ]);
    }
}
