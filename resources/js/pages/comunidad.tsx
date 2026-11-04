import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import LevelBadge from '@/components/ruta/level-badge';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { PERIODOS, ZONAS_EJEMPLO, nivelPorConteo } from '@/lib/ruta';

const MINIMO_ANONIMATO = 5;

const TENDENCIA = [
    { mes: 'Jun', valor: 120 },
    { mes: 'Jul', valor: 135 },
    { mes: 'Ago', valor: 128 },
    { mes: 'Sep', valor: 150 },
    { mes: 'Oct', valor: 142 },
    { mes: 'Nov', valor: 140 },
];

export default function Comunidad() {
    const [periodo, setPeriodo] = useState('30');
    const factor = PERIODOS.find((p) => p.id === periodo)?.factor ?? 1;
    const zonas = ZONAS_EJEMPLO.map((z) => ({
        ...z,
        conteo: Math.round(z.reportes * factor),
    })).sort((a, b) => b.conteo - a.conteo);
    const total = zonas.reduce((s, z) => s + z.conteo, 0);
    const maximo = Math.max(...zonas.map((z) => z.conteo));
    const maxTendencia = Math.max(...TENDENCIA.map((t) => t.valor));
    const puntos = TENDENCIA.map(
        (t, i) => `${20 + i * 56},${110 - (t.valor / maxTendencia) * 90}`,
    ).join(' ');

    return (
        <>
            <Head title="Dashboard comunitario" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Comunidad"
                    title="Lo que reporta la comunidad"
                    description="Estadísticas agregadas y anónimas por zona. Nunca mostramos grupos tan pequeños que permitan identificar a alguien."
                />

                <SectionCard headingId="filtros" title="Periodo">
                    <label htmlFor="periodo" className="sr-only">
                        Periodo
                    </label>
                    <select
                        id="periodo"
                        value={periodo}
                        onChange={(e) => setPeriodo(e.target.value)}
                        className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
                    >
                        {PERIODOS.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.etiqueta}
                            </option>
                        ))}
                    </select>
                </SectionCard>

                <section
                    aria-label="Indicadores generales"
                    className="grid gap-4 sm:grid-cols-3"
                >
                    {[
                        { etiqueta: 'Reportes en el periodo', valor: total },
                        {
                            etiqueta: 'Zonas con datos',
                            valor: zonas.filter(
                                (z) => z.conteo >= MINIMO_ANONIMATO,
                            ).length,
                        },
                        {
                            etiqueta: 'Zona con más reportes',
                            valor: zonas[0].nombre,
                        },
                    ].map((k) => (
                        <div
                            key={k.etiqueta}
                            className="rounded-3xl border bg-card p-5 shadow-sm"
                        >
                            <p className="text-sm text-muted-foreground">
                                {k.etiqueta}
                            </p>
                            <p className="mt-1 font-serif text-3xl font-semibold">
                                {k.valor}
                            </p>
                        </div>
                    ))}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <SectionCard headingId="por-zona" title="Reportes por zona">
                        <ul className="space-y-3">
                            {zonas.map((z) => (
                                <li key={z.id}>
                                    {z.conteo < MINIMO_ANONIMATO ? (
                                        <div className="flex justify-between text-sm text-muted-foreground">
                                            <span>{z.nombre}</span>
                                            <span>Datos insuficientes</span>
                                        </div>
                                    ) : (
                                        <>
                                            <div className="mb-1 flex flex-wrap items-center justify-between gap-2 text-sm">
                                                <span className="font-medium">
                                                    {z.nombre}
                                                </span>
                                                <span className="flex items-center gap-2">
                                                    <LevelBadge
                                                        nivel={nivelPorConteo(
                                                            z.conteo,
                                                        )}
                                                    />{' '}
                                                    <strong>{z.conteo}</strong>
                                                </span>
                                            </div>
                                            <div
                                                className="h-2.5 rounded-full bg-secondary"
                                                aria-hidden="true"
                                            >
                                                <div
                                                    className="bg-purple h-full rounded-full"
                                                    style={{
                                                        width: `${(z.conteo / maximo) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </SectionCard>

                    <SectionCard
                        headingId="tendencia"
                        title="Tendencia mensual"
                    >
                        <svg
                            viewBox="0 0 360 140"
                            role="img"
                            aria-label="Línea de tendencia de reportes por mes de junio a noviembre; se mantiene entre 120 y 150 reportes"
                            className="w-full"
                        >
                            <polyline
                                points={puntos}
                                fill="none"
                                stroke="#6d28a8"
                                strokeWidth="3"
                                strokeLinejoin="round"
                            />
                            {TENDENCIA.map((t, i) => (
                                <g key={t.mes}>
                                    <circle
                                        cx={20 + i * 56}
                                        cy={110 - (t.valor / maxTendencia) * 90}
                                        r="4.5"
                                        fill="#e395f2"
                                        stroke="#6d28a8"
                                        strokeWidth="2"
                                    />
                                    <text
                                        x={20 + i * 56}
                                        y="132"
                                        textAnchor="middle"
                                        fontSize="11"
                                        fill="currentColor"
                                    >
                                        {t.mes}
                                    </text>
                                </g>
                            ))}
                        </svg>
                        <details className="mt-2 text-sm">
                            <summary className="cursor-pointer font-medium">
                                Ver valores
                            </summary>
                            <ul className="mt-2 grid grid-cols-3 gap-1 text-muted-foreground">
                                {TENDENCIA.map((t) => (
                                    <li key={t.mes}>
                                        {t.mes}: {t.valor}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    </SectionCard>
                </div>

                <DataNotice
                    periodo={PERIODOS.find((p) => p.id === periodo)?.etiqueta}
                    reportes={total}
                >
                    Zonas con menos de {MINIMO_ANONIMATO} reportes se ocultan
                    para evitar la reidentificación. Los datos reflejan reportes
                    comunitarios, no hechos verificados, y pueden estar
                    incompletos.
                </DataNotice>
            </div>
        </>
    );
}
