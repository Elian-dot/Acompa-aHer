<?php

namespace App\Actions\Statistics;

use Illuminate\Support\Collection;

class BuildHourlyIncidence
{
    private const PERIOD_DAYS = 90;

    private const MINIMUM_SAMPLE = 30;

    public function __construct(private EligibleReports $eligible) {}

    /**
     * Distribución horaria de reportes verificados. Mide cuándo se reportan incidentes,
     * no cuánta gente circula: no debe presentarse como flujo peatonal ni como seguridad.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $now = now();
        $minimum = max(5, config()->integer('safety.heatmap_minimum_reports'));

        $counts = $this->eligible->between($now->subDays(self::PERIOD_DAYS), $now)
            ->selectRaw("EXTRACT(HOUR FROM occurred_at AT TIME ZONE 'America/Bogota')::int AS local_hour, count(*) AS total")
            ->groupBy('local_hour')->toBase()->get()->pluck('total', 'local_hour');

        $total = (int) $counts->sum();
        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $count = (int) ($counts[$hour] ?? 0);
            $hours[] = ['hour' => $hour, 'count' => $count >= $minimum ? $count : null];
        }

        $available = $total >= self::MINIMUM_SAMPLE;
        $visible = collect($hours)->filter(fn (array $item): bool => $item['count'] !== null);

        return [
            'hours' => $available ? $hours : [],
            'bands' => $available ? $this->bands($counts, $minimum) : [],
            'highest_hours' => $available ? $visible->sortByDesc('count')->take(3)->pluck('hour')->sort()->values()->all() : [],
            'lowest_ranges' => $available ? $this->ranges(collect($hours)->filter(fn (array $item): bool => $item['count'] === null)->pluck('hour')->all()) : [],
            'meta' => [
                'status' => $available ? 'available' : 'insufficient_data',
                'sample_size' => $total,
                'minimum_sample' => self::MINIMUM_SAMPLE,
                'minimum_group' => $minimum,
                'confidence' => match (true) {
                    $total < self::MINIMUM_SAMPLE => 'insufficient',
                    $total < 100 => 'low',
                    $total < 300 => 'medium',
                    default => 'high',
                },
                'period_days' => self::PERIOD_DAYS,
                'from' => $now->subDays(self::PERIOD_DAYS)->toIso8601String(),
                'to' => $now->toIso8601String(),
                'timezone' => 'America/Bogota',
                'source' => 'Reportes verificados en AcompañaHer',
                'notice' => 'Muestra cuándo ocurrieron los incidentes reportados y verificados. No mide cuánta gente circula ni garantiza que una franja sea segura: pocos reportes también pueden significar pocas personas o subregistro.',
            ],
        ];
    }

    /**
     * @param  Collection<int, int|string>  $counts
     * @return list<array{id: string, label: string, from: int, to: int, count: int|null}>
     */
    private function bands($counts, int $minimum): array
    {
        $bands = [
            ['id' => 'dia', 'label' => 'Día', 'from' => 5, 'to' => 17],
            ['id' => 'tarde', 'label' => 'Tarde-noche', 'from' => 18, 'to' => 19],
            ['id' => 'noche', 'label' => 'Noche', 'from' => 20, 'to' => 23],
            ['id' => 'madrugada', 'label' => 'Madrugada', 'from' => 0, 'to' => 4],
        ];

        return array_map(function (array $band) use ($counts, $minimum): array {
            $sum = 0;
            for ($hour = $band['from']; $hour <= $band['to']; $hour++) {
                $sum += (int) ($counts[$hour] ?? 0);
            }

            return [...$band, 'count' => $sum >= $minimum ? $sum : null];
        }, $bands);
    }

    /**
     * @param  list<int>  $hours
     * @return list<array{from: int, to: int}>
     */
    private function ranges(array $hours): array
    {
        sort($hours);
        $ranges = [];

        foreach ($hours as $hour) {
            $last = array_key_last($ranges);
            if ($last !== null && $ranges[$last]['to'] === $hour - 1) {
                $ranges[$last]['to'] = $hour;
            } else {
                $ranges[] = ['from' => $hour, 'to' => $hour];
            }
        }

        return $ranges;
    }
}
