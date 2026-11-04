<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maps\BuildHeatmap;
use App\Data\GeographicCoverage;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeatmapRequest;
use Illuminate\Http\JsonResponse;

class MapController extends Controller
{
    public function index(HeatmapRequest $request, GeographicCoverage $coverage, BuildHeatmap $heatmap): JsonResponse
    {
        return response()->json([
            'coverage' => $coverage->featureCollection(),
            'heatmap' => $heatmap->handle($request->filled('report_category_id') ? $request->integer('report_category_id') : null),
        ]);
    }
}
