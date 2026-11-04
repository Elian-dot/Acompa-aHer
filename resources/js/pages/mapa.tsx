import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import LevelBadge from '@/components/ruta/level-badge';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import {
    FRANJAS,
    NIVELES,
    PERIODOS,
    TIPOS_INCIDENTE,
    ZONAS_EJEMPLO,
    nivelPorConteo,
} from '@/lib/ruta';
import type { NivelRiesgo } from '@/lib/ruta';
import { cn } from '@/lib/utils';

export default function Mapa() {
    const [franja, setFranja] = useState('todas');
    const [tipo, setTipo] = useState('todos');
    const [periodo, setPeriodo] = useState('30');
    const [activa, setActiva] = useState<string | null>(null);

    const zonas = useMemo(() => {
        const f = FRANJAS.find((x) => x.id === franja)?.factor ?? 1;
        const t = TIPOS_INCIDENTE.find((x) => x.id === tipo)?.factor ?? 1;
        const p = PERIODOS.find((x) => x.id === periodo)?.factor ?? 1;

        return ZONAS_EJEMPLO.map((zona) => {
            const conteo = Math.round(zona.reportes * f * t * p * 1.0);

            return { ...zona, conteo, nivel: nivelPorConteo(conteo) };
        });
    }, [franja, tipo, periodo]);

    const total = zonas.reduce((suma, zona) => suma + zona.conteo, 0);
    const detalle = zonas.find((zona) => zona.id === activa);
    const periodoEtiqueta =
        PERIODOS.find((x) => x.id === periodo)?.etiqueta ?? '';

    return (
        <>
            <Head title="Mapa de calor" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Planear mi trayecto"
                    title="Zonas con más o menos reportes"
                    description="Cada zona se pinta según la cantidad de reportes nocturnos recibidos. Mostramos áreas aproximadas, nunca ubicaciones exactas."
                    ayuda={
                        <p>
                            Usa los filtros para cambiar la franja horaria, el
                            tipo de incidente y el periodo. Los colores siempre
                            vienen con un patrón y una etiqueta de texto. Un
                            reporte es un aporte de la comunidad, no un hecho
                            verificado.
                        </p>
                    }
                />

                <SectionCard headingId="filtros" title="Filtros">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Filtro
                            id="franja"
                            etiqueta="Franja horaria"
                            valor={franja}
                            onChange={setFranja}
                            opciones={FRANJAS}
                        />
                        <Filtro
                            id="tipo"
                            etiqueta="Tipo de incidente"
                            valor={tipo}
                            onChange={setTipo}
                            opciones={TIPOS_INCIDENTE}
                        />
                        <Filtro
                            id="periodo"
                            etiqueta="Periodo"
                            valor={periodo}
                            onChange={setPeriodo}
                            opciones={PERIODOS}
                        />
                    </div>
                </SectionCard>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <SectionCard
                        headingId="mapa-zonas"
                        title="Mapa por zonas"
                        description="Selecciona una zona para ver su detalle."
                    >
                        <div
                            className="grid grid-cols-3 gap-2"
                            role="group"
                            aria-label="Zonas del mapa de calor"
                        >
                            {[0, 1, 2].flatMap((fila) =>
                                [0, 1, 2].map((col) => {
                                    const zona = zonas.find(
                                        (z) => z.fila === fila && z.col === col,
                                    );

                                    if (!zona) {
                                        return <div key={`${fila}${col}`} />;
                                    }

                                    const info = NIVELES[zona.nivel];

                                    return (
                                        <button
                                            key={zona.id}
                                            type="button"
                                            onClick={() => setActiva(zona.id)}
                                            aria-pressed={activa === zona.id}
                                            aria-label={`${zona.nombre}: ${zona.conteo} reportes, ${info.etiqueta}`}
                                            className={cn(
                                                'relative flex aspect-[4/3] flex-col justify-end overflow-hidden rounded-2xl border-2 border-transparent p-3 text-left text-[#24112f] transition hover:scale-[1.02]',
                                                activa === zona.id &&
                                                    'border-foreground ring-2 ring-lila',
                                            )}
                                            style={{
                                                backgroundColor: info.color,
                                            }}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'absolute inset-0 opacity-30',
                                                    info.patron,
                                                )}
                                            />
                                            <span className="relative text-sm font-bold">
                                                {zona.nombre}
                                            </span>
                                            <span className="relative text-xs font-semibold">
                                                {zona.conteo} reportes
                                            </span>
                                        </button>
                                    );
                                }),
                            )}
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Cuadrícula ilustrativa con datos de ejemplo.
                        </p>
                    </SectionCard>

                    <div className="flex flex-col gap-6">
                        <SectionCard headingId="leyenda" title="Leyenda">
                            <ul className="space-y-3">
                                {(Object.keys(NIVELES) as NivelRiesgo[]).map(
                                    (nivel) => (
                                        <li
                                            key={nivel}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <LevelBadge nivel={nivel} />
                                            <span className="text-muted-foreground">
                                                {NIVELES[nivel].descripcion}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </SectionCard>

                        <SectionCard
                            headingId="detalle"
                            title="Detalle de la zona"
                        >
                            {detalle ? (
                                <div
                                    className="space-y-2 text-sm"
                                    aria-live="polite"
                                >
                                    <p className="text-lg font-semibold">
                                        {detalle.nombre}
                                    </p>
                                    <LevelBadge nivel={detalle.nivel} />
                                    <p>
                                        <strong>{detalle.conteo}</strong>{' '}
                                        reportes en el periodo (
                                        {periodoEtiqueta.toLowerCase()}).
                                    </p>
                                    <p className="text-muted-foreground">
                                        Son reportes de la comunidad, sin
                                        verificar.
                                        {detalle.conteo < 5 &&
                                            ' Hay muy pocos datos para sacar conclusiones.'}
                                    </p>
                                </div>
                            ) : (
                                <p
                                    className="text-sm text-muted-foreground"
                                    aria-live="polite"
                                >
                                    Selecciona una zona del mapa.
                                </p>
                            )}
                        </SectionCard>
                    </div>
                </div>

                <DataNotice periodo={periodoEtiqueta} reportes={total}>
                    Los reportes mostrados son agregados por zona y no
                    identifican a las personas afectadas. La ausencia de
                    reportes no es una prueba de seguridad.
                </DataNotice>
            </div>
        </>
    );
}

type Opcion = { id: string; etiqueta: string };

function Filtro({
    id,
    etiqueta,
    valor,
    onChange,
    opciones,
}: {
    id: string;
    etiqueta: string;
    valor: string;
    onChange: (valor: string) => void;
    opciones: readonly Opcion[];
}) {
    return (
        <div className="grid gap-2">
            <label htmlFor={id} className="text-sm font-medium">
                {etiqueta}
            </label>
            <select
                id={id}
                value={valor}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
            >
                {opciones.map((opcion) => (
                    <option key={opcion.id} value={opcion.id}>
                        {opcion.etiqueta}
                    </option>
                ))}
            </select>
        </div>
    );
}
