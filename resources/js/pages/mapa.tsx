import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import GeographicMap from '@/components/ruta/geographic-map';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { useGeolocation } from '@/hooks/use-geolocation';
import { requestErrorMessage } from '@/lib/safety';
import { index } from '@/routes/api/v1/map';
import type {
    Category,
    Coordinate,
    MapConfiguration,
    MapPayload,
} from '@/types/safety';

export default function Mapa({
    categories,
    mapConfiguration,
}: {
    categories: Category[];
    mapConfiguration: MapConfiguration;
}) {
    const request = useHttp<Record<string, never>, MapPayload>({});
    const [category, setCategory] = useState('');
    const [message, setMessage] = useState('');
    const [position, setPosition] = useState<Coordinate | null>(null);
    const location = useGeolocation();
    function load() {
        setMessage('');
        void request
            .get(
                index.url(
                    category
                        ? { query: { report_category_id: category } }
                        : undefined,
                ),
            )
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    const initialLoad = useEffectEvent(() => load());
    const cancel = useEffectEvent(() => request.cancel());
    useEffect(() => {
        initialLoad();
        return () => cancel();
    }, [category]);
    const data = message || request.processing ? null : request.response;
    const meta = data?.heatmap.meta;
    return (
        <>
            <Head title="Mapa de calor" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Información del territorio"
                    title="Mapa de reportes nocturnos"
                    description="Consulta áreas con incidentes verificados durante los últimos 30 días. Una zona sin reportes no es necesariamente una zona segura."
                />
                <SectionCard headingId="filtros" title="Qué quieres consultar">
                    <div className="flex flex-wrap items-end gap-4">
                        <label className="grid gap-2 text-sm font-medium">
                            Tipo de incidente
                            <select
                                value={category}
                                onChange={(e) => setCategory(e.target.value)}
                                className="h-11 rounded-xl border bg-background px-3"
                            >
                                <option value="">Todos los tipos</option>
                                {categories.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <Button
                            variant="outline"
                            disabled={request.processing}
                            onClick={load}
                        >
                            {request.processing
                                ? 'Consultando…'
                                : 'Actualizar datos'}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={location.locating}
                            onClick={() => location.locate(setPosition)}
                        >
                            Ubicarme en el mapa
                        </Button>
                    </div>
                    <p
                        role="status"
                        className="mt-3 text-sm text-muted-foreground"
                    >
                        {location.message}
                    </p>
                </SectionCard>
                <FormErrors errors={request.errors} message={message} />
                {!mapConfiguration.coverageAvailable && (
                    <FormErrors message="La cobertura geográfica aún no está disponible." />
                )}
                <SectionCard
                    headingId="mapa"
                    title="Incidentes verificados por área"
                >
                    {request.processing && (
                        <p role="status" className="mb-3 animate-pulse text-sm">
                            Actualizando el mapa…
                        </p>
                    )}
                    <GeographicMap
                        configuration={mapConfiguration}
                        coverage={data?.coverage}
                        heatmap={data?.heatmap}
                        points={
                            position
                                ? [
                                      {
                                          ...position,
                                          label: 'Tu ubicación · no se guarda',
                                      },
                                  ]
                                : []
                        }
                    />
                    {meta?.status === 'insufficient_data' && (
                        <p
                            role="status"
                            className="mt-4 rounded-2xl border bg-secondary p-4"
                        >
                            No hay suficientes reportes verificados para mostrar
                            áreas con estos filtros. Esto no indica ausencia de
                            riesgo.
                        </p>
                    )}
                    <ul
                        aria-label="Leyenda"
                        className="mt-4 flex flex-wrap gap-3 text-sm"
                    >
                        {[
                            ['#2e8b57', '5–9 reportes'],
                            ['#d4aa16', '10–19 reportes'],
                            ['#ef8c35', '20–29 reportes'],
                            ['#cc3446', '30 o más reportes'],
                        ].map(([color, label]) => (
                            <li key={color} className="flex items-center gap-2">
                                <span
                                    className="size-4 rounded border border-foreground/30"
                                    style={{ background: color }}
                                    aria-hidden="true"
                                />
                                {label}
                            </li>
                        ))}
                    </ul>
                </SectionCard>
                {meta && (
                    <aside className="rounded-2xl border border-dashed p-4 text-sm text-muted-foreground">
                        <p>
                            Fuente: reportes comunitarios revisados. Del{' '}
                            {new Date(meta.from).toLocaleDateString('es-CO', {
                                timeZone: 'America/Bogota',
                            })}{' '}
                            al{' '}
                            {new Date(meta.to).toLocaleDateString('es-CO', {
                                timeZone: 'America/Bogota',
                            })}{' '}
                            · {meta.night_start_hour}:00–
                            {String(meta.night_end_hour).padStart(2, '0')}:00,
                            hora de Colombia.
                        </p>
                        <p className="mt-2">
                            Áreas de aproximadamente {meta.cell_meters} metros.
                            Solo se muestran grupos de al menos{' '}
                            {meta.minimum_reports} reportes y conteos por
                            intervalos. No se publican puntos exactos,
                            descripciones, adjuntos ni identidades.
                        </p>
                    </aside>
                )}
            </div>
        </>
    );
}
