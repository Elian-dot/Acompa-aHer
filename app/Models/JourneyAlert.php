<?php

namespace App\Models;

use App\Enums\AlertKind;
use App\Enums\AlertStatus;
use Database\Factories\JourneyAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $idempotency_key */
#[Fillable(['kind', 'idempotency_key'])]
class JourneyAlert extends Model
{
    /** @use HasFactory<JourneyAlertFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'preliminary'];

    protected static function booted(): void
    {
        static::creating(function (JourneyAlert $alert): void {
            $alert->idempotency_key ??= (string) Str::uuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => AlertKind::class,
            'status' => AlertStatus::class,
            'inactivity_started_at' => 'immutable_datetime',
            'triggered_at' => 'immutable_datetime',
            'escalation_due_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Journey, $this> */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /** @return HasMany<AlertDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(AlertDelivery::class);
    }
}
