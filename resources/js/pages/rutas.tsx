import { Head } from '@inertiajs/react';
import { Footprints, Lightbulb, Store, Timer } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import DataNotice from '@/components/ruta/data-notice';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Nivel = 'Buena' | 'Media' | 'Baja';

type RutaEjemplo = {
    id: string;
    nombre: string;
    minutos: number;
    metros: number;
    iluminacion: Nivel;
    peatones: Nivel;
    comercios: number;
    confianza: 'Baja' | 'Media' | 'Alta';
    porque: string;
    recomendada?: boolean;
};

const RUTAS: RutaEjemplo[] = [
    {
        id: 'a',
        nombre: 'Ruta A · Avenida principal',
        minutos: 22,
        metros: 1800,
        iluminacion: 'Buena',
        peatones: 'Buena',
        comercios: 6,
        confianza: 'Media',
        recomendada: true,
        porque: 'Mejor valorada: tiene buena iluminación reportada, más personas caminando a esta hora y 6 comercios abiertos reportados. Tarda 4 minutos más que la ruta más corta.',
    },
    {
        id: 'b',
        nombre: 'Ruta B · Calle del parque',
        minutos: 18,
        metros: 1500,
        iluminacion: 'Media',
        peatones: 'Media',
        comercios: 2,
        confianza: 'Baja',
        porque: 'Es más corta, pero tiene tramos con iluminación media y pocos comercios abiertos. La confianza de los datos es baja: hay pocos reportes recientes.',
    },
    {
        id: 'c',
        nombre: 'Ruta C · Atajo por el callejón',
        minutos: 14,
        metros: 1200,
        iluminacion: 'Baja',
        peatones: 'Baja',
        comercios: 0,
        confianza: 'Media',
        porque: 'Es la más rápida, pero se reportó poca iluminación y poco flujo peatonal. Considera otra alternativa si vas sola de noche.',
    },
];

const claseNivel: Record<Nivel, string> = {
    Buena: 'border-[#2e8b57]',
    Media: 'border-[#d9a400]',
    Baja: 'border-[#d64545]',
};

export default function Rutas() {
    const [origen, setOrigen] = useState('');
    const [destino, setDestino] = useState('');
    const [buscado, setBuscado] = useState(false);
    const [seleccion, setSeleccion] = useState<string>('a');
    const [error, setError] = useState('');

    const buscar = (evento: FormEvent) => {
        evento.preventDefault();

        if (!origen.trim() || !destino.trim()) {
            setError('Escribe un origen y un destino para ver alternativas.');
            setBuscado(false);

            return;
        }

        setError('');
        setBuscado(true);
    };

    const elegida = RUTAS.find((ruta) => ruta.id === seleccion) ?? RUTAS[0];

    return (
        <>
            <Head title="Rutas seguras" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Planear mi trayecto"
                    title="Compara rutas antes de salir"
                    description="Sugerimos alternativas según la iluminación, el flujo peatonal y los comercios abiertos reportados por la comunidad. Ninguna ruta es completamente segura: tú decides."
                    ayuda={
                        <p>
                            Escribe de dónde sales y a dónde vas. Verás varias
                            alternativas con su tiempo, distancia y la razón por
                            la que se recomienda cada una. Puedes comparar y
                            elegir la que prefieras.
                        </p>
                    }
                />

                <SectionCard headingId="buscar" title="¿A dónde vas?">
                    <form
                        onSubmit={buscar}
                        className="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end"
                        noValidate
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="origen">Origen</Label>
                            <Input
                                id="origen"
                                value={origen}
                                onChange={(e) => setOrigen(e.target.value)}
                                placeholder="Ej. Universidad"
                                autoComplete="off"
                                aria-describedby="error-ruta"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="destino">Destino</Label>
                            <Input
                                id="destino"
                                value={destino}
                                onChange={(e) => setDestino(e.target.value)}
                                placeholder="Ej. Mi casa"
                                autoComplete="off"
                                aria-describedby="error-ruta"
                            />
                        </div>
                        <Button
                            type="submit"
                            size="lg"
                            className="rounded-full"
                        >
                            Ver alternativas
                        </Button>
                        <p
                            id="error-ruta"
                            role="alert"
                            className="text-sm text-red-700 md:col-span-3 dark:text-red-300"
                        >
                            {error}
                        </p>
                    </form>
                </SectionCard>

                {!buscado && (
                    <div className="rounded-3xl border border-dashed p-8 text-center text-muted-foreground">
                        Aún no hay una búsqueda. Completa origen y destino para
                        comparar rutas de ejemplo.
                    </div>
                )}

                {buscado && (
                    <>
                        <div
                            role="radiogroup"
                            aria-label="Alternativas de ruta"
                            className="grid gap-4 lg:grid-cols-3"
                        >
                            {RUTAS.map((ruta) => (
                                <button
                                    key={ruta.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={seleccion === ruta.id}
                                    onClick={() => setSeleccion(ruta.id)}
                                    className={cn(
                                        'hover:border-purple rounded-3xl border bg-card p-5 text-left shadow-sm transition',
                                        seleccion === ruta.id &&
                                            'border-purple border-2 ring-2 ring-lila/40',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <h2 className="text-lg font-semibold">
                                            {ruta.nombre}
                                        </h2>
                                        {ruta.recomendada && (
                                            <span className="rounded-full bg-gold/30 px-2.5 py-1 text-xs font-bold">
                                                Mejor valorada
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                        <Timer
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {ruta.minutos} min · {ruta.metros} m
                                    </p>
                                    <ul className="mt-4 space-y-2 text-sm">
                                        <Indicador
                                            icono={Lightbulb}
                                            etiqueta="Iluminación"
                                            nivel={ruta.iluminacion}
                                        />
                                        <Indicador
                                            icono={Footprints}
                                            etiqueta="Actividad peatonal"
                                            nivel={ruta.peatones}
                                        />
                                        <li className="flex items-center gap-2">
                                            <Store
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Comercios abiertos reportados:{' '}
                                            <strong>{ruta.comercios}</strong>
                                        </li>
                                    </ul>
                                </button>
                            ))}
                        </div>

                        <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
                            <SectionCard
                                headingId="mapa-ruta"
                                title="Mapa de la ruta"
                                description="Vista ilustrativa. El resumen en texto está a la derecha."
                            >
                                <MapaIlustrativo seleccion={seleccion} />
                            </SectionCard>

                            <SectionCard
                                headingId="resumen"
                                title={`¿Por qué ${elegida.nombre.split(' · ')[0]}?`}
                            >
                                <p className="text-sm">{elegida.porque}</p>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Resumen: {elegida.minutos} minutos,{' '}
                                    {elegida.metros} metros, iluminación{' '}
                                    {elegida.iluminacion.toLowerCase()},
                                    actividad peatonal{' '}
                                    {elegida.peatones.toLowerCase()} y{' '}
                                    {elegida.comercios} comercios abiertos
                                    reportados.
                                </p>
                            </SectionCard>
                        </div>

                        <SectionCard headingId="comparar" title="Comparación">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[32rem] text-left text-sm">
                                    <caption className="sr-only">
                                        Comparación de las tres rutas
                                    </caption>
                                    <thead>
                                        <tr className="border-b">
                                            <th
                                                scope="col"
                                                className="py-2 pr-4"
                                            >
                                                Ruta
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 pr-4"
                                            >
                                                Tiempo
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 pr-4"
                                            >
                                                Iluminación
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 pr-4"
                                            >
                                                Peatones
                                            </th>
                                            <th scope="col" className="py-2">
                                                Confianza
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {RUTAS.map((ruta) => (
                                            <tr
                                                key={ruta.id}
                                                className="border-b last:border-0"
                                            >
                                                <th
                                                    scope="row"
                                                    className="py-2 pr-4 font-semibold"
                                                >
                                                    {
                                                        ruta.nombre.split(
                                                            ' · ',
                                                        )[0]
                                                    }
                                                </th>
                                                <td className="py-2 pr-4">
                                                    {ruta.minutos} min
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {ruta.iluminacion}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {ruta.peatones}
                                                </td>
                                                <td className="py-2">
                                                    {ruta.confianza}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </SectionCard>

                        <DataNotice
                            periodo="Últimos 30 días"
                            confianza={elegida.confianza}
                        >
                            Las rutas se calculan con reportes de la comunidad y
                            pueden estar desactualizadas. Una ruta bien valorada
                            no es una ruta libre de riesgo.
                        </DataNotice>
                    </>
                )}
            </div>
        </>
    );
}

function Indicador({
    icono: Icono,
    etiqueta,
    nivel,
}: {
    icono: typeof Lightbulb;
    etiqueta: string;
    nivel: Nivel;
}) {
    return (
        <li className="flex items-center gap-2">
            <Icono className="size-4" aria-hidden="true" />
            <span>{etiqueta}:</span>
            <span
                className={cn(
                    'rounded-full border-2 px-2 py-0.5 text-xs font-bold',
                    claseNivel[nivel],
                )}
            >
                {nivel}
            </span>
        </li>
    );
}

function MapaIlustrativo({ seleccion }: { seleccion: string }) {
    const trazos: Record<string, string> = {
        a: 'M30 200 C 90 190, 120 120, 200 110 S 320 60, 380 40',
        b: 'M30 200 C 80 220, 160 180, 220 150 S 330 90, 380 40',
        c: 'M30 200 L 380 40',
    };

    return (
        <svg
            viewBox="0 0 410 240"
            role="img"
            aria-label="Mapa ilustrativo con las tres rutas alternativas entre el origen y el destino"
            className="w-full rounded-2xl bg-deep-plum"
        >
            <g stroke="#ffffff1a" strokeWidth="1">
                {Array.from({ length: 8 }, (_, i) => (
                    <line
                        key={`v${i}`}
                        x1={i * 60}
                        y1="0"
                        x2={i * 60}
                        y2="240"
                    />
                ))}
                {Array.from({ length: 5 }, (_, i) => (
                    <line
                        key={`h${i}`}
                        x1="0"
                        y1={i * 60}
                        x2="410"
                        y2={i * 60}
                    />
                ))}
            </g>
            {Object.entries(trazos).map(([id, d]) => (
                <path
                    key={id}
                    d={d}
                    fill="none"
                    stroke={id === seleccion ? '#e395f2' : '#796c7d'}
                    strokeWidth={id === seleccion ? 5 : 3}
                    strokeDasharray={
                        id === 'b' ? '8 6' : id === 'c' ? '2 6' : undefined
                    }
                    strokeLinecap="round"
                />
            ))}
            <circle cx="30" cy="200" r="9" fill="#fdfcfb" />
            <text x="46" y="204" fill="#fdfcfb" fontSize="12">
                Origen
            </text>
            <circle cx="380" cy="40" r="9" fill="#f6ad69" />
            <text x="330" y="26" fill="#fdfcfb" fontSize="12">
                Destino
            </text>
        </svg>
    );
}
