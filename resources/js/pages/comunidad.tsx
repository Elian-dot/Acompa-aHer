import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import FormErrors from '@/components/ruta/form-errors';
import LoadingBlock from '@/components/ruta/loading-block';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { apiErrorMessage } from '@/lib/api-errors';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { community_statistics } from '@/routes/api/v1';
import type { CommunityStatistics } from '@/types/journey';

const PERIODS = [
    { id: 30, label: 'Últimos 30 días' },
    { id: 90, label: 'Últimos 90 días' },
    { id: 180, label: 'Últimos 180 días' },
];

export default function Comunidad() {
    const http = useHttp<Record<string, never>, CommunityStatistics>({});
    const [period, setPeriod] = useState(90);
    const [problem, setProblem] = useState('');
    const data = http.response;

    const load = useEffectEvent((days: number) => {
        setProblem('');
        void http
            .get(community_statistics.url({ query: { period: days } }))
            .catch((error: unknown) => setProblem(apiErrorMessage(error)));
    });
    const cancel = useEffectEvent(() => http.cancel());

    useEffect(() => {
        load(period);
        return () => cancel();
    }, [period]);

    const maxCategory = Math.max(
        1,
        ...(data?.categories.map((item) => item.count ?? 0) ?? [1]),
    );
    const maxMonth = Math.max(
        1,
        ...(data?.months.map((item) => item.count ?? 0) ?? [1]),
    );

    return (
        <>
            <Head title="Dashboard comunitario" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Comunidad"
                    title="Lo que reporta la comunidad"
                    description="Estadísticas agregadas y anónimas de los reportes verificados en Girardot. Agrupamos o ocultamos las cifras pequeñas para que nunca permitan identificar a alguien."
                />

                <SectionCard headingId="periodo" title="Periodo">
                    <label htmlFor="periodo" className="sr-only">
                        Periodo
                    </label>
                    <select
                        id="periodo"
                        value={period}
                        onChange={(event) =>
                            setPeriod(Number(event.target.value))
                        }
                        className="h-11 rounded-xl border border-input bg-background px-3 text-sm"
                    >
                        {PERIODS.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.label}
                            </option>
                        ))}
                    </select>
                </SectionCard>

                <FormErrors message={problem} />
                {!data && !problem && (
                    <LoadingBlock label="Consultando las estadísticas" />
                )}

                {data && (
                    <>
                        <section
                            aria-label="Indicadores generales"
                            className="grid gap-4 sm:grid-cols-3"
                        >
                            <div className="rounded-3xl border bg-card p-5 shadow-sm">
                                <p className="text-sm text-muted-foreground">
                                    Reportes verificados
                                </p>
                                <p className="mt-1 font-serif text-3xl font-semibold">
                                    {data.totals.verified}
                                </p>
                            </div>
                            <div className="rounded-3xl border bg-card p-5 shadow-sm">
                                <p className="text-sm text-muted-foreground">
                                    Frente al periodo anterior
                                </p>
                                <p className="mt-1 font-serif text-3xl font-semibold">
                                    {data.totals.change_percent === null
                                        ? 'Sin dato'
                                        : `${data.totals.change_percent > 0 ? '+' : ''}${data.totals.change_percent} %`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {data.totals.previous_period === null
                                        ? `El periodo anterior tuvo menos de ${data.meta.minimum_group} reportes.`
                                        : `Antes: ${data.totals.previous_period} reportes.`}
                                </p>
                            </div>
                            <div className="rounded-3xl border bg-card p-5 shadow-sm">
                                <p className="text-sm text-muted-foreground">
                                    Zona
                                </p>
                                <p className="mt-1 font-serif text-3xl font-semibold">
                                    {data.meta.zone}
                                </p>
                            </div>
                        </section>

                        {data.meta.status === 'insufficient_data' ? (
                            <SectionCard
                                headingId="sin-datos"
                                title="Todavía no hay datos suficientes"
                            >
                                <p className="text-sm">
                                    Con {data.meta.sample_size} reportes
                                    verificados en este periodo no podemos
                                    mostrar categorías ni tendencias sin riesgo
                                    de identificar a alguien. Necesitamos al
                                    menos {data.meta.minimum_sample}.
                                </p>
                            </SectionCard>
                        ) : (
                            <div className="grid gap-6 lg:grid-cols-2">
                                <SectionCard
                                    headingId="categorias"
                                    title="Por tipo de incidente"
                                >
                                    <ul className="space-y-3">
                                        {data.categories.map((item) => (
                                            <li key={item.code}>
                                                <div className="mb-1 flex items-center justify-between gap-2 text-sm">
                                                    <span className="font-medium">
                                                        {item.name}
                                                    </span>
                                                    <strong>
                                                        {item.count ??
                                                            `menos de ${data.meta.minimum_group}`}
                                                    </strong>
                                                </div>
                                                <div
                                                    className="h-2.5 rounded-full bg-secondary"
                                                    aria-hidden="true"
                                                >
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            item.grouped
                                                                ? 'pattern-stripes bg-transparent text-purple dark:text-lila'
                                                                : 'bg-purple dark:bg-lila',
                                                        )}
                                                        style={{
                                                            width: `${item.count === null ? 6 : Math.max(4, (item.count / maxCategory) * 100)}%`,
                                                        }}
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </SectionCard>

                                <SectionCard
                                    headingId="tendencia"
                                    title="Tendencia mensual"
                                    description="Últimos seis meses. El mes en curso aún no termina."
                                >
                                    <figure>
                                        <div
                                            role="img"
                                            aria-label="Gráfica de barras con los reportes verificados de los últimos seis meses"
                                            className="flex h-48 items-end gap-2"
                                        >
                                            {data.months.map((month) => (
                                                <div
                                                    key={month.month}
                                                    className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                                >
                                                    <span className="text-xs font-semibold">
                                                        {month.count ??
                                                            `<${data.meta.minimum_group}`}
                                                    </span>
                                                    <div
                                                        className={cn(
                                                            'w-full rounded-t-md',
                                                            month.count === null
                                                                ? 'pattern-stripes h-2 text-purple outline-1 -outline-offset-1 outline-purple dark:text-lila dark:outline-lila'
                                                                : month.partial
                                                                  ? 'bg-mauve'
                                                                  : 'bg-purple dark:bg-lila',
                                                        )}
                                                        style={
                                                            month.count === null
                                                                ? undefined
                                                                : {
                                                                      height: `${Math.max(6, (month.count / maxMonth) * 80)}%`,
                                                                  }
                                                        }
                                                    />
                                                    <span className="text-xs text-muted-foreground">
                                                        {month.label}
                                                        {month.partial
                                                            ? '*'
                                                            : ''}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        <figcaption className="mt-2 text-xs text-muted-foreground">
                                            * Mes en curso (parcial). Las barras
                                            rayadas tienen menos de{' '}
                                            {data.meta.minimum_group} reportes.
                                        </figcaption>
                                    </figure>
                                </SectionCard>
                            </div>
                        )}

                        <DataNotice
                            source={data.meta.source}
                            period={
                                PERIODS.find(
                                    (item) => item.id === data.meta.period_days,
                                )?.label
                            }
                            sampleSize={data.meta.sample_size}
                            updatedAt={formatDate(data.meta.to)}
                        >
                            {data.meta.notice}
                        </DataNotice>
                    </>
                )}
            </div>
        </>
    );
}
