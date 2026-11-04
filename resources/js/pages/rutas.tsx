import { Head, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import GeographicMap from '@/components/ruta/geographic-map';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useGeolocation } from '@/hooks/use-geolocation';
import { requestErrorMessage } from '@/lib/safety';
import { store } from '@/routes/api/v1/walking_routes';
import type {
    Coordinate,
    MapConfiguration,
    WalkingRouteResponse,
} from '@/types/safety';

type RouteForm = {
    origin: { latitude: string; longitude: string };
    destination: { latitude: string; longitude: string };
    provider_consent: boolean;
};

export default function Rutas({
    mapConfiguration,
    routingProvider,
}: {
    mapConfiguration: MapConfiguration;
    routingProvider: string;
}) {
    const form = useHttp<RouteForm, WalkingRouteResponse>({
        origin: { latitude: '', longitude: '' },
        destination: { latitude: '', longitude: '' },
        provider_consent: false,
    });
    const [selecting, setSelecting] = useState<'origin' | 'destination'>(
        'origin',
    );
    const [result, setResult] = useState<WalkingRouteResponse | null>(null);
    const [selected, setSelected] = useState(1);
    const [message, setMessage] = useState('');
    const location = useGeolocation();
    const points = (['origin', 'destination'] as const).flatMap((key) => {
        const point = form.data[key];
        return point.latitude !== '' &&
            point.longitude !== '' &&
            Number.isFinite(Number(point.latitude)) &&
            Number.isFinite(Number(point.longitude))
            ? [
                  {
                      latitude: Number(point.latitude),
                      longitude: Number(point.longitude),
                      label: key === 'origin' ? 'Origen' : 'Destino',
                  },
              ]
            : [];
    });
    function choose(key: 'origin' | 'destination', point: Coordinate) {
        form.setData(key, {
            latitude: point.latitude.toFixed(6),
            longitude: point.longitude.toFixed(6),
        });
        setResult(null);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        setMessage('');
        setResult(null);
        void form
            .post(store.url())
            .then((response) => {
                setResult(response);
                setSelected(response.data[0]?.id ?? 1);
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    return (
        <>
            <Head title="Rutas peatonales" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Planea tu recorrido"
                    title="Rutas para caminar por Girardot"
                    description="Selecciona origen y destino para consultar alternativas peatonales reales. Por ahora no se calcula una puntuación de seguridad: faltan fuentes validadas de iluminación, comercios y afluencia."
                />
                {!mapConfiguration.coverageAvailable && (
                    <FormErrors message="La cobertura de Girardot todavía no está disponible." />
                )}
                <form onSubmit={submit} className="space-y-6">
                    <SectionCard
                        headingId="recorrido"
                        title="¿De dónde sales y a dónde vas?"
                    >
                        <div className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {(['origin', 'destination'] as const).map(
                                    (key) => (
                                        <Button
                                            type="button"
                                            key={key}
                                            variant={
                                                selecting === key
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            aria-pressed={selecting === key}
                                            onClick={() => setSelecting(key)}
                                            disabled={form.processing}
                                        >
                                            Seleccionar{' '}
                                            {key === 'origin'
                                                ? 'origen'
                                                : 'destino'}{' '}
                                            en el mapa
                                        </Button>
                                    ),
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Toca el mapa para indicar el{' '}
                                {selecting === 'origin' ? 'origen' : 'destino'}.
                                También puedes introducir coordenadas.
                            </p>
                            <GeographicMap
                                configuration={mapConfiguration}
                                points={points}
                                routes={result?.data}
                                selectedRoute={selected}
                                onSelect={
                                    form.processing
                                        ? undefined
                                        : (point) => choose(selecting, point)
                                }
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={location.locating || form.processing}
                                onClick={() =>
                                    location.locate((point) =>
                                        choose('origin', point),
                                    )
                                }
                            >
                                {location.locating
                                    ? 'Buscando ubicación…'
                                    : 'Usar mi ubicación como origen'}
                            </Button>
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {location.message}
                            </p>
                            <fieldset
                                disabled={form.processing}
                                className="grid gap-5 sm:grid-cols-2"
                            >
                                {(['origin', 'destination'] as const).map(
                                    (key) => (
                                        <div key={key} className="space-y-2">
                                            <h3 className="font-semibold">
                                                {key === 'origin'
                                                    ? 'Origen'
                                                    : 'Destino'}
                                            </h3>
                                            <label className="grid gap-1 text-sm">
                                                Latitud
                                                <Input
                                                    required
                                                    type="number"
                                                    min="-90"
                                                    max="90"
                                                    step="any"
                                                    value={
                                                        form.data[key].latitude
                                                    }
                                                    onChange={(event) => {
                                                        form.setData(key, {
                                                            ...form.data[key],
                                                            latitude:
                                                                event.target
                                                                    .value,
                                                        });
                                                        setResult(null);
                                                    }}
                                                />
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                Longitud
                                                <Input
                                                    required
                                                    type="number"
                                                    min="-180"
                                                    max="180"
                                                    step="any"
                                                    value={
                                                        form.data[key].longitude
                                                    }
                                                    onChange={(event) => {
                                                        form.setData(key, {
                                                            ...form.data[key],
                                                            longitude:
                                                                event.target
                                                                    .value,
                                                        });
                                                        setResult(null);
                                                    }}
                                                />
                                            </label>
                                        </div>
                                    ),
                                )}
                            </fieldset>
                            <label className="flex items-start gap-3 text-sm">
                                <input
                                    required
                                    type="checkbox"
                                    className="mt-1 size-5 accent-primary"
                                    checked={form.data.provider_consent}
                                    onChange={(event) =>
                                        form.setData(
                                            'provider_consent',
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span>
                                    Autorizo enviar el origen y destino a{' '}
                                    {routingProvider} para calcular el
                                    recorrido. El proveedor puede registrar la
                                    consulta. Nuestra aplicación no guarda un
                                    historial de estas búsquedas.{' '}
                                    <a
                                        className="underline"
                                        href="https://routing.openstreetmap.de/about.html"
                                    >
                                        Consultar condiciones y privacidad
                                    </a>
                                    .
                                </span>
                            </label>
                        </div>
                    </SectionCard>
                    <FormErrors errors={form.errors} message={message} />
                    <Button
                        type="submit"
                        size="lg"
                        disabled={
                            form.processing ||
                            !mapConfiguration.coverageAvailable
                        }
                    >
                        {form.processing
                            ? 'Calculando alternativas…'
                            : 'Consultar rutas peatonales'}
                    </Button>
                </form>
                {result && (
                    <SectionCard
                        headingId="alternativas"
                        title="Alternativas de recorrido"
                    >
                        {result.data.length === 0 ? (
                            <p role="status">
                                No encontramos rutas peatonales dentro de la
                                cobertura para estos puntos. Ajusta el origen o
                                el destino.
                            </p>
                        ) : (
                            <ul className="grid gap-4 md:grid-cols-3">
                                {result.data.map((route) => (
                                    <li key={route.id}>
                                        <button
                                            type="button"
                                            aria-pressed={selected === route.id}
                                            onClick={() =>
                                                setSelected(route.id)
                                            }
                                            className={`w-full rounded-2xl border-2 p-5 text-left ${selected === route.id ? 'border-primary bg-secondary' : 'border-border bg-card'}`}
                                        >
                                            <strong className="text-lg">
                                                Ruta {route.id}
                                            </strong>
                                            <p className="mt-2">
                                                {Math.ceil(
                                                    route.duration_seconds / 60,
                                                )}{' '}
                                                min ·{' '}
                                                {(
                                                    route.distance_meters / 1000
                                                ).toLocaleString('es-CO', {
                                                    maximumFractionDigits: 2,
                                                })}{' '}
                                                km
                                            </p>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                Información de seguridad
                                                insuficiente
                                            </p>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="mt-4 text-sm text-muted-foreground">
                            Fuente: {result.meta.provider}. {result.meta.notice}{' '}
                            Ninguna ruta garantiza ausencia de riesgo.
                        </p>
                    </SectionCard>
                )}
            </div>
        </>
    );
}
