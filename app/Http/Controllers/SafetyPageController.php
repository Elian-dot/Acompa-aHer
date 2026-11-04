<?php

namespace App\Http\Controllers;

use App\Data\GeographicCoverage;
use App\Models\EmergencyContact;
use App\Models\Report;
use App\Models\ReportCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SafetyPageController extends Controller
{
    public function map(Request $request, GeographicCoverage $coverage): Response
    {
        return Inertia::render($request->routeIs('public.*') ? 'public/mapa' : 'mapa', [
            ...$this->mapConfiguration($coverage),
            'categories' => $this->categories(),
        ]);
    }

    public function routes(Request $request, GeographicCoverage $coverage): Response
    {
        return Inertia::render($request->routeIs('public.*') ? 'public/rutas' : 'rutas', [
            ...$this->mapConfiguration($coverage),
            'routingProvider' => config('safety.routing_provider'),
        ]);
    }

    public function reports(Request $request, GeographicCoverage $coverage): Response
    {
        return Inertia::render('reportes', [
            ...$this->mapConfiguration($coverage),
            'categories' => $this->categories(),
            'canReport' => $request->user()->can('create', Report::class),
        ]);
    }

    public function sos(Request $request): Response
    {
        return Inertia::render('sos', [
            'canManageContacts' => $request->user()->can('viewAny', EmergencyContact::class),
        ]);
    }

    public function contacts(): Response
    {
        Gate::authorize('viewAny', EmergencyContact::class);

        return Inertia::render('contactos');
    }

    public function moderation(): Response
    {
        Gate::authorize('moderate', Report::class);

        return Inertia::render('moderacion', ['discardReasons' => config('safety.discard_reasons')]);
    }

    /** @return array<string, mixed> */
    private function mapConfiguration(GeographicCoverage $coverage): array
    {
        return [
            'mapConfiguration' => [
                'center' => [4.303, -74.8039], 'zoom' => 14,
                'tileUrl' => config('safety.tile_url'),
                'coverageAvailable' => $coverage->isAvailable(),
                'coverage' => $coverage->featureCollection(),
                'coverageSource' => config('safety.coverage_source'),
                'coverageSourceUrl' => config('safety.coverage_source_url'),
                'coverageRetrievedAt' => config('safety.coverage_retrieved_at'),
            ],
        ];
    }

    /** @return array<int, array{id: int, code: string, name: string}> */
    private function categories(): array
    {
        return ReportCategory::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name'])->toArray();
    }
}
