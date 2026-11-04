<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

readonly class ReportSubmission
{
    /** @param list<UploadedFile> $attachments */
    public function __construct(
        public int $categoryId,
        public float $latitude,
        public float $longitude,
        public CarbonImmutable $occurredAt,
        public ?string $description,
        public bool $isAnonymous,
        public array $attachments,
    ) {}
}
