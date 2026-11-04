<?php

namespace App\Models;

use App\Enums\JourneyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JourneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $inactivity_paused_until
 * @property int $inactivity_seconds
 * @property int $escalation_delay_seconds
 * @property JourneyStatus $status
 */
#[Fillable(['origin', 'destination', 'route'])]
#[Hidden(['origin', 'destination', 'route', 'user_id'])]
class Journey extends Model
{
    /** @use HasFactory<JourneyFactory> */
    use HasFactory, MassPrunable;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::creating(function (Journey $journey): void {
            $journey->started_at ??= now();
            $journey->last_activity_at ??= $journey->started_at;
            $journey->inactivity_seconds ??= config()->integer('safety.inactivity_seconds');
            $journey->escalation_delay_seconds ??= config()->integer('safety.escalation_delay_seconds');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => JourneyStatus::class,
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'inactivity_paused_until' => 'immutable_datetime',
            'inactivity_seconds' => 'integer',
            'escalation_delay_seconds' => 'integer',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('ended_at')->where('ended_at', '<=', now()->subHours(24)->format($this->getDateFormat()));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<JourneyParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(JourneyParticipant::class);
    }

    /** @return HasMany<JourneyLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(JourneyLocation::class);
    }

    /** @return HasMany<JourneyAlert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(JourneyAlert::class);
    }

    /** @return HasOne<JourneyLocation, $this> */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(JourneyLocation::class)->latestOfMany('recorded_at');
    }

    public function isActive(): bool
    {
        return $this->status === JourneyStatus::Active;
    }
}
