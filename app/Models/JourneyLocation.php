<?php

namespace App\Models;

use Database\Factories\JourneyLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'accuracy_meters', 'recorded_at'])]
#[Hidden(['position'])]
class JourneyLocation extends Model
{
    /** @use HasFactory<JourneyLocationFactory> */
    use HasFactory;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['accuracy_meters' => 'decimal:2', 'recorded_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Journey, $this> */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
