<?php

namespace Database\Factories;

use App\Models\GeographicFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeographicFeature>
 */
class GeographicFeatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'osm_type' => 'way',
            'osm_id' => fake()->unique()->numberBetween(1, 2000000000),
            'kind' => 'road',
            'name' => 'Calle de prueba',
            'classification' => 'residential',
            'geometry' => 'SRID=4326;LINESTRING(-74.8039 4.303,-74.802 4.307)',
            'source_retrieved_at' => now(),
        ];
    }
}
