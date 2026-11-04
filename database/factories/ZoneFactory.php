<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('test-zone-########'),
            'name' => 'Zona de prueba',
            'boundary' => 'SRID=4326;MULTIPOLYGON(((-74.82 4.28,-74.78 4.28,-74.78 4.33,-74.82 4.33,-74.82 4.28)))',
        ];
    }
}
