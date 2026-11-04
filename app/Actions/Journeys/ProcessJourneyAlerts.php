<?php

namespace App\Actions\Journeys;

use App\Enums\AlertKind;
use App\Enums\AlertStage;
use App\Enums\AlertStatus;
use App\Enums\DeliveryChannel;
use App\Enums\JourneyStatus;
use App\Jobs\DeliverAlertNotification;
use App\Models\AlertDelivery;
use App\Models\Journey;
use App\Models\JourneyAlert;
use App\Models\JourneyParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProcessJourneyAlerts
{
    private const BATCH = 100;

    public function __construct(private FinishJourney $finishJourney) {}

    /**
     * Cierra trayectos abandonados para que empiece su ventana de eliminación.
     */
    public function closeAbandoned(): int
    {
        $limit = now()->subHours(config()->integer('safety.journey_max_hours'))->format('Y-m-d H:i:s.uP');

        $journeys = Journey::query()->where('status', JourneyStatus::Active->value)
            ->where('started_at', '<=', $limit)->orderBy('id')->limit(self::BATCH)->get();

        foreach ($journeys as $journey) {
            $this->finishJourney->handle($journey, JourneyStatus::Cancelled);
        }

        return $journeys->count();
    }

    /**
     * Crea la alerta preliminar de los trayectos sin actividad válida durante su plazo.
     */
    public function raisePreliminaryAlerts(): int
    {
        $ids = $this->overdueJourneys()->orderBy('journeys.id')->limit(self::BATCH)->pluck('journeys.id');
        $raised = 0;

        foreach ($ids as $id) {
            try {
                $raised += DB::transaction(function () use ($id): int {
                    $journey = $this->overdueJourneys()->where('journeys.id', $id)->lockForUpdate()->first();

                    if ($journey === null) {
                        return 0;
                    }

                    $now = now();
                    $alert = new JourneyAlert;
                    $alert->forceFill([
                        'journey_id' => $journey->id,
                        'kind' => AlertKind::Inactivity,
                        'status' => AlertStatus::Preliminary,
                        'inactivity_started_at' => $journey->last_activity_at,
                        'triggered_at' => $now,
                        'escalation_due_at' => $now->addSeconds($journey->escalation_delay_seconds),
                    ])->save();

                    $this->enqueue($alert, userId: $journey->user_id, participantId: null, stage: AlertStage::Preliminary);

                    return 1;
                });
            } catch (UniqueConstraintViolationException) {
                // Otro proceso ya creó la alerta de este ciclo de inactividad.
            }
        }

        return $raised;
    }

    /**
     * Escala las alertas preliminares cuya espera terminó sin que la usuaria respondiera.
     */
    public function escalateDueAlerts(): int
    {
        $ids = JourneyAlert::query()
            ->where('kind', AlertKind::Inactivity->value)->where('status', AlertStatus::Preliminary->value)
            ->where('escalation_due_at', '<=', now()->format('Y-m-d H:i:s.uP'))
            ->orderBy('id')->limit(self::BATCH)->pluck('id');
        $escalated = 0;

        foreach ($ids as $id) {
            $escalated += DB::transaction(function () use ($id): int {
                $alert = JourneyAlert::query()->whereKey($id)->lockForUpdate()->first();
                $journey = $alert ? Journey::query()->whereKey($alert->journey_id)->lockForUpdate()->first() : null;

                if ($alert === null || $journey === null || $alert->status !== AlertStatus::Preliminary || ! $journey->isActive()) {
                    return 0;
                }

                $alert->status = AlertStatus::Escalated;
                $alert->save();

                $this->enqueue($alert, userId: $journey->user_id, participantId: null, stage: AlertStage::Escalated);

                $participants = JourneyParticipant::query()->where('journey_id', $journey->id)
                    ->whereNull('revoked_at')->where('expires_at', '>', now()->format('Y-m-d H:i:s.uP'))->orderBy('id')->get();

                foreach ($participants as $participant) {
                    $this->enqueue($alert, userId: null, participantId: $participant->id, stage: AlertStage::Escalated);
                }

                return 1;
            });
        }

        return $escalated;
    }

    /**
     * Trayectos activos cuyo plazo de inactividad venció y sin alerta en este ciclo.
     *
     * @return Builder<Journey>
     */
    private function overdueJourneys(): Builder
    {
        return Journey::query()->where('journeys.status', JourneyStatus::Active->value)
            ->whereRaw("GREATEST(journeys.last_activity_at, COALESCE(journeys.inactivity_paused_until, journeys.last_activity_at)) + (journeys.inactivity_seconds * interval '1 second') <= ?", [now()->format('Y-m-d H:i:s.uP')])
            ->whereDoesntHave('alerts', fn ($alerts) => $alerts->where('kind', AlertKind::Inactivity->value)
                ->whereColumn('journey_alerts.inactivity_started_at', 'journeys.last_activity_at'));
    }

    private function enqueue(JourneyAlert $alert, ?int $userId, ?int $participantId, AlertStage $stage): void
    {
        $delivery = new AlertDelivery;
        $delivery->forceFill([
            'journey_alert_id' => $alert->id,
            'user_id' => $userId,
            'journey_participant_id' => $participantId,
            'channel' => DeliveryChannel::Push,
            'stage' => $stage,
        ])->save();

        DeliverAlertNotification::dispatch($delivery->id)->afterCommit();
    }
}
