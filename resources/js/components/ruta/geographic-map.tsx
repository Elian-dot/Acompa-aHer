import type { FeatureCollection } from 'geojson';
import type * as Leaflet from 'leaflet';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import type {
    Coordinate,
    MapConfiguration,
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
    const [ready, setReady] = useState(false);
    const [message, setMessage] = useState('');
    const selectPoint = useEffectEvent((point: Coordinate) =>
        onSelect?.(point),
    );
    const [latitude, longitude] = configuration.center;

    useEffect(() => {
        let disposed = false;
        let observer: ResizeObserver | undefined;
        void import('leaflet')
            .then((L) => {
                if (disposed || !container.current) return;
                library.current = L;
                const map = L.map(container.current, {
                    zoomControl: false,
                }).setView([latitude, longitude], configuration.zoom);
                instance.current = map;
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
                        'No se pudo cargar una parte del mapa. Revisa tu conexión.',
                    ),
                );
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
        };
    }, [latitude, longitude, configuration.zoom, configuration.tileUrl]);

    useEffect(() => {
        const L = library.current;
        const group = layers.current;
        if (!ready || !L || !group) return;
        group.clearLayers();
        if (coverage)
            L.geoJSON(coverage, {
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
    }, [ready, coverage, heatmap, points, routes, selectedRoute]);

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
                    {message}
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
