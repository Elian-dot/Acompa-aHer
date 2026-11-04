import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import SimulationBanner from '@/components/ruta/simulation-banner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { CONTACTOS_EJEMPLO } from '@/lib/ruta';

type Fase = 'espera' | 'detenida' | 'aviso' | 'alerta' | 'bien';

const AVISO_SEGUNDOS = 10;

export default function Inactividad() {
    const [minutos, setMinutos] = useState(10);
    const [fase, setFase] = useState<Fase>('espera');
    const [restante, setRestante] = useState(AVISO_SEGUNDOS);

    useEffect(() => {
        if (fase === 'detenida') {
            const id = setTimeout(() => {
                setRestante(AVISO_SEGUNDOS);
                setFase('aviso');
            }, 2500);

            return () => clearTimeout(id);
        }

        if (fase === 'aviso') {
            if (restante <= 0) {
                setFase('alerta');

                return;
            }

            const id = setTimeout(() => setRestante((r) => r - 1), 1000);

            return () => clearTimeout(id);
        }
    }, [fase, restante]);

    return (
        <>
            <Head title="Alerta por inactividad" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Acompañarme"
                    title="Alerta por inactividad"
                    description="Si te detienes durante el tiempo que definas y no confirmas que estás bien, avisamos a tus contactos de confianza. Siempre te damos oportunidad de cancelar."
                    ayuda={
                        <p>
                            Primero te mostramos un aviso con cuenta atrás. Si
                            pulsas “Estoy bien” o “Cancelar”, nadie recibe nada.
                            Solo si no respondes se notificará a tus contactos.
                        </p>
                    }
                />

                <SimulationBanner>
                    Aquí puedes ensayar el flujo con una detención simulada. No
                    se envían avisos reales.
                </SimulationBanner>

                <SectionCard headingId="config" title="Configura el tiempo">
                    <div className="grid gap-2">
                        <Label htmlFor="minutos">
                            Avisar si me detengo más de:{' '}
                            <strong>{minutos} min</strong>
                        </Label>
                        <input
                            id="minutos"
                            type="range"
                            min={3}
                            max={30}
                            step={1}
                            value={minutos}
                            onChange={(e) => setMinutos(Number(e.target.value))}
                            disabled={fase !== 'espera' && fase !== 'bien'}
                            className="w-full accent-[#6d28a8]"
                            aria-describedby="ayuda-min"
                        />
                        <p
                            id="ayuda-min"
                            className="text-sm text-muted-foreground"
                        >
                            Antes de avisar a tus contactos te mostraremos un
                            aviso de {AVISO_SEGUNDOS} segundos. Entre 3 y 30
                            minutos.
                        </p>
                    </div>
                </SectionCard>

                <SectionCard headingId="simulacion" title="Ensáyalo">
                    <div aria-live="polite" className="space-y-4">
                        {(fase === 'espera' || fase === 'bien') && (
                            <>
                                {fase === 'bien' && (
                                    <p className="rounded-2xl bg-secondary p-4 text-sm font-medium">
                                        Alerta cancelada. Tus contactos no
                                        recibieron nada.
                                    </p>
                                )}
                                <Button
                                    className="rounded-full"
                                    onClick={() => setFase('detenida')}
                                >
                                    Simular que me detuve {minutos} minutos
                                </Button>
                            </>
                        )}
                        {fase === 'detenida' && (
                            <p className="text-sm">
                                Detectamos que llevas {minutos} minutos sin
                                moverte…
                            </p>
                        )}
                        {fase === 'aviso' && (
                            <div
                                role="alertdialog"
                                aria-labelledby="titulo-aviso"
                                className="rounded-3xl border-2 border-gold bg-gold/15 p-6 text-center"
                            >
                                <h3
                                    id="titulo-aviso"
                                    className="text-xl font-semibold"
                                >
                                    ¿Estás bien?
                                </h3>
                                <p className="mt-2">
                                    Avisaremos a tus contactos en{' '}
                                    <strong className="text-2xl tabular-nums">
                                        {restante}
                                    </strong>{' '}
                                    s si no respondes.
                                </p>
                                <div className="mt-4 flex flex-wrap justify-center gap-3">
                                    <Button
                                        size="lg"
                                        className="rounded-full"
                                        onClick={() => setFase('bien')}
                                    >
                                        Estoy bien
                                    </Button>
                                    <Button
                                        size="lg"
                                        variant="outline"
                                        className="rounded-full"
                                        onClick={() => setFase('bien')}
                                    >
                                        Cancelar alerta
                                    </Button>
                                </div>
                            </div>
                        )}
                        {fase === 'alerta' && (
                            <div className="rounded-3xl border-2 border-[#b42336] p-6">
                                <h3 className="text-xl font-semibold">
                                    Alerta simulada
                                </h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    En la versión real se notificaría a estos
                                    contactos:
                                </p>
                                <ul className="mt-3 space-y-2 text-sm">
                                    {CONTACTOS_EJEMPLO.map((c) => (
                                        <li
                                            key={c.id}
                                            className="flex justify-between rounded-xl border p-3"
                                        >
                                            <span>{c.nombre}</span>
                                            <span className="font-semibold">
                                                Pendiente (simulación)
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <Button
                                    className="mt-4 rounded-full"
                                    onClick={() => setFase('espera')}
                                >
                                    Reiniciar ensayo
                                </Button>
                            </div>
                        )}
                    </div>
                </SectionCard>

                <SectionCard headingId="casos" title="Qué tenemos en cuenta">
                    <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                        <li>
                            GPS impreciso: no avisamos por pequeños movimientos
                            de la señal.
                        </li>
                        <li>
                            Detenciones voluntarias: puedes pausar la alerta si
                            vas a quedarte en un lugar.
                        </li>
                        <li>
                            Pérdida de conexión: te mostraremos si el aviso no
                            pudo entregarse.
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </>
    );
}
