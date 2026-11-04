import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { cn } from '@/lib/utils';

type Franja = {
    hora: string;
    flujo: number;
    datos: 'suficientes' | 'insuficientes';
};

const FRANJAS: Franja[] = [
    { hora: '6 p. m.', flujo: 92, datos: 'suficientes' },
    { hora: '7 p. m.', flujo: 85, datos: 'suficientes' },
    { hora: '8 p. m.', flujo: 70, datos: 'suficientes' },
    { hora: '9 p. m.', flujo: 54, datos: 'suficientes' },
    { hora: '10 p. m.', flujo: 38, datos: 'suficientes' },
    { hora: '11 p. m.', flujo: 24, datos: 'suficientes' },
    { hora: '12 a. m.', flujo: 14, datos: 'suficientes' },
    { hora: '1 a. m.', flujo: 8, datos: 'insuficientes' },
    { hora: '2 a. m.', flujo: 5, datos: 'insuficientes' },
    { hora: '3 a. m.', flujo: 4, datos: 'insuficientes' },
    { hora: '4 a. m.', flujo: 9, datos: 'insuficientes' },
];

const etiquetaFlujo = (flujo: number) =>
    flujo >= 70 ? 'Alto' : flujo >= 35 ? 'Medio' : 'Bajo';

export default function Horarios() {
    const [verTabla, setVerTabla] = useState(false);

    return (
        <>
            <Head title="Horarios seguros" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Planear mi trayecto"
                    title="Franjas con más flujo peatonal"
                    description="Ver cuándo hay más personas en la calle puede ayudarte a elegir mejor tu hora de regreso. Más gente no garantiza seguridad."
                    ayuda={
                        <p>
                            La gráfica muestra el flujo peatonal relativo por
                            hora. Las barras con patrón de rayas indican que hay
                            pocos datos y la recomendación es menos confiable.
                        </p>
                    }
                />

                <SectionCard
                    headingId="grafica"
                    title="Flujo peatonal por hora"
                    action={
                        <button
                            type="button"
                            onClick={() => setVerTabla((v) => !v)}
                            aria-pressed={verTabla}
                            className="hover:border-purple rounded-full border px-4 py-1.5 text-sm font-medium"
                        >
                            {verTabla ? 'Ver gráfica' : 'Ver como tabla'}
                        </button>
                    }
                >
                    {verTabla ? (
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Flujo peatonal por hora
                            </caption>
                            <thead>
                                <tr className="border-b">
                                    <th scope="col" className="py-2">
                                        Hora
                                    </th>
                                    <th scope="col" className="py-2">
                                        Flujo
                                    </th>
                                    <th scope="col" className="py-2">
                                        Datos
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {FRANJAS.map((f) => (
                                    <tr
                                        key={f.hora}
                                        className="border-b last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className="py-2 font-medium"
                                        >
                                            {f.hora}
                                        </th>
                                        <td className="py-2">
                                            {etiquetaFlujo(f.flujo)} ({f.flujo})
                                        </td>
                                        <td className="py-2 capitalize">
                                            {f.datos}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <figure>
                            <div
                                role="img"
                                aria-label="Gráfica de barras del flujo peatonal por hora. Es más alto entre 6 y 8 p. m. y desciende después de las 10 p. m."
                                className="flex h-56 items-end gap-1.5 sm:gap-3"
                            >
                                {FRANJAS.map((f) => (
                                    <div
                                        key={f.hora}
                                        className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                                    >
                                        <span className="text-xs font-semibold">
                                            {f.flujo}
                                        </span>
                                        <div
                                            className={cn(
                                                'bg-purple w-full rounded-t-lg',
                                                f.datos === 'insuficientes' &&
                                                    'pattern-stripes text-purple outline-purple bg-transparent outline-2 -outline-offset-2 dark:text-lila dark:outline-lila',
                                            )}
                                            style={{
                                                height: `${f.flujo * 0.8}%`,
                                            }}
                                        />
                                        <span className="text-[10px] text-muted-foreground sm:text-xs">
                                            {f.hora}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <figcaption className="mt-3 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-2">
                                    <span
                                        className="bg-purple size-3 rounded-sm"
                                        aria-hidden="true"
                                    />
                                    Datos suficientes
                                </span>
                                <span className="flex items-center gap-2">
                                    <span
                                        className="pattern-stripes text-purple outline-purple size-3 rounded-sm outline-1 dark:text-lila"
                                        aria-hidden="true"
                                    />
                                    Datos insuficientes
                                </span>
                            </figcaption>
                        </figure>
                    )}
                </SectionCard>

                <div className="grid gap-6 md:grid-cols-2">
                    <SectionCard headingId="reco" title="Recomendaciones">
                        <ul className="list-disc space-y-2 pl-5 text-sm">
                            <li>
                                Entre las <strong>6 y las 8 p. m.</strong> hay
                                más flujo peatonal en las zonas de ejemplo.
                            </li>
                            <li>
                                Después de las <strong>10 p. m.</strong> el
                                flujo baja de forma marcada. Si puedes, planea
                                tu regreso antes o acompañada.
                            </li>
                            <li>
                                Recomendación basada en conteos agregados de los
                                últimos 30 días.
                            </li>
                        </ul>
                    </SectionCard>
                    <SectionCard
                        headingId="limites"
                        title="Cuándo no hay datos suficientes"
                    >
                        <p className="text-sm text-muted-foreground">
                            Entre la 1 y las 4 a. m. tenemos muy pocos datos. En
                            esas horas no damos recomendaciones: te sugerimos
                            activar el acompañamiento virtual.
                        </p>
                    </SectionCard>
                </div>

                <DataNotice periodo="Últimos 30 días" confianza="Media">
                    Mayor flujo peatonal no equivale a mayor seguridad. Usa esta
                    información junto con tu criterio y otras herramientas.
                </DataNotice>
            </div>
        </>
    );
}
