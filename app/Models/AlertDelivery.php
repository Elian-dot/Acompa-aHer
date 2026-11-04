<?php

namespace App\Models;

use App\Enums\AlertStage;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use Database\Factories\AlertDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel', 'stage'])]
class AlertDelivery extends Model
{
    /** @use HasFactory<AlertDeliveryFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'stage' => AlertStage::class,
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<JourneyAlert, $this> */
    public function journeyAlert(): BelongsTo
    {
        return $this->belongsTo(JourneyAlert::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<JourneyParticipant, $this> */
    public function journeyParticipant(): BelongsTo
    {
        return $this->belongsTo(JourneyParticipant::class);
    }
}
