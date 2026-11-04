<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAttachment>
 */
class ReportAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'disk' => 'local',
            'path' => 'reports/'.fake()->unique()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ];
    }
}
