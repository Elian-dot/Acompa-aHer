import type { FeatureCollection } from 'geojson';
import type * as Leaflet from 'leaflet';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { reference_map } from '@/routes/api/v1';
import type {
    Coordinate,
    MapConfiguration,
    ReferenceMap,
    WalkingRoute,
} from '@/types/safety';
import 'leaflet/dist/leaflet.css';

const COLORS: Record<string, string> = {
    bajo: '#2e8b57',
    medio: '#d4aa16',
    alto: '#ef8c35',
    'muy-alto': '#cc3446',
};

type Props = {
    configuration: MapConfiguration;
    coverage?: FeatureCollection;
    heatmap?: FeatureCollection;
    points?: (Coordinate & { label: string })[];
    routes?: WalkingRoute[];
    selectedRoute?: number;
    onSelect?: (point: Coordinate) => void;
};

export default function GeographicMap({
    configuration,
    coverage,
    heatmap,
    points,
    routes,
    selectedRoute,
    onSelect,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const instance = useRef<Leaflet.Map | null>(null);
    const library = useRef<typeof Leaflet | null>(null);
    const layers = useRef<Leaflet.LayerGroup | null>(null);
    const referenceLayers = useRef<Leaflet.LayerGroup | null>(null);
    const [reference, setReference] = useState<ReferenceMap | null>(null);
    const [ready, setReady] = useState(false);
    const [message, setMessage] = useState('');
    const selectPoint = useEffectEvent((point: Coordinate) =>
        onSelect?.(point),
    );
    const [latitude, longitude] = configuration.center;

    useEffect(() => {
        const abort = new AbortController();
        void fetch(reference_map.url(), {
            signal: abort.signal,
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('reference-map');
                const data: ReferenceMap = await response.json();
                if (!abort.signal.aborted) setReference(data);
            })
            .catch(() => {
                // Las imágenes y la selección manual siguen disponibles si falla esta capa.
            });
        return () => abort.abort();
    }, []);

    useEffect(() => {
        let disposed = false;
        let observer: ResizeObserver | undefined;
        void import('leaflet')
            .then((L) => {
                if (disposed || !container.current) return;
                library.current = L;
                const map = L.map(container.current, {
                    zoomControl: false,
                    preferCanvas: true,
                }).setView([latitude, longitude], configuration.zoom);
                instance.current = map;
                map.createPane('reference');
                map.getPane('reference')!.style.zIndex = '350';
                L.control
                    .zoom({ zoomInTitle: 'Acercar', zoomOutTitle: 'Alejar' })
                    .addTo(map);
                const tiles = L.tileLayer(configuration.tileUrl, {
                    maxZoom: 19,
                    attribution:
                        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                }).addTo(map);
                tiles.on('tileerror', () =>
                    setMessage(
                        'No se pudieron cargar algunas imágenes del mapa.',
                    ),
                );
                referenceLayers.current = L.layerGroup().addTo(map);
                layers.current = L.layerGroup().addTo(map);
                map.on('click', (event: Leaflet.LeafletMouseEvent) =>
                    selectPoint({
                        latitude: event.latlng.lat,
                        longitude: event.latlng.lng,
                    }),
                );
                observer = new ResizeObserver(() => map.invalidateSize());
                observer.observe(container.current);
                setReady(true);
            })
            .catch(() =>
                setMessage(
                    'No se pudo cargar el mapa interactivo. Recarga la página.',
                ),
            );
        return () => {
            disposed = true;
            observer?.disconnect();
            instance.current?.remove();
            instance.current = null;
            layers.current = null;
            referenceLayers.current = null;
        };
    }, [latitude, longitude, configuration.zoom, configuration.tileUrl]);

    useEffect(() => {
        const L = library.current;
        const group = referenceLayers.current;
        if (!ready || !L || !group || !reference) return;
        group.clearLayers();
        const labels: Record<string, string> = {
            road: 'Vía cartografiada',
            shop: 'Comercio cartografiado',
            amenity: 'Lugar de referencia',
            street_lamp: 'Luminaria cartografiada',
        };
        L.geoJSON(reference, {
            pane: 'reference',
            style: (feature) => ({
                color: '#8c7e70',
                weight: ['primary', 'secondary', 'trunk'].includes(
                    String(feature?.properties?.classification),
                )
                    ? 4
                    : 2,
                opacity: 0.8,
                dashArray: ['no', 'private'].includes(
                    String(feature?.properties?.access),
                )
                    ? '4 4'
                    : undefined,
            }),
            pointToLayer: (feature, latlng) =>
                L.circleMarker(latlng, {
                    pane: 'reference',
                    radius: 4,
                    color:
                        feature.properties?.kind === 'street_lamp'
                            ? '#9c7413'
                            : '#137f80',
                    weight: 1,
                    fillOpacity: 0.8,
                }),
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = String(
                    feature.properties?.name ??
                        labels[String(feature.properties?.kind)] ??
                        'Referencia cartográfica',
                );
                layer.bindTooltip(label);
            },
        }).addTo(group);
    }, [ready, reference]);

    useEffect(() => {
        const L = library.current;
        const group = layers.current;
        if (!ready || !L || !group) return;
        group.clearLayers();
        if (coverage ?? configuration.coverage)
            L.geoJSON(coverage ?? configuration.coverage, {
                interactive: false,
                style: { color: '#7e36a6', weight: 2, fillOpacity: 0.025 },
            }).addTo(group);
        if (heatmap)
            L.geoJSON(heatmap, {
                style: (feature) => ({
                    color:
                        COLORS[String(feature?.properties?.level)] ?? '#7e36a6',
                    weight: 2,
                    fillOpacity: 0.55,
                    dashArray:
                        feature?.properties?.level === 'muy-alto'
                            ? '5 4'
                            : undefined,
                }),
                onEachFeature: (feature, layer) => {
                    const label = document.createElement('span');
                    label.textContent = `${String(feature.properties?.count_range ?? '')} reportes verificados`;
                    layer.bindTooltip(label);
                },
            }).addTo(group);
        routes?.forEach((route) =>
            L.geoJSON(route.geometry, {
                interactive: false,
                style: {
                    color: route.id === selectedRoute ? '#7e36a6' : '#79717b',
                    weight: route.id === selectedRoute ? 6 : 3,
                },
            }).addTo(group),
        );
        points?.forEach((point, index) => {
            const label = document.createElement('span');
            label.textContent = point.label;
            L.circleMarker([point.latitude, point.longitude], {
                radius: 8,
                color: index === 0 ? '#6d28a8' : '#ad521e',
                fillOpacity: 1,
                fillColor: '#fff',
                weight: 3,
            })
                .bindTooltip(label)
                .addTo(group);
        });
    }, [
        ready,
        coverage,
        configuration.coverage,
        heatmap,
        points,
        routes,
        selectedRoute,
    ]);

    useEffect(() => {
        const L = library.current;
        if (!ready || !L || !instance.current) return;
        const selected = routes?.find((route) => route.id === selectedRoute);
        if (selected)
            instance.current.fitBounds(
                L.geoJSON(selected.geometry).getBounds(),
                { padding: [25, 25], maxZoom: 17, animate: false },
            );
        else if (points?.length)
            instance.current.panTo([points[0].latitude, points[0].longitude], {
                animate: false,
            });
    }, [ready, points, routes, selectedRoute]);

    return (
        <div className="space-y-2">
            <div
                ref={container}
                role="region"
                aria-label="Mapa interactivo de Girardot. Usa las flechas para desplazarte y los botones para acercar o alejar."
                className="relative z-0 h-80 w-full overflow-hidden rounded-2xl border bg-secondary md:h-96"
            />
            {message && (
                <p role="status" className="text-sm text-muted-foreground">
                    {message}{' '}
                    {reference && reference.features.length > 0
                        ? 'Puedes orientarte y seleccionar puntos sobre las calles de la cartografía local.'
                        : 'Revisa tu conexión. Puedes introducir las coordenadas manualmente.'}
                </p>
            )}
            {reference && reference.features.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    Cartografía local:{' '}
                    {reference.meta.roads.toLocaleString('es-CO')} tramos de vía
                    y {reference.meta.places.toLocaleString('es-CO')} lugares de
                    referencia. {reference.meta.notice}{' '}
                    <a
                        href="https://www.openstreetmap.org/copyright"
                        className="underline"
                    >
                        OpenStreetMap · ODbL
                    </a>
                    .
                </p>
            )}
            <p className="text-xs text-muted-foreground">
                Cartografía:{' '}
                <a href={configuration.coverageSourceUrl} className="underline">
                    {configuration.coverageSource}
                </a>{' '}
                · Consultada el {configuration.coverageRetrievedAt}. Cobertura
                operativa, sujeta a revisión.{' '}
                <a
                    href="https://www.openstreetmap.org/fixthemap"
                    className="underline"
                >
                    Informar un error del mapa
                </a>
                .
            </p>
        </div>
    );
}
