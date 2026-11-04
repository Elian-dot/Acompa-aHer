<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Journeys\BuildJourneyState;
use App\Http\Controllers\Controller;
use App\Models\Journey;
use App\Models\JourneyParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JourneyParticipantController extends Controller
{
    public function destroy(Journey $journey, JourneyParticipant $participant, BuildJourneyState $state): JsonResponse
    {
        Gate::authorize('update', $journey);

        if ($participant->revoked_at === null) {
            $participant->revoked_at = now();
            $participant->save();
        }
        $participant->pushSubscriptions()->delete();

        return response()->json(['data' => $state->forOwner($journey)]);
    }

    /**
     * Genera un enlace nuevo e invalida el anterior (por ejemplo, si el SMS no llegó o llegó a otra persona).
     */
    public function renew(Journey $journey, JourneyParticipant $participant, BuildJourneyState $state): JsonResponse
    {
        Gate::authorize('update', $journey);

        if (! $journey->isActive() || $participant->revoked_at !== null) {
            throw ValidationException::withMessages(['participant' => 'No se puede renovar el enlace de este contacto.']);
        }

        $token = Str::random(48);
        $hours = min(config()->integer('safety.share_default_hours'), config()->integer('safety.journey_max_hours'));
        $participant->forceFill(['access_token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours($hours), 'accepted_at' => null])->save();
        $participant->pushSubscriptions()->delete();

        return response()->json([
            'data' => $state->forOwner($journey),
            'share' => [JourneyController::shareEntry($participant->load('emergencyContact'), $token)],
        ]);
    }
}
