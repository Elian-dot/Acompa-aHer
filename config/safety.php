<?php

return [
    'inactivity_seconds' => (int) env('JOURNEY_INACTIVITY_SECONDS', 900),
    'escalation_delay_seconds' => (int) env('JOURNEY_ESCALATION_DELAY_SECONDS', 120),
    'coverage_code' => '25307',
    'coverage_source' => 'OpenStreetMap · límite municipal de Girardot',
    'coverage_source_url' => 'https://www.openstreetmap.org/relation/1353143',
    'coverage_retrieved_at' => '2026-11-04',
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'heatmap_cell_meters' => 500,
    'heatmap_minimum_reports' => 5,
    'night_start_hour' => (int) env('HEATMAP_NIGHT_START_HOUR', 20),
    'night_end_hour' => (int) env('HEATMAP_NIGHT_END_HOUR', 2),
    'routing_url' => env('WALKING_ROUTER_URL', 'https://routing.openstreetmap.de/routed-foot'),
    'routing_provider' => env('WALKING_ROUTER_NAME', 'FOSSGIS · OpenStreetMap'),
    'discard_reasons' => [
        'insufficient_information' => 'Información insuficiente para verificar',
        'outside_coverage' => 'Fuera de cobertura',
        'identifying_content' => 'Contiene datos que identifican a una persona',
        'duplicate' => 'Reporte duplicado',
    ],
];
