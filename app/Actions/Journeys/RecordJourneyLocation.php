<?php

namespace App\Actions\Journeys;

use App\Models\Journey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordJourneyLocation
{
    public function __construct(private ResolveJourneyAlerts $resolveAlerts) {}

    /**
     * Guarda una muestra y decide si cuenta como actividad. El reloj del servidor
     * gobierna los plazos; la hora del dispositivo solo ordena las muestras.
     *
     * @param  array{latitude: float, longitude: float, accuracy_meters: float, recorded_at: CarbonImmutable}  $sample
     */
    public function handle(Journey $journey, array $sample): bool
    {
        return DB::transaction(function () use ($journey, $sample): bool {
            $locked = Journey::query()->whereKey($journey->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['journey' => 'Este acompañamiento ya terminó.']);
            }

            $now = now();
            $recordedAt = $sample['recorded_at']->lessThan($locked->started_at) ? $locked->started_at : $sample['recorded_at'];
            $point = sprintf('ST_SetSRID(ST_MakePoint(%.7F, %.7F), 4326)', $sample['longitude'], $sample['latitude']);

            DB::table('journey_locations')->insertOrIgnore([
                'journey_id' => $locked->id,
                'position' => DB::raw($point),
                'accuracy_meters' => round($sample['accuracy_meters'], 2),
                'recorded_at' => $recordedAt->utc()->format('Y-m-d H:i:s.uP'),
            ]);

            if ($sample['accuracy_meters'] > config()->integer('safety.max_accuracy_meters')) {
                return false;
            }

            $threshold = max((float) config()->integer('safety.movement_meters'), $sample['accuracy_meters']);
            $stamp = $now->utc()->format('Y-m-d H:i:s.uP');
            $moved = DB::update(
                "UPDATE journeys SET last_activity_at = GREATEST(last_activity_at, ?), activity_anchor = $point, inactivity_paused_until = NULL, updated_at = ?
                 WHERE id = ? AND status = 'active' AND ST_Distance(activity_anchor::geography, $point::geography) >= ?",
                [$stamp, $stamp, $locked->id, $threshold],
            ) > 0;

            if ($moved) {
                $this->resolveAlerts->handle($locked);
            }

            return $moved;
        });
    }
}
