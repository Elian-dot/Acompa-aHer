<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $endpoint
 * @property string $endpoint_hash
 */
#[Fillable(['endpoint', 'public_key', 'auth_token', 'content_encoding', 'expires_at'])]
#[Hidden(['endpoint', 'endpoint_hash', 'public_key', 'auth_token'])]
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription): void {
            $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['endpoint' => 'encrypted', 'public_key' => 'encrypted', 'auth_token' => 'encrypted', 'expires_at' => 'immutable_datetime'];
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
