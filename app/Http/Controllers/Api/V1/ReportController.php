<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\CreateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, CreateReport $createReport): JsonResponse
    {
        $report = $createReport->handle($request->user(), $request->submission());

        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status->value], 'message' => 'Reporte recibido. Será revisado antes de aparecer en consultas públicas.'], 201);
    }
}
