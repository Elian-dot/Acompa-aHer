<?php

namespace Database\Factories;

use App\Models\ReportCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCategory>
 */
class ReportCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
