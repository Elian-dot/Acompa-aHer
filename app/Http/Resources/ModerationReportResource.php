<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Report */
class ModerationReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->name,
            'description' => $this->description,
            'is_anonymous' => $this->is_anonymous,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'status' => $this->status->value,
            'moderation_reason' => $this->moderation_reason,
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment): array => [
                'id' => $attachment->id,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ])),
        ];
    }
}
