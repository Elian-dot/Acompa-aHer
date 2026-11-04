<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'report_category_id' => ReportCategory::factory(),
            'is_anonymous' => true,
            'location' => 'SRID=4326;POINT(-74.8039 4.303)',
            'occurred_at' => now()->subHour(),
            'description' => null,
            'status' => ReportStatus::Pending,
        ];
    }

    public function identified(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['is_anonymous' => false, 'user_id' => $user->id]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Verified,
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
        ]);
    }
}
