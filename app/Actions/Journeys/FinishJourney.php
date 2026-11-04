<?php

namespace App\Actions\Journeys;

use App\Enums\JourneyStatus;
use App\Models\Journey;
use Illuminate\Support\Facades\DB;

class FinishJourney
{
    public function __construct(private ResolveJourneyAlerts $resolveAlerts) {}

    /**
     * Cierra el trayecto; desde ese instante empieza la ventana de 24 horas de retención.
     * Es idempotente: cerrar un trayecto ya cerrado no cambia nada.
     */
    public function handle(Journey $journey, JourneyStatus $outcome): Journey
    {
        return DB::transaction(function () use ($journey, $outcome): Journey {
            $locked = Journey::query()->whereKey($journey->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                return $locked;
            }

            $locked->status = $outcome;
            $locked->ended_at = now();
            $locked->save();

            $this->resolveAlerts->handle($locked);

            return $locked;
        });
    }
}
