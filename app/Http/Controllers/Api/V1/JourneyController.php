<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Journeys\BuildJourneyState;
use App\Actions\Journeys\ConfirmJourneyActivity;
use App\Actions\Journeys\FinishJourney;
use App\Actions\Journeys\RecordJourneyLocation;
use App\Actions\Journeys\StartJourney;
use App\Enums\JourneyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmActivityRequest;
use App\Http\Requests\FinishJourneyRequest;
use App\Http\Requests\RecordLocationRequest;
use App\Http\Requests\StartJourneyRequest;
use App\Models\Journey;
use App\Models\JourneyParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JourneyController extends Controller
{
    public function __construct(private BuildJourneyState $state) {}

    public function current(Request $request): JsonResponse
    {
        Gate::authorize('start', Journey::class);
        $journey = $request->user()->journeys()->where('status', JourneyStatus::Active->value)->latest('started_at')->first();

        return response()->json(['data' => $journey ? $this->state->forOwner($journey) : null]);
    }

    public function store(StartJourneyRequest $request, StartJourney $start): JsonResponse
    {
        $result = $start->handle($request->user(), $request->validated());
        $journey = $result['journey'];

        $share = JourneyParticipant::query()->where('journey_id', $journey->id)->with('emergencyContact')->orderBy('id')->get()
            ->map(fn (JourneyParticipant $participant): array => self::shareEntry($participant, $result['tokens'][$participant->id]))->all();

        return response()->json([
            'data' => $this->state->forOwner($journey),
            'share' => $share,
            'notice' => 'Envía cada enlace desde tu dispositivo. Solo quien lo reciba podrá ver tu ubicación mientras dure el acompañamiento.',
        ], 201);
    }

    public function location(RecordLocationRequest $request, Journey $journey, RecordJourneyLocation $record): JsonResponse
    {
        Gate::authorize('update', $journey);
        $counted = $record->handle($journey, $request->sample());

        return response()->json(['data' => $this->state->forOwner($journey), 'meta' => ['counted_as_activity' => $counted]]);
    }

    public function activity(ConfirmActivityRequest $request, Journey $journey, ConfirmJourneyActivity $confirm): JsonResponse
    {
        Gate::authorize('update', $journey);
        $confirm->handle($journey, $request->filled('pause_minutes') ? $request->integer('pause_minutes') : null);

        return response()->json(['data' => $this->state->forOwner($journey)]);
    }

    public function finish(FinishJourneyRequest $request, Journey $journey, FinishJourney $finish): JsonResponse
    {
        Gate::authorize('update', $journey);
        $closed = $finish->handle($journey, JourneyStatus::from($request->string('outcome')->toString()));

        return response()->json(['data' => $this->state->forOwner($closed)]);
    }

    /** @return array<string, mixed> */
    public static function shareEntry(JourneyParticipant $participant, string $token): array
    {
        $url = route('tracking.page', ['token' => $token]);
        $message = 'Te comparto mi trayecto en AcompañaHer para que me acompañes. Abre este enlace: '.$url;

        return [
            'participant_id' => $participant->id,
            'name' => $participant->emergencyContact->name,
            'tracking_url' => $url,
            'sms_url' => 'sms:'.$participant->emergencyContact->phone.'?body='.rawurlencode($message),
        ];
    }
}
