<?php

namespace Database\Factories;

use App\Enums\AlertKind;
use App\Enums\AlertStatus;
use App\Models\Journey;
use App\Models\JourneyAlert;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JourneyAlert>
 */
class JourneyAlertFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'journey_id' => Journey::factory(),
            'kind' => AlertKind::Inactivity,
            'status' => AlertStatus::Preliminary,
            'inactivity_started_at' => fn (array $attributes): CarbonImmutable => Journey::query()->whereKey($attributes['journey_id'])->firstOrFail()->last_activity_at,
            'triggered_at' => now(),
            'escalation_due_at' => now()->addSeconds(config()->integer('safety.escalation_delay_seconds')),
        ];
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => AlertKind::Emergency,
            'status' => AlertStatus::Escalated,
            'inactivity_started_at' => null,
            'escalation_due_at' => null,
            'confirmed_at' => $attributes['triggered_at'],
        ]);
    }
}
