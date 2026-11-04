<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\ModerateReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateReportRequest;
use App\Http\Resources\ModerationReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportModerationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('moderate', Report::class);
        $data = $request->validate(['status' => ['sometimes', Rule::enum(ReportStatus::class)], 'page' => ['sometimes', 'integer', 'min:1']]);
        $reports = Report::query()->with(['category', 'attachments'])
            ->where('status', $data['status'] ?? ReportStatus::Pending->value)
            ->orderBy('created_at')->orderBy('id')->paginate(20);

        return ModerationReportResource::collection($reports);
    }

    public function update(ModerateReportRequest $request, Report $report, ModerateReport $moderateReport): ModerationReportResource
    {
        return new ModerationReportResource($moderateReport->handle($report, $request->user(), ReportStatus::from($request->string('status')->toString()), $request->validated('moderation_reason')));
    }
}
