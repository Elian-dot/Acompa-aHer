<?php

namespace App\Http\Controllers;

use App\Actions\Journeys\BuildJourneyState;
use App\Actions\Journeys\StorePushSubscription;
use App\Data\GeographicCoverage;
use App\Enums\AlertKind;
use App\Enums\AlertStatus;
use App\Http\Requests\PushSubscriptionRequest;
use App\Models\JourneyAlert;
use App\Models\JourneyParticipant;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seguimiento para contactos sin cuenta: el enlace privado es la única credencial.
 */
class TrackingController extends Controller
{
    public function page(string $token, GeographicCoverage $coverage): Response
    {
        return Inertia::render('seguimiento', [
            'token' => $token,
            'mapConfiguration' => $coverage->mapConfiguration(),
            'vapidPublicKey' => config('safety.webpush.public_key'),
        ]);
    }

    public function state(string $token, BuildJourneyState $state): JsonResponse
    {
        $participant = $this->participant($token);

        if ($participant->accepted_at === null && $participant->allowsTracking($token)) {
            $participant->accepted_at = now();
            $participant->save();
        }

        return response()->json(['data' => $state->forParticipant($participant)]);
    }

    public function acknowledge(string $token, BuildJourneyState $state): JsonResponse
    {
        $participant = $this->participant($token);
        abort_unless($participant->allowsTracking($token), 410, 'Este seguimiento ya no está disponible.');

        JourneyAlert::query()->where('journey_id', $participant->journey_id)
            ->where('kind', AlertKind::Inactivity->value)->where('status', AlertStatus::Escalated->value)
            ->update(['status' => AlertStatus::Acknowledged->value, 'acknowledged_at' => now()->format('Y-m-d H:i:s.uP')]);

        return response()->json(['data' => $state->forParticipant($participant)]);
    }

    public function subscribe(string $token, PushSubscriptionRequest $request, StorePushSubscription $store): JsonResponse
    {
        $participant = $this->participant($token);
        abort_unless($participant->allowsTracking($token), 410, 'Este seguimiento ya no está disponible.');
        $store->handle($request->validated(), null, $participant);

        return response()->json(['message' => 'Avisos activados en este dispositivo.', 'participant' => $participant->id], 201);
    }

    public function unsubscribe(string $token, PushSubscriptionRequest $request): JsonResponse
    {
        $participant = $this->participant($token);
        PushSubscription::query()->where('endpoint_hash', hash('sha256', $request->string('endpoint')->toString()))
            ->where('journey_participant_id', $participant->id)->delete();

        return response()->json(['message' => 'Avisos desactivados en este dispositivo.']);
    }

    private function participant(string $token): JourneyParticipant
    {
        return JourneyParticipant::query()->where('access_token_hash', hash('sha256', $token))->firstOr(fn () => abort(404, 'Este enlace no es válido.'));
    }
}
