import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    MapPinOff,
    Phone,
    ShieldAlert,
    WifiOff,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import SimulationBanner from '@/components/ruta/simulation-banner';
import { Button } from '@/components/ui/button';
import { CONTACTOS_EJEMPLO } from '@/lib/ruta';

type Fase = 'inactivo' | 'cuenta-atras' | 'enviando' | 'resultado';

const SEGUNDOS = 5;
const MENSAJE =
    'Necesito ayuda. Esta es mi ubicación actual. Por favor, comunícate conmigo.';

export default function Sos() {
    const [fase, setFase] = useState<Fase>('inactivo');
    const [segundos, setSegundos] = useState(SEGUNDOS);
    useEffect(() => {
        if (fase === 'cuenta-atras') {
            if (segundos <= 0) {
                setFase('enviando');

                return;
            }

            const id = setTimeout(() => setSegundos((s) => s - 1), 1000);

            return () => clearTimeout(id);
        }

        if (fase === 'enviando') {
            const id = setTimeout(() => setFase('resultado'), 1500);

            return () => clearTimeout(id);
        }
    }, [fase, segundos]);

    const iniciar = () => {
        setSegundos(SEGUNDOS);
        setFase('cuenta-atras');
    };

    const cancelar = () => setFase('inactivo');

    return (
        <>
            <Head title="Botón SOS" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Acompañarme"
                    title="Botón SOS"
                    description="Acceso rápido a números de emergencia y a tus contactos de confianza. Diseñado para usarse con una sola mano y bajo estrés."
                />

                <SimulationBanner>
                    Este botón funciona en simulación: no llama ni escribe a
                    ningún servicio ni persona real. Si estás en peligro
                    inmediato, llama al 123 desde tu teléfono.
                </SimulationBanner>

                <a
                    href="tel:123"
                    className="flex items-center justify-center gap-3 rounded-3xl bg-[#b42336] p-6 text-xl font-bold text-white shadow-lg transition hover:bg-[#9a1d2e]"
                >
                    <Phone className="size-6" aria-hidden="true" />
                    Llamar al 123 (emergencias)
                </a>

                <SectionCard
                    headingId="que-hace"
                    title="¿Qué hará el botón SOS?"
                >
                    <ol className="list-decimal space-y-2 pl-5 text-sm">
                        <li>
                            Tienes {SEGUNDOS} segundos para cancelar antes de
                            que se ejecute.
                        </li>
                        <li>Obtiene tu ubicación GPS aproximada.</li>
                        <li>
                            Envía este mensaje a tus contactos de emergencia:
                        </li>
                    </ol>
                    <blockquote className="border-purple mt-3 rounded-2xl border-l-4 bg-secondary/60 p-4 text-sm italic">
                        “{MENSAJE}”
                    </blockquote>
                    <ul
                        className="mt-4 flex flex-wrap gap-2"
                        aria-label="Contactos de emergencia"
                    >
                        {CONTACTOS_EJEMPLO.map((c) => (
                            <li
                                key={c.id}
                                className="flex items-center gap-2 rounded-full border bg-background px-3 py-1.5 text-sm"
                            >
                                <span
                                    className="bg-purple text-warm-white flex size-6 items-center justify-center rounded-full text-[10px] font-bold"
                                    aria-hidden="true"
                                >
                                    {c.iniciales}
                                </span>
                                {c.nombre}
                            </li>
                        ))}
                    </ul>
                </SectionCard>

                <section
                    aria-labelledby="activar"
                    className="rounded-3xl border bg-card p-6 text-center shadow-sm"
                    aria-live="polite"
                >
                    <h2 id="activar" className="sr-only">
                        Activar SOS simulado
                    </h2>

                    {fase === 'inactivo' && (
                        <>
                            <button
                                type="button"
                                onClick={iniciar}
                                className="mx-auto flex size-44 flex-col items-center justify-center gap-2 rounded-full bg-[#b42336] text-white shadow-xl ring-8 ring-[#b42336]/25 transition hover:bg-[#9a1d2e] focus-visible:ring-lila"
                            >
                                <ShieldAlert
                                    className="size-12"
                                    aria-hidden="true"
                                />
                                <span className="text-2xl font-bold">SOS</span>
                                <span className="text-xs font-medium">
                                    Simulación
                                </span>
                            </button>
                            <p className="mt-4 text-sm text-muted-foreground">
                                Toca para iniciar la cuenta atrás de {SEGUNDOS}{' '}
                                segundos.
                            </p>
                        </>
                    )}

                    {fase === 'cuenta-atras' && (
                        <div className="space-y-4">
                            <p className="text-6xl font-bold tabular-nums">
                                {segundos}
                            </p>
                            <p className="text-lg font-semibold">
                                Enviando alerta simulada en {segundos} segundos…
                            </p>
                            <Button
                                size="lg"
                                variant="outline"
                                className="rounded-full"
                                onClick={cancelar}
                            >
                                Cancelar
                            </Button>
                        </div>
                    )}

                    {fase === 'enviando' && (
                        <p className="text-lg font-semibold">
                            Obteniendo ubicación y preparando el mensaje…
                        </p>
                    )}

                    {fase === 'resultado' && (
                        <div className="space-y-4">
                            <p className="text-lg font-semibold">
                                Simulación completada
                            </p>
                            <ul className="mx-auto max-w-sm space-y-2 text-left text-sm">
                                {CONTACTOS_EJEMPLO.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex items-center justify-between rounded-xl border p-3"
                                    >
                                        <span>{c.nombre}</span>
                                        <span className="font-semibold">
                                            No enviado (simulación)
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            <p className="text-sm text-muted-foreground">
                                En la versión real verías aquí “Entregado” o
                                “Falló” por contacto. Nunca mostraremos
                                “enviado” sin confirmarlo.
                            </p>
                            <Button
                                className="rounded-full"
                                onClick={() => setFase('inactivo')}
                            >
                                Volver a empezar
                            </Button>
                        </div>
                    )}
                </section>

                <SectionCard
                    headingId="alternativas"
                    title="Si algo falla"
                    description="Alternativas cuando no hay GPS, conexión o batería."
                >
                    <ul className="grid gap-3 sm:grid-cols-3">
                        <li className="rounded-2xl border p-4 text-sm">
                            <MapPinOff
                                className="text-purple mb-2 size-5 dark:text-lila"
                                aria-hidden="true"
                            />
                            <strong>Sin GPS:</strong> el mensaje se enviará sin
                            ubicación y avisará que no pudimos obtenerla.
                        </li>
                        <li className="rounded-2xl border p-4 text-sm">
                            <WifiOff
                                className="text-purple mb-2 size-5 dark:text-lila"
                                aria-hidden="true"
                            />
                            <strong>Sin conexión:</strong> intenta un SMS o
                            llama directamente al 123.
                        </li>
                        <li className="rounded-2xl border p-4 text-sm">
                            <AlertTriangle
                                className="text-purple mb-2 size-5 dark:text-lila"
                                aria-hidden="true"
                            />
                            <strong>Batería baja:</strong> prioriza una llamada;
                            es más rápida que cualquier mensaje.
                        </li>
                    </ul>
                    <p className="mt-4 text-sm text-muted-foreground">
                        Línea Púrpura (orientación a mujeres): verifica los
                        canales, horarios y cobertura de tu municipio antes de
                        depender de ella.
                    </p>
                </SectionCard>
            </div>
        </>
    );
}
