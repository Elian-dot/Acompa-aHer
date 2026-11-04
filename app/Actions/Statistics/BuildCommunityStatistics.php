<?php

namespace App\Actions\Statistics;

use Carbon\CarbonImmutable;

class BuildCommunityStatistics
{
    private const MINIMUM_SAMPLE = 30;

    public function __construct(private EligibleReports $eligible) {}

    /**
     * Agregados anónimos de reportes verificados. Los grupos menores al umbral se
     * agrupan u ocultan para que no permitan identificar a una persona.
     *
     * @return array<string, mixed>
     */
    public function handle(int $periodDays): array
    {
        $now = now();
        $minimum = max(5, config()->integer('safety.heatmap_minimum_reports'));
        $from = $now->subDays($periodDays);

        $total = (int) $this->eligible->between($from, $now)->count();
        $previous = (int) $this->eligible->between($from->subDays($periodDays), $from)->count();
        $available = $total >= self::MINIMUM_SAMPLE;

        return [
            'totals' => [
                'verified' => $total,
                'previous_period' => $previous >= $minimum ? $previous : null,
                'change_percent' => $available && $previous >= $minimum ? (int) round(($total - $previous) / $previous * 100) : null,
            ],
            'categories' => $available ? $this->categories($from, $now, $minimum) : [],
            'months' => $available ? $this->months($now, $minimum) : [],
            'meta' => [
                'status' => $available ? 'available' : 'insufficient_data',
                'sample_size' => $total,
                'minimum_sample' => self::MINIMUM_SAMPLE,
                'minimum_group' => $minimum,
                'period_days' => $periodDays,
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
                'timezone' => 'America/Bogota',
                'source' => 'Reportes verificados en AcompañaHer',
                'zone' => 'Girardot (municipio)',
                'notice' => 'Cuenta reportes verificados de la comunidad, no todos los hechos ocurridos. Los grupos con menos de '.$minimum.' reportes se agrupan u ocultan. No incluye identidad de autoras ni trazas de acompañamiento.',
            ],
        ];
    }

    /**
     * @return list<array{code: string, name: string, count: int|null, grouped: bool}>
     */
    private function categories($from, $now, int $minimum): array
    {
        $rows = $this->eligible->between($from, $now)
            ->join('report_categories', 'report_categories.id', '=', 'reports.report_category_id')
            ->selectRaw('report_categories.code AS code, report_categories.name AS name, count(*) AS total')
            ->groupBy('report_categories.code', 'report_categories.name')->orderByDesc('total')->toBase()->get();

        $shown = [];
        $groupedTotal = 0;

        foreach ($rows as $row) {
            if ((int) $row->total >= $minimum) {
                $shown[] = ['code' => $row->code, 'name' => $row->name, 'count' => (int) $row->total, 'grouped' => false];
            } else {
                $groupedTotal += (int) $row->total;
            }
        }

        if ($groupedTotal > 0) {
            $shown[] = ['code' => 'grouped', 'name' => 'Otras categorías (agrupadas)', 'count' => $groupedTotal >= $minimum ? $groupedTotal : null, 'grouped' => true];
        }

        return $shown;
    }

    /**
     * Últimos seis meses calendario en hora local, con el mes en curso incluido.
     *
     * @return list<array{month: string, label: string, count: int|null, partial: bool}>
     */
    private function months($now, int $minimum): array
    {
        $start = CarbonImmutable::parse($now->setTimezone('America/Bogota')->startOfMonth()->subMonths(5)->toDateTimeString(), 'America/Bogota');

        $counts = $this->eligible->between($start->utc(), $now)
            ->selectRaw("to_char(occurred_at AT TIME ZONE 'America/Bogota', 'YYYY-MM') AS month, count(*) AS total")
            ->groupBy('month')->toBase()->get()->pluck('total', 'month');

        $labels = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $months = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $start->addMonths($offset);
            $key = $month->format('Y-m');
            $count = (int) ($counts[$key] ?? 0);
            $months[] = [
                'month' => $key,
                'label' => $labels[$month->month - 1].' '.$month->format('y'),
                'count' => $count >= $minimum ? $count : null,
                'partial' => $key === $now->setTimezone('America/Bogota')->format('Y-m'),
            ];
        }

        return $months;
    }
}
