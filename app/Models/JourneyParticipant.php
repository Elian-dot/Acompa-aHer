<?php

namespace App\Models;

use App\Enums\JourneyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JourneyParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $access_token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property-read Journey $journey
 */
#[Fillable(['expires_at'])]
#[Hidden(['access_token_hash'])]
class JourneyParticipant extends Model
{
    /** @use HasFactory<JourneyParticipantFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function allowsTracking(string $token): bool
    {
        if (! hash_equals($this->access_token_hash, hash('sha256', $token))) {
            return false;
        }

        return static::query()
            ->whereKey($this->getKey())
            ->where('access_token_hash', $this->access_token_hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now()->format($this->getDateFormat()))
            ->whereHas('journey', fn (Builder $journeys): Builder => $journeys->where('status', JourneyStatus::Active))
            ->exists();
    }

    /** @return BelongsTo<Journey, $this> */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /** @return BelongsTo<EmergencyContact, $this> */
    public function emergencyContact(): BelongsTo
    {
        return $this->belongsTo(EmergencyContact::class);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<AlertDelivery, $this> */
    public function alertDeliveries(): HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }
}
