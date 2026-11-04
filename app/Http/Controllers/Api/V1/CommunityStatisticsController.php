<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Statistics\BuildCommunityStatistics;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityStatisticsController extends Controller
{
    public function __invoke(Request $request, BuildCommunityStatistics $statistics): JsonResponse
    {
        $validated = $request->validate(['period' => ['nullable', 'integer', 'in:30,90,180']]);

        return response()->json($statistics->handle((int) ($validated['period'] ?? 90)));
    }
}
