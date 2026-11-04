<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Journeys\StorePushSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request, StorePushSubscription $store): JsonResponse
    {
        $store->handle($request->validated(), $request->user(), null);

        return response()->json(['message' => 'Notificaciones activadas en este dispositivo.'], 201);
    }

    public function destroy(PushSubscriptionRequest $request): JsonResponse
    {
        PushSubscription::query()->where('endpoint_hash', hash('sha256', $request->string('endpoint')->toString()))
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Notificaciones desactivadas en este dispositivo.']);
    }
}
