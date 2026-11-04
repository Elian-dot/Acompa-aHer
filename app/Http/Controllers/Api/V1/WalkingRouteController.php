<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Routes\CalculateWalkingRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateWalkingRouteRequest;
use Illuminate\Http\JsonResponse;

class WalkingRouteController extends Controller
{
    public function store(CalculateWalkingRouteRequest $request, CalculateWalkingRoutes $calculateRoutes): JsonResponse
    {
        return response()->json($calculateRoutes->handle($request->validated('origin'), $request->validated('destination')));
    }
}
