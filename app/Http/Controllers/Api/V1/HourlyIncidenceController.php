<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Statistics\BuildHourlyIncidence;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HourlyIncidenceController extends Controller
{
    public function __invoke(BuildHourlyIncidence $incidence): JsonResponse
    {
        return response()->json($incidence->handle());
    }
}
