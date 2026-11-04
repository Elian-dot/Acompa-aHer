import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import FormErrors from '@/components/ruta/form-errors';
import LoadingBlock from '@/components/ruta/loading-block';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { apiErrorMessage } from '@/lib/api-errors';
import {
    confidenceLabel,
    formatDate,
    formatHour,
    formatHourRange,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { hourly_incidence } from '@/routes/api/v1';
import type { HourlyIncidence } from '@/types/journey';

const NIGHT = new Set([20, 21, 22, 23, 0, 1]);

export default function Horarios() {
    const http = useHttp<Record<string, never>, HourlyIncidence>({});
    const [showTable, setShowTable] = useState(false);
    const [problem, setProblem] = useState('');
    const data = http.response;

    const load = useEffectEvent(() => {
        void http
            .get(hourly_incidence.url())
            .catch((error: unknown) => setProblem(apiErrorMessage(error)));
    });
    const cancel = useEffectEvent(() => http.cancel());

    useEffect(() => {
        load();
        return () => cancel();
    }, []);

    const maximum = Math.max(
        1,
        ...(data?.hours.map((item) => item.count ?? 0) ?? [1]),
    );

    return (
        <>
            <Head title="Horarios" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Planear mi trayecto"
                    title="Horarios con más y menos reportes"
                    description="Mira a qué horas se han reportado y verificado más incidentes en Girardot. Es una señal de incidencia: no mide cuánta gente circula ni garantiza que una hora sea segura."
                    ayuda={
                        <p>
                            Cada barra cuenta los reportes verificados de los
                            últimos 90 días en esa hora. Las horas con menos de
                            5 reportes no se muestran con su cifra para proteger
                            la privacidad. Si hay pocos datos en total, te lo
                            decimos en lugar de inventar un patrón.
                        </p>
                    }
                />

                <FormErrors message={problem} />
                {!data && !problem && (
                    <LoadingBlock label="Consultando los reportes por hora" />
                )}

                {data && data.meta.status === 'insufficient_data' && (
                    <SectionCard
                        headingId="sin-datos"
                        title="Todavía no hay datos suficientes"
                    >
                        <p className="text-sm">
                            Hay {data.meta.sample_size} reportes verificados en
                            los últimos {data.meta.period_days} días y
                            necesitamos al menos {data.meta.minimum_sample} para
                            mostrar una distribución por hora sin sacar
                            conclusiones engañosas.
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Cuando se verifiquen más reportes de la comunidad,
                            esta sección se activará sola.
                        </p>
                    </SectionCard>
                )}

                {data && data.meta.status === 'available' && (
                    <>
                        <SectionCard
                            headingId="grafica"
                            title="Reportes verificados por hora"
                            action={
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowTable((value) => !value)
                                    }
                                    aria-pressed={showTable}
                                    className="rounded-full border px-4 py-1.5 text-sm font-medium hover:border-purple"
                                >
                                    {showTable
                                        ? 'Ver gráfica'
                                        : 'Ver como tabla'}
                                </button>
                            }
                        >
                            {showTable ? (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[20rem] text-left text-sm">
                                        <caption className="sr-only">
                                            Reportes verificados por hora en los
                                            últimos {data.meta.period_days} días
                                        </caption>
                                        <thead>
                                            <tr className="border-b">
                                                <th
                                                    scope="col"
                                                    className="py-2"
                                                >
                                                    Hora
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="py-2"
                                                >
                                                    Reportes verificados
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.hours.map((item) => (
                                                <tr
                                                    key={item.hour}
                                                    className="border-b last:border-0"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="py-2 font-medium"
                                                    >
                                                        {formatHour(item.hour)}
                                                    </th>
                                                    <td className="py-2">
                                                        {item.count ??
                                                            `menos de ${data.meta.minimum_group}`}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <figure>
                                    <div
                                        role="img"
                                        aria-label={`Gráfica de barras de reportes verificados por hora. Las horas con más reportes son: ${data.highest_hours.map(formatHour).join(', ')}.`}
                                        className="flex h-60 items-end gap-0.5 sm:gap-1.5"
                                    >
                                        {data.hours.map((item) => (
                                            <div
                                                key={item.hour}
                                                className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
                                            >
                                                <span className="text-[10px] font-semibold sm:text-xs">
                                                    {item.count ??
                                                        '<' +
                                                            data.meta
                                                                .minimum_group}
                                                </span>
                                                <div
                                                    className={cn(
                                                        'w-full rounded-t-md',
                                                        item.count === null
                                                            ? 'pattern-stripes h-2 text-purple outline-1 -outline-offset-1 outline-purple dark:text-lila dark:outline-lila'
                                                            : NIGHT.has(
                                                                    item.hour,
                                                                )
                                                              ? 'bg-purple dark:bg-lila'
                                                              : 'bg-mauve',
                                                    )}
                                                    style={
                                                        item.count === null
                                                            ? undefined
                                                            : {
                                                                  height: `${Math.max(6, (item.count / maximum) * 85)}%`,
                                                              }
                                                    }
                                                />
                                                <span className="text-[9px] text-muted-foreground sm:text-xs">
                                                    {item.hour}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <figcaption className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                        <span className="flex items-center gap-2">
                                            <span
                                                className="size-3 rounded-sm bg-purple dark:bg-lila"
                                                aria-hidden="true"
                                            />{' '}
                                            Franja de noche (8 p. m. a 2 a. m.)
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <span
                                                className="size-3 rounded-sm bg-mauve"
                                                aria-hidden="true"
                                            />{' '}
                                            Otras horas
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <span
                                                className="pattern-stripes size-3 rounded-sm text-purple outline-1 outline-purple dark:text-lila dark:outline-lila"
                                                aria-hidden="true"
                                            />{' '}
                                            Menos de {data.meta.minimum_group}{' '}
                                            reportes
                                        </span>
                                    </figcaption>
                                </figure>
                            )}
                        </SectionCard>

                        <div className="grid gap-6 md:grid-cols-2">
                            <SectionCard
                                headingId="lectura"
                                title="Qué muestran los datos"
                            >
                                <ul className="list-disc space-y-2 pl-5 text-sm">
                                    {data.highest_hours.length > 0 && (
                                        <li>
                                            Las horas con más reportes
                                            verificados son{' '}
                                            {data.highest_hours
                                                .map(formatHour)
                                                .join(', ')}
                                            .
                                        </li>
                                    )}
                                    {data.lowest_ranges.length > 0 && (
                                        <li>
                                            Con menos de{' '}
                                            {data.meta.minimum_group} reportes
                                            por hora:{' '}
                                            {data.lowest_ranges
                                                .map((range) =>
                                                    formatHourRange(
                                                        range.from,
                                                        range.to,
                                                    ),
                                                )
                                                .join('; ')}
                                            .
                                        </li>
                                    )}
                                    <li>
                                        Pocos reportes en una hora pueden
                                        significar poca gente en la calle o poca
                                        denuncia, no necesariamente más
                                        seguridad.
                                    </li>
                                </ul>
                            </SectionCard>
                            <SectionCard headingId="franjas" title="Por franja">
                                <ul className="space-y-2 text-sm">
                                    {data.bands.map((band) => (
                                        <li
                                            key={band.id}
                                            className="flex items-center justify-between gap-3 rounded-xl border px-3 py-2"
                                        >
                                            <span>
                                                <span className="font-semibold">
                                                    {band.label}
                                                </span>{' '}
                                                <span className="text-muted-foreground">
                                                    ({formatHour(band.from)} a{' '}
                                                    {formatHour(
                                                        (band.to + 1) % 24,
                                                    )}
                                                    )
                                                </span>
                                            </span>
                                            <span className="font-semibold">
                                                {band.count ??
                                                    `<${data.meta.minimum_group}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </SectionCard>
                        </div>
                    </>
                )}

                {data && (
                    <DataNotice
                        source={data.meta.source}
                        period={`Últimos ${data.meta.period_days} días`}
                        sampleSize={data.meta.sample_size}
                        confidence={confidenceLabel[data.meta.confidence]}
                        updatedAt={formatDate(data.meta.to)}
                    >
                        {data.meta.notice}
                    </DataNotice>
                )}
            </div>
        </>
    );
}
