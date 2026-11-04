<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maps\BuildReferenceMap;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReferenceMapController extends Controller
{
    public function __invoke(BuildReferenceMap $map): JsonResponse
    {
        return response()->json($map->handle());
    }
}
