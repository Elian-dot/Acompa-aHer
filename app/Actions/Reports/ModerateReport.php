<?php

namespace App\Actions\Reports;

use App\Data\GeographicCoverage;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ModerateReport
{
    public function __construct(private GeographicCoverage $coverage) {}

    public function handle(Report $report, User $moderator, ReportStatus $status, ?string $reason): Report
    {
        return DB::transaction(function () use ($report, $moderator, $status, $reason): Report {
            $report = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($report->status === ReportStatus::Pending, 409, 'Otra moderadora ya resolvió este reporte. Actualiza la bandeja.');

            if ($status === ReportStatus::Verified) {
                $point = Report::query()->whereKey($report->id)->selectRaw('ST_X(location) AS longitude, ST_Y(location) AS latitude')->firstOrFail();
                $this->coverage->validatePoint((float) $point->getAttribute('longitude'), (float) $point->getAttribute('latitude'), 'location');
            }

            $report->status = $status;
            $report->moderator()->associate($moderator);
            $report->moderated_at = now();
            $report->moderation_reason = $status === ReportStatus::Discarded ? $reason : null;
            $report->save();

            return $report->load(['category', 'attachments']);
        });
    }
}
