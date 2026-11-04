<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property ReportStatus $status
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $moderated_at
 * @property CarbonImmutable|null $anonymized_at
 */
#[Fillable(['report_category_id', 'is_anonymous', 'location', 'occurred_at', 'description'])]
#[Hidden(['user_id', 'location', 'description', 'moderated_by'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    /** @var string */
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending', 'is_anonymous' => true];

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'description' => 'encrypted',
            'status' => ReportStatus::class,
            'occurred_at' => 'immutable_datetime',
            'moderated_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<Report> $query */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->where('status', ReportStatus::Verified);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReportCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'report_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }
}
