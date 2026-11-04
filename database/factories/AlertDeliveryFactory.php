<?php

namespace Database\Factories;

use App\Enums\AlertStage;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Models\AlertDelivery;
use App\Models\JourneyAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertDelivery>
 */
class AlertDeliveryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'journey_alert_id' => JourneyAlert::factory(),
            'user_id' => fn (array $attributes): int => JourneyAlert::query()->whereKey($attributes['journey_alert_id'])->firstOrFail()->journey->user_id,
            'journey_participant_id' => null,
            'channel' => DeliveryChannel::Websocket,
            'stage' => AlertStage::Preliminary,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
        ];
    }
}
