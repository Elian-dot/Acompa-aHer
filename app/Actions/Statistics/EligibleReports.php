<?php

namespace App\Actions\Statistics;

use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro común de los resultados públicos: solo reportes verificados dentro de la
 * cobertura municipal (misma elegibilidad que el mapa de calor, invariante I09).
 */
class EligibleReports
{
    /** @return Builder<Report> */
    public function between(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Report::query()->verified()
            ->whereBetween('occurred_at', [$from->format('Y-m-d H:i:s.uP'), $to->format('Y-m-d H:i:s.uP')])
            ->whereRaw('EXISTS (SELECT 1 FROM zones WHERE code = ? AND ST_Covers(boundary, reports.location))', [config('safety.coverage_code')]);
    }
}
