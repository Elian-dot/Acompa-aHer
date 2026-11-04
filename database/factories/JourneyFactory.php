<?php

namespace Database\Factories;

use App\Enums\JourneyStatus;
use App\Models\Journey;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journey>
 */
class JourneyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'origin' => 'SRID=4326;POINT(-74.8039 4.303)',
            'destination' => 'SRID=4326;POINT(-74.801 4.306)',
            'route' => 'SRID=4326;LINESTRING(-74.8039 4.303,-74.801 4.306)',
            'status' => JourneyStatus::Active,
            'started_at' => now()->subMinutes(30),
            'last_activity_at' => now()->subMinutes(15),
        ];
    }

    public function completedAt(CarbonInterface $endedAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JourneyStatus::Completed,
            'started_at' => $endedAt->copy()->subMinutes(30),
            'last_activity_at' => $endedAt,
            'ended_at' => $endedAt,
        ]);
    }
}
