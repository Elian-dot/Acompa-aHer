<?php

namespace App\Actions\Journeys;

use App\Models\Journey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmJourneyActivity
{
    public function __construct(private ResolveJourneyAlerts $resolveAlerts) {}

    /**
     * Confirmación manual («Estoy bien») y pausa voluntaria opcional.
     */
    public function handle(Journey $journey, ?int $pauseMinutes = null): void
    {
        DB::transaction(function () use ($journey, $pauseMinutes): void {
            $locked = Journey::query()->whereKey($journey->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['journey' => 'Este acompañamiento ya terminó.']);
            }

            $now = now();
            $stamp = $now->utc()->format('Y-m-d H:i:s.uP');
            $pausedUntil = $pauseMinutes ? $now->addMinutes($pauseMinutes)->utc()->format('Y-m-d H:i:s.uP') : null;

            DB::update(
                "UPDATE journeys SET last_activity_at = GREATEST(last_activity_at, ?), inactivity_paused_until = ?, updated_at = ?,
                    activity_anchor = COALESCE((SELECT position FROM journey_locations WHERE journey_id = journeys.id ORDER BY recorded_at DESC LIMIT 1), activity_anchor)
                 WHERE id = ? AND status = 'active'",
                [$stamp, $pausedUntil, $stamp, $locked->id],
            );

            $this->resolveAlerts->handle($locked);
        });
    }
}
