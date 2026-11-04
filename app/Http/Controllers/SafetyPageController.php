<?php

namespace App\Http\Controllers;

use App\Data\GeographicCoverage;
use App\Models\EmergencyContact;
use App\Models\Journey;
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

    public function journey(Request $request, GeographicCoverage $coverage): Response
    {
        return Inertia::render('acompanamiento', [
            ...$this->mapConfiguration($coverage),
            'canManageContacts' => $request->user()->can('viewAny', EmergencyContact::class),
            'canStartJourney' => $request->user()->can('start', Journey::class),
            'vapidPublicKey' => config('safety.webpush.public_key'),
            'limits' => [
                'minInactivityMinutes' => config()->integer('safety.min_inactivity_seconds') / 60,
                'maxInactivityMinutes' => config()->integer('safety.max_inactivity_seconds') / 60,
                'defaultInactivityMinutes' => config()->integer('safety.inactivity_seconds') / 60,
                'maxPauseMinutes' => config()->integer('safety.pause_max_minutes'),
                'maxShareHours' => min(config()->integer('safety.share_max_hours'), config()->integer('safety.journey_max_hours')),
            ],
        ]);
    }

    public function alerts(Request $request): Response
    {
        return Inertia::render('inactividad', [
            'canStartJourney' => $request->user()->can('start', Journey::class),
            'vapidPublicKey' => config('safety.webpush.public_key'),
            'limits' => [
                'minInactivityMinutes' => config()->integer('safety.min_inactivity_seconds') / 60,
                'maxInactivityMinutes' => config()->integer('safety.max_inactivity_seconds') / 60,
                'defaultInactivityMinutes' => config()->integer('safety.inactivity_seconds') / 60,
                'maxPauseMinutes' => config()->integer('safety.pause_max_minutes'),
            ],
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
        return ['mapConfiguration' => $coverage->mapConfiguration()];
    }

    /** @return array<int, array{id: int, code: string, name: string}> */
    private function categories(): array
    {
        return ReportCategory::query()->where('is_active', true)->orderBy('id')->get(['id', 'code', 'name'])->toArray();
    }
}
