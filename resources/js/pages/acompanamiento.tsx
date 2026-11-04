import { Head } from '@inertiajs/react';
import {
    BatteryLow,
    Eye,
    Pause,
    Play,
    Radio,
    Square,
    WifiOff,
} from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import SimulationBanner from '@/components/ruta/simulation-banner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { CONTACTOS_EJEMPLO } from '@/lib/ruta';

type Estado = 'detenido' | 'activo' | 'pausado';

const DURACIONES = [
    { id: '30', etiqueta: '30 minutos' },
    { id: '60', etiqueta: '1 hora' },
    { id: '120', etiqueta: '2 horas' },
];

export default function Acompanamiento() {
    const [seleccion, setSeleccion] = useState<string[]>([]);
    const [duracion, setDuracion] = useState('60');
    const [estado, setEstado] = useState<Estado>('detenido');
    const [autorizo, setAutorizo] = useState(false);
    const [error, setError] = useState('');

    const alternar = (id: string) =>
        setSeleccion((actual) =>
            actual.includes(id)
                ? actual.filter((x) => x !== id)
                : [...actual, id],
        );

    const iniciar = () => {
        if (seleccion.length === 0) {
            setError('Elige al menos una persona de confianza.');

            return;
        }

        if (!autorizo) {
            setError('Confirma que autorizas compartir tu ubicación.');

            return;
        }

        setError('');
        setEstado('activo');
    };

    const quienes = CONTACTOS_EJEMPLO.filter((c) =>
        seleccion.includes(c.id),
    ).map((c) => c.nombre);

    return (
        <>
            <Head title="Acompañamiento virtual" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Acompañarme"
                    title="Acompañamiento en tiempo real"
                    description="Comparte tu ubicación en vivo, solo con las personas que tú elijas y solo durante tu trayecto."
                    ayuda={
                        <p>
                            Elige a quién avisar, por cuánto tiempo y pulsa
                            iniciar. Podrás pausar o terminar cuando quieras. No
                            guardamos historial de tu recorrido más allá de lo
                            necesario.
                        </p>
                    }
                />

                <SimulationBanner>
                    La ubicación en vivo está simulada: no se comparte con nadie
                    todavía.
                </SimulationBanner>

                {estado !== 'detenido' && (
                    <div
                        role="status"
                        aria-live="polite"
                        className="bg-brand-gradient text-warm-white flex flex-wrap items-center justify-between gap-4 rounded-3xl p-5"
                    >
                        <div className="flex items-center gap-3">
                            <Radio
                                className="size-6 text-lila"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-semibold">
                                    {estado === 'activo'
                                        ? 'Compartiendo tu ubicación (simulado)'
                                        : 'Ubicación en pausa'}
                                </p>
                                <p className="text-warm-white/80 flex items-center gap-1.5 text-sm">
                                    <Eye
                                        className="size-4"
                                        aria-hidden="true"
                                    />{' '}
                                    La ven: {quienes.join(', ')}
                                </p>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            {estado === 'activo' ? (
                                <Button
                                    variant="secondary"
                                    className="rounded-full"
                                    onClick={() => setEstado('pausado')}
                                >
                                    <Pause aria-hidden="true" /> Pausar
                                </Button>
                            ) : (
                                <Button
                                    variant="secondary"
                                    className="rounded-full"
                                    onClick={() => setEstado('activo')}
                                >
                                    <Play aria-hidden="true" /> Reanudar
                                </Button>
                            )}
                            <Button
                                className="rounded-full bg-[#b42336] text-white hover:bg-[#9a1d2e]"
                                onClick={() => setEstado('detenido')}
                            >
                                <Square aria-hidden="true" /> Terminar
                            </Button>
                        </div>
                    </div>
                )}

                <SectionCard
                    headingId="contactos"
                    title="¿Con quién la compartes?"
                    description="Solo ven tu ubicación las personas que marques aquí."
                >
                    <fieldset
                        disabled={estado !== 'detenido'}
                        className="grid gap-3 sm:grid-cols-3"
                    >
                        <legend className="sr-only">
                            Contactos autorizados
                        </legend>
                        {CONTACTOS_EJEMPLO.map((c) => (
                            <div
                                key={c.id}
                                className="flex items-center gap-3 rounded-2xl border p-4"
                            >
                                <Checkbox
                                    id={`c-${c.id}`}
                                    checked={seleccion.includes(c.id)}
                                    onCheckedChange={() => alternar(c.id)}
                                />
                                <Label
                                    htmlFor={`c-${c.id}`}
                                    className="flex flex-col font-normal"
                                >
                                    <span className="font-semibold">
                                        {c.nombre}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {c.vinculo}
                                    </span>
                                </Label>
                            </div>
                        ))}
                    </fieldset>
                </SectionCard>

                <SectionCard
                    headingId="duracion"
                    title="Duración y consentimiento"
                >
                    <div className="grid gap-4">
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="duracion">
                                Vigencia del seguimiento
                            </Label>
                            <select
                                id="duracion"
                                value={duracion}
                                onChange={(e) => setDuracion(e.target.value)}
                                disabled={estado !== 'detenido'}
                                className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
                            >
                                {DURACIONES.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.etiqueta}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="autorizo"
                                checked={autorizo}
                                onCheckedChange={(v) => setAutorizo(v === true)}
                                disabled={estado !== 'detenido'}
                                className="mt-0.5"
                            />
                            <Label htmlFor="autorizo" className="font-normal">
                                Autorizo compartir mi ubicación en vivo con las
                                personas elegidas durante{' '}
                                {
                                    DURACIONES.find((d) => d.id === duracion)
                                        ?.etiqueta
                                }
                                . Puedo detenerlo cuando quiera.
                            </Label>
                        </div>
                        <p
                            role="alert"
                            className="text-sm text-red-700 dark:text-red-300"
                        >
                            {error}
                        </p>
                        <div>
                            <Button
                                size="lg"
                                className="rounded-full"
                                onClick={iniciar}
                                disabled={estado !== 'detenido'}
                            >
                                Iniciar acompañamiento
                            </Button>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard headingId="estado" title="Estado del dispositivo">
                    <ul className="grid gap-3 text-sm sm:grid-cols-3">
                        <li className="flex items-center gap-2 rounded-2xl border p-3">
                            <Radio className="size-4" aria-hidden="true" /> GPS:
                            señal buena
                        </li>
                        <li className="flex items-center gap-2 rounded-2xl border p-3">
                            <WifiOff className="size-4" aria-hidden="true" />{' '}
                            Conexión: estable
                        </li>
                        <li className="flex items-center gap-2 rounded-2xl border p-3">
                            <BatteryLow className="size-4" aria-hidden="true" />{' '}
                            Batería: te avisaremos si baja del 15 %
                        </li>
                    </ul>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Si se pierde la señal, tus contactos verán “última
                        ubicación conocida” con la hora, no una ubicación en
                        vivo.
                    </p>
                </SectionCard>
            </div>
        </>
    );
}
