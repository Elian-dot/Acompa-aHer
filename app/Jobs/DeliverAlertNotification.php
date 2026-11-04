<?php

namespace App\Jobs;

use App\Enums\AlertStage;
use App\Enums\AlertStatus;
use App\Enums\DeliveryStatus;
use App\Models\AlertDelivery;
use App\Services\WebPush\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class DeliverAlertNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(PushSender $sender): void
    {
        $delivery = AlertDelivery::query()
            ->with(['journeyAlert.journey.user.currentTeam', 'user', 'journeyParticipant.emergencyContact'])
            ->find($this->deliveryId);

        if ($delivery === null || $delivery->status === DeliveryStatus::Sent) {
            return;
        }

        $alert = $delivery->journeyAlert;
        $journey = $alert->journey;
        $participant = $delivery->journeyParticipant;
        $expectedStatus = $delivery->stage === AlertStage::Preliminary ? AlertStatus::Preliminary : AlertStatus::Escalated;

        // Revalida antes de enviar: la usuaria pudo responder o el trayecto cerrarse mientras la tarea esperaba.
        if ($alert->status !== $expectedStatus || ! $journey->isActive()) {
            $this->fail($delivery, $journey->isActive() ? 'alert_resolved' : 'journey_closed');

            return;
        }

        if ($participant !== null && ($participant->revoked_at !== null || $participant->expires_at->isPast())) {
            $this->fail($delivery, 'participant_inactive');

            return;
        }

        $subscriptions = $participant !== null
            ? $participant->pushSubscriptions()->get()
            : $delivery->user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            $this->fail($delivery, 'no_subscription');

            return;
        }

        $payload = $this->payload($delivery);
        $delivered = false;
        $transient = false;

        foreach ($subscriptions as $subscription) {
            $result = $sender->send($subscription, $payload);

            if ($result->successful) {
                $delivered = true;
            } elseif ($result->subscriptionExpired) {
                $subscription->delete();
            } elseif ($result->reason === 'push_not_configured') {
                $this->fail($delivery, 'push_not_configured');

                return;
            } else {
                $transient = true;
            }
        }

        $delivery->attempts++;
        $delivery->last_attempt_at = now();

        if ($delivered) {
            $delivery->status = DeliveryStatus::Sent;
            $delivery->sent_at = now();
            $delivery->failure_code = null;
            $delivery->save();

            return;
        }

        if ($transient && $this->attempts() < $this->tries) {
            $delivery->save();
            $this->release($this->attempts() * 30);

            return;
        }

        $delivery->status = DeliveryStatus::Failed;
        $delivery->failure_code = $transient ? 'push_error' : 'subscription_expired';
        $delivery->save();
    }

    private function fail(AlertDelivery $delivery, string $code): void
    {
        $delivery->status = DeliveryStatus::Failed;
        $delivery->failure_code = $code;
        $delivery->save();
    }

    /** @return array<string, mixed> */
    private function payload(AlertDelivery $delivery): array
    {
        $journey = $delivery->journeyAlert->journey;
        $owner = Str::before($journey->user->name, ' ');
        $minutes = (int) round($journey->inactivity_seconds / 60);

        if ($delivery->journeyParticipant !== null) {
            return [
                'title' => "{$owner} no ha respondido",
                'body' => "Lleva más de {$minutes} minutos sin moverse ni confirmar que está bien. Abre el seguimiento para ver su última ubicación.",
                'tag' => 'alert-'.$delivery->journey_alert_id,
                'participant' => $delivery->journey_participant_id,
                'url' => '/',
            ];
        }

        $slug = $journey->user->currentTeam?->slug;
        $wait = (int) round($journey->escalation_delay_seconds / 60);

        return [
            'title' => $delivery->stage === AlertStage::Preliminary ? '¿Estás bien?' : 'Avisamos a tus contactos',
            'body' => $delivery->stage === AlertStage::Preliminary
                ? "Llevas más de {$minutes} minutos sin moverte. Confirma que estás bien o avisaremos a tus contactos en {$wait} min."
                : 'No respondiste a tiempo, así que enviamos el aviso a tus contactos de confianza.',
            'tag' => 'alert-'.$delivery->journey_alert_id,
            'url' => $slug ? '/'.$slug.'/inactividad' : '/',
        ];
    }

    public function failed(): void
    {
        $delivery = AlertDelivery::query()->find($this->deliveryId);

        if ($delivery !== null && $delivery->status === DeliveryStatus::Pending) {
            $this->fail($delivery, 'push_error');
        }
    }
}
