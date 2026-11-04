<?php

namespace App\Actions\Journeys;

use App\Enums\AlertStatus;
use App\Models\Journey;
use App\Models\JourneyAlert;

class ResolveJourneyAlerts
{
    /**
     * Cierra las alertas abiertas: la usuaria volvió a estar activa o terminó el trayecto.
     */
    public function handle(Journey $journey): int
    {
        return JourneyAlert::query()
            ->where('journey_id', $journey->id)
            ->whereIn('status', [AlertStatus::Preliminary->value, AlertStatus::Escalated->value, AlertStatus::Acknowledged->value])
            ->update(['status' => AlertStatus::Resolved->value, 'resolved_at' => now()->format('Y-m-d H:i:s.uP')]);
    }
}
