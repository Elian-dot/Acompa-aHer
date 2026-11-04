<?php

namespace App\Actions\Routes;

use App\Data\GeographicCoverage;
use App\Models\Zone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CalculateWalkingRoutes
{
    public function __construct(private GeographicCoverage $coverage) {}

    /**
     * @param  array{latitude: float, longitude: float}  $origin
     * @param  array{latitude: float, longitude: float}  $destination
     * @return array<string, mixed>
     */
    public function handle(array $origin, array $destination): array
    {
        $this->coverage->validatePoint((float) $origin['longitude'], (float) $origin['latitude'], 'origin');
        $this->coverage->validatePoint((float) $destination['longitude'], (float) $destination['latitude'], 'destination');
        if ($origin == $destination) {
            throw ValidationException::withMessages(['destination' => 'El destino debe ser diferente del origen.']);
        }

        $baseUrl = rtrim(config()->string('safety.routing_url'), '/');
        abort_if($baseUrl === '', 503, 'El motor de rutas peatonales no está configurado.');
        abort_unless(Cache::add('walking-provider:'.hash('sha256', $baseUrl), true, now()->addSeconds(2)), 429, 'Espera un segundo antes de volver a consultar.');
        $coordinates = sprintf('%.6F,%.6F;%.6F,%.6F', $origin['longitude'], $origin['latitude'], $destination['longitude'], $destination['latitude']);

        try {
            $response = Http::acceptJson()->withUserAgent('CaminaSegura/1.0 (+'.config('app.url').')')
                ->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->get($baseUrl.'/route/v1/foot/'.$coordinates, [
                    'alternatives' => 'true', 'overview' => 'full', 'geometries' => 'geojson',
                    'steps' => 'false', 'radiuses' => '50;50',
                ]);
        } catch (ConnectionException) {
            abort(503, 'No pudimos conectar con el motor de rutas. Inténtalo de nuevo.');
        }

        if ($response->json('code') === 'NoRoute' || $response->json('code') === 'NoSegment') {
            return $this->result([]);
        }
        abort_unless($response->successful() && $response->json('code') === 'Ok', 503, 'El motor de rutas no está disponible.');
        $data = $response->json();
        abort_if(Validator::make($data, [
            'routes' => ['required', 'array', 'min:1'],
            'routes.*.distance' => ['required', 'numeric', 'min:0', 'max:100000'],
            'routes.*.duration' => ['required', 'numeric', 'min:0'],
            'routes.*.geometry.type' => ['required', 'in:LineString'],
            'routes.*.geometry.coordinates' => ['required', 'array', 'min:2', 'max:20000'],
            'routes.*.geometry.coordinates.*' => ['required', 'array', 'size:2'],
            'routes.*.geometry.coordinates.*.0' => ['required', 'numeric', 'between:-180,180'],
            'routes.*.geometry.coordinates.*.1' => ['required', 'numeric', 'between:-90,90'],
        ])->fails(), 503, 'El motor devolvió una ruta que no se pudo validar.');

        $routes = [];
        foreach (array_slice($data['routes'], 0, 3) as $route) {
            $geometry = ['type' => 'LineString', 'coordinates' => $route['geometry']['coordinates']];
            $covered = Zone::query()->where('code', config('safety.coverage_code'))
                ->whereRaw('ST_Covers(boundary, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))', [json_encode($geometry, JSON_THROW_ON_ERROR)])->exists();
            if ($covered) {
                $routes[] = [
                    'id' => count($routes) + 1, 'distance_meters' => (float) $route['distance'],
                    'duration_seconds' => (float) $route['duration'], 'geometry' => $geometry,
                    'safety_score' => null, 'evidence_status' => 'insufficient_data',
                ];
            }
        }

        return $this->result($routes);
    }

    /** @param list<array<string, mixed>> $routes
     * @return array<string, mixed>
     */
    private function result(array $routes): array
    {
        return [
            'data' => $routes,
            'meta' => [
                'profile' => 'foot', 'status' => $routes === [] ? 'no_route' : 'available',
                'provider' => config('safety.routing_provider'), 'queried_at' => now()->toIso8601String(),
                'notice' => 'Rutas peatonales sin puntuación de seguridad: faltan fuentes validadas de iluminación, comercios y afluencia.',
            ],
        ];
    }
}
