<?php

namespace App\Actions\Journeys;

use App\Data\GeographicCoverage;
use App\Models\Journey;
use App\Models\JourneyParticipant;
use App\Models\User;
use App\Support\Geometry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartJourney
{
    public function __construct(private GeographicCoverage $coverage) {}

    /**
     * @param  array{origin: array{latitude: float, longitude: float}, destination: array{latitude: float, longitude: float}, route?: list<array{0: float, 1: float}>|null, contact_ids: list<int>, inactivity_minutes?: int|null, share_hours?: int|null}  $data
     * @return array{journey: Journey, tokens: array<int, string>} tokens indexados por id de participación; solo existen aquí
     */
    public function handle(User $user, array $data): array
    {
        $this->coverage->validatePoint((float) $data['origin']['longitude'], (float) $data['origin']['latitude'], 'origin');
        $this->coverage->validatePoint((float) $data['destination']['longitude'], (float) $data['destination']['latitude'], 'destination');

        return DB::transaction(function () use ($user, $data): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->journeys()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['journey' => 'Ya tienes un acompañamiento activo. Termínalo antes de iniciar otro.']);
            }

            $contacts = $user->emergencyContacts()->whereKey($data['contact_ids'])->orderBy('id')->get();
            if ($contacts->count() !== count($data['contact_ids'])) {
                throw ValidationException::withMessages(['contact_ids' => 'Algún contacto seleccionado ya no está disponible.']);
            }

            $inactivity = ($data['inactivity_minutes'] ?? null) !== null
                ? (int) $data['inactivity_minutes'] * 60
                : config()->integer('safety.inactivity_seconds');
            $hours = min(
                (int) ($data['share_hours'] ?? config()->integer('safety.share_default_hours')),
                config()->integer('safety.journey_max_hours'),
            );

            $journey = new Journey;
            $journey->user_id = $user->id;
            $journey->origin = Geometry::point((float) $data['origin']['latitude'], (float) $data['origin']['longitude']);
            $journey->activity_anchor = $journey->origin;
            $journey->destination = Geometry::point((float) $data['destination']['latitude'], (float) $data['destination']['longitude']);
            $journey->route = ! empty($data['route']) ? Geometry::line($data['route']) : null;
            $journey->inactivity_seconds = $inactivity;
            $journey->save();

            $tokens = [];
            foreach ($contacts as $contact) {
                $token = Str::random(48);
                $participant = new JourneyParticipant;
                $participant->forceFill([
                    'journey_id' => $journey->id,
                    'emergency_contact_id' => $contact->id,
                    'access_token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addHours($hours),
                ])->save();
                $tokens[$participant->id] = $token;
            }

            return ['journey' => $journey, 'tokens' => $tokens];
        });
    }
}
