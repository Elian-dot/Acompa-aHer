<?php

namespace App\Actions\Journeys;

use App\Enums\AlertStatus;
use App\Models\AlertDelivery;
use App\Models\Journey;
use App\Models\JourneyAlert;
use App\Models\JourneyLocation;
use App\Models\JourneyParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class BuildJourneyState
{
    /**
     * Estado completo para la propietaria del trayecto.
     *
     * @return array<string, mixed>
     */
    public function forOwner(Journey $journey): array
    {
        $row = $this->journeyRow($journey);
        $dueAt = $this->inactivityDueAt($row);
        $alert = $this->openAlert($row);

        return [
            'id' => $row->id,
            'status' => $row->status->value,
            'started_at' => $row->started_at->toIso8601String(),
            'ended_at' => $row->ended_at?->toIso8601String(),
            'last_activity_at' => $row->last_activity_at->toIso8601String(),
            'paused_until' => $row->inactivity_paused_until?->isFuture() ? $row->inactivity_paused_until->toIso8601String() : null,
            'inactivity_seconds' => $row->inactivity_seconds,
            'escalation_delay_seconds' => $row->escalation_delay_seconds,
            'inactivity_due_at' => $dueAt->toIso8601String(),
            'origin' => $this->point($row, 'origin'),
            'destination' => $this->point($row, 'destination'),
            'route' => $row->getAttribute('route_json') ? json_decode($row->getAttribute('route_json'), true, flags: JSON_THROW_ON_ERROR) : null,
            'location' => $this->latestLocation($row),
            'alert' => $alert ? $this->alertState($alert) : null,
            'participants' => $row->participants()->with('emergencyContact')->withCount('pushSubscriptions')->orderBy('id')
                ->get()->map(fn (JourneyParticipant $participant): array => [
                    'id' => $participant->id,
                    'name' => $participant->emergencyContact->name,
                    'status' => $this->participantStatus($participant),
                    'opened_at' => $participant->accepted_at?->toIso8601String(),
                    'expires_at' => $participant->expires_at->toIso8601String(),
                    'push_enabled' => $participant->push_subscriptions_count > 0,
                ])->all(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Vista mínima para un contacto con enlace: sin teléfono ni datos de la cuenta.
     *
     * @return array<string, mixed>
     */
    public function forParticipant(JourneyParticipant $participant): array
    {
        $journey = $participant->journey()->with('user')->firstOrFail();
        $base = [
            'owner_name' => Str::before($journey->user->name, ' '),
            'server_time' => now()->toIso8601String(),
        ];

        $state = match (true) {
            $participant->revoked_at !== null => 'revoked',
            $participant->expires_at->isPast() => 'expired',
            ! $journey->isActive() => 'ended',
            default => 'active',
        };

        if ($state !== 'active') {
            return [...$base, 'state' => $state];
        }

        $row = $this->journeyRow($journey);
        $alert = $this->openAlert($row);

        return [
            ...$base,
            'state' => 'active',
            'started_at' => $row->started_at->toIso8601String(),
            'expires_at' => $participant->expires_at->toIso8601String(),
            'paused_until' => $row->inactivity_paused_until?->isFuture() ? $row->inactivity_paused_until->toIso8601String() : null,
            'origin' => $this->point($row, 'origin'),
            'destination' => $this->point($row, 'destination'),
            'route' => $row->getAttribute('route_json') ? json_decode($row->getAttribute('route_json'), true, flags: JSON_THROW_ON_ERROR) : null,
            'location' => $this->latestLocation($row),
            'alert' => $alert && $alert->status !== AlertStatus::Preliminary ? [
                'status' => $alert->status->value,
                'triggered_at' => $alert->triggered_at->toIso8601String(),
                'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            ] : null,
            'push_enabled' => $participant->pushSubscriptions()->exists(),
        ];
    }

    private function journeyRow(Journey $journey): Journey
    {
        return Journey::query()->whereKey($journey->id)
            ->selectRaw('journeys.*, ST_X(origin) AS origin_lng, ST_Y(origin) AS origin_lat, ST_X(destination) AS destination_lng, ST_Y(destination) AS destination_lat, ST_AsGeoJSON(route) AS route_json')
            ->firstOrFail();
    }

    /** @return array{latitude: float, longitude: float} */
    private function point(Journey $row, string $prefix): array
    {
        return ['latitude' => (float) $row->getAttribute($prefix.'_lat'), 'longitude' => (float) $row->getAttribute($prefix.'_lng')];
    }

    /** @return array{latitude: float, longitude: float, accuracy_meters: float, recorded_at: string}|null */
    private function latestLocation(Journey $row): ?array
    {
        $location = JourneyLocation::query()->where('journey_id', $row->id)->orderByDesc('recorded_at')
            ->selectRaw('ST_X(position) AS lng, ST_Y(position) AS lat, accuracy_meters, recorded_at')->first();

        return $location ? [
            'latitude' => (float) $location->getAttribute('lat'),
            'longitude' => (float) $location->getAttribute('lng'),
            'accuracy_meters' => (float) $location->accuracy_meters,
            'recorded_at' => $location->recorded_at->toIso8601String(),
        ] : null;
    }

    private function inactivityDueAt(Journey $row): CarbonImmutable
    {
        $start = $row->inactivity_paused_until && $row->inactivity_paused_until->greaterThan($row->last_activity_at)
            ? $row->inactivity_paused_until : $row->last_activity_at;

        return $start->addSeconds($row->inactivity_seconds);
    }

    private function openAlert(Journey $row): ?JourneyAlert
    {
        return JourneyAlert::query()->where('journey_id', $row->id)
            ->whereIn('status', [AlertStatus::Preliminary->value, AlertStatus::Escalated->value, AlertStatus::Acknowledged->value])
            ->orderByDesc('triggered_at')->first();
    }

    /** @return array<string, mixed> */
    private function alertState(JourneyAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'status' => $alert->status->value,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'escalation_due_at' => $alert->escalation_due_at?->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'deliveries' => $alert->deliveries()->with('journeyParticipant.emergencyContact')->orderBy('id')->get()
                ->map(fn (AlertDelivery $delivery): array => [
                    'recipient' => $delivery->journeyParticipant?->emergencyContact->name ?? 'Tú',
                    'is_owner' => $delivery->user_id !== null,
                    'stage' => $delivery->stage->value,
                    'status' => $delivery->status->value,
                    'failure_code' => $delivery->failure_code,
                    'attempts' => $delivery->attempts,
                    'sent_at' => $delivery->sent_at?->toIso8601String(),
                ])->all(),
        ];
    }

    private function participantStatus(JourneyParticipant $participant): string
    {
        return match (true) {
            $participant->revoked_at !== null => 'revoked',
            $participant->expires_at->isPast() => 'expired',
            $participant->accepted_at !== null => 'opened',
            default => 'pending',
        };
    }
}
