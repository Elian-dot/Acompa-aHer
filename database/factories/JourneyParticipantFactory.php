<?php

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\Journey;
use App\Models\JourneyParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JourneyParticipant>
 */
class JourneyParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'journey_id' => Journey::factory(),
            'emergency_contact_id' => fn (array $attributes): int => EmergencyContact::factory()
                ->for(Journey::query()->whereKey($attributes['journey_id'])->firstOrFail()->user)
                ->create()->id,
            'access_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(2),
        ];
    }
}
