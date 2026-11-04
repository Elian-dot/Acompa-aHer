<?php

namespace App\Actions\Maps;

use App\Models\Report;
use Illuminate\Support\Facades\DB;

class BuildHeatmap
{
    /** @return array<string, mixed> */
    public function handle(?int $categoryId = null): array
    {
        $now = now();
        $cellSize = max(500, config()->integer('safety.heatmap_cell_meters'));
        $minimum = max(5, config()->integer('safety.heatmap_minimum_reports'));
        $nightStart = config()->integer('safety.night_start_hour');
        $nightEnd = config()->integer('safety.night_end_hour');
        $localHour = "EXTRACT(HOUR FROM occurred_at AT TIME ZONE 'America/Bogota')";
        $nightOperator = $nightStart >= $nightEnd ? 'OR' : 'AND';

        $cells = Report::query()->verified()
            ->whereBetween('occurred_at', [$now->subDays(30)->format('Y-m-d H:i:s.uP'), $now->format('Y-m-d H:i:s.uP')])
            ->whereRaw("($localHour >= ? $nightOperator $localHour < ?)", [$nightStart, $nightEnd])
            ->whereRaw('EXISTS (SELECT 1 FROM zones WHERE code = ? AND ST_Covers(boundary, reports.location))', [config('safety.coverage_code')])
            ->when($categoryId !== null, fn ($query) => $query->where('report_category_id', $categoryId))
            ->selectRaw('floor(ST_X(ST_Transform(location, 3857)) / ?) AS cell_x, floor(ST_Y(ST_Transform(location, 3857)) / ?) AS cell_y, count(*) AS report_count', [$cellSize, $cellSize])
            ->groupBy('cell_x', 'cell_y')->havingRaw('count(*) >= ?', [$minimum])
            ->orderBy('cell_x')->orderBy('cell_y')->toBase();

        $cells = DB::query()->fromSub($cells, 'cells')->selectRaw('cell_x, cell_y, report_count, ST_AsGeoJSON(ST_Transform(ST_MakeEnvelope(cell_x * ?, cell_y * ?, (cell_x + 1) * ?, (cell_y + 1) * ?, 3857), 4326)) AS geometry', [$cellSize, $cellSize, $cellSize, $cellSize])->get();

        $features = $cells->map(function (object $cell): array {
            $count = (int) $cell->report_count;
            $lower = intdiv($count, 5) * 5;

            return [
                'type' => 'Feature',
                'geometry' => json_decode($cell->geometry, true, flags: JSON_THROW_ON_ERROR),
                'properties' => ['count_range' => $lower.'–'.($lower + 4), 'level' => match (true) {
                    $lower >= 30 => 'muy-alto', $lower >= 20 => 'alto', $lower >= 10 => 'medio', default => 'bajo',
                }],
            ];
        })->all();

        return [
            'type' => 'FeatureCollection', 'features' => $features,
            'meta' => [
                'period_days' => 30, 'from' => $now->subDays(30)->toIso8601String(), 'to' => $now->toIso8601String(),
                'timezone' => 'America/Bogota', 'night_start_hour' => $nightStart, 'night_end_hour' => $nightEnd,
                'minimum_reports' => $minimum, 'cell_meters' => $cellSize,
                'status' => $features === [] ? 'insufficient_data' : 'available',
                'notice' => 'Solo reportes verificados. Sin datos suficientes no significa ausencia de riesgo.',
            ],
        ];
    }
}
