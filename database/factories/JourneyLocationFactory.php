<?php

namespace Database\Factories;

use App\Models\Journey;
use App\Models\JourneyLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyLocation>
 */
class JourneyLocationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'journey_id' => Journey::factory(),
            'position' => 'SRID=4326;POINT(-74.8039 4.303)',
            'accuracy_meters' => '8.50',
            'recorded_at' => now(),
            'received_at' => now(),
        ];
    }
}
