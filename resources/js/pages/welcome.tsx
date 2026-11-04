import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    Clock,
    FilePenLine,
    Flame,
    HeartHandshake,
    Lock,
    Plus,
    Route,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import ThemeToggle from '@/components/theme-toggle';
import { dashboard, login, register } from '@/routes';
import { mapa, rutas } from '@/routes/public';

const NAV = [
    { href: '#inicio', texto: 'Inicio' },
    { href: '#funciones', texto: 'Funciones' },
    { href: '#como-funciona', texto: 'Cómo funciona' },
    { href: '#privacidad', texto: 'Privacidad' },
    { href: '#ayuda', texto: 'Ayuda' },
];

const FUNCIONES = [
    {
        icono: Route,
        titulo: 'Rutas más informadas',
        texto: 'Compara alternativas según iluminación, flujo peatonal y comercios abiertos reportados.',
    },
    {
        icono: Flame,
        titulo: 'Mapa de calor',
        texto: 'Zonas con más o menos reportes nocturnos, con leyenda, texto y patrones.',
    },
    {
        icono: FilePenLine,
        titulo: 'Reportes de la comunidad',
        texto: 'Informa incidentes con ubicación aproximada y la opción de ser anónima.',
    },
    {
        icono: Clock,
        titulo: 'Horarios con más flujo',
        texto: 'Franjas con más gente en la calle, siempre con su nivel de confianza.',
    },
    {
        icono: ShieldAlert,
        titulo: 'Botón SOS',
        texto: 'Acceso rápido a números de emergencia y a tus contactos de confianza.',
    },
    {
        icono: HeartHandshake,
        titulo: 'Acompañamiento en vivo',
        texto: 'Comparte tu trayecto solo con quien tú autorices y detenlo cuando quieras.',
    },
    {
        icono: BellRing,
        titulo: 'Alerta por inactividad',
        texto: 'Si te detienes y no respondes, avisamos a tus contactos tras darte tiempo de cancelar.',
    },
    {
        icono: BarChart3,
        titulo: 'Dashboard comunitario',
        texto: 'Estadísticas agregadas y anónimas por zona, con sus límites a la vista.',
    },
];

const PRINCIPIOS = [
    {
        titulo: 'Centrado en ti',
        texto: 'Diseñado a partir de las necesidades y preocupaciones de las mujeres que se desplazan de noche.',
    },
    {
        titulo: 'Transparente',
        texto: 'Cada dato muestra su fuente, periodo y nivel de confianza.',
    },
    {
        titulo: 'Sin promesas falsas',
        texto: 'Ninguna ruta ni zona se etiqueta como completamente segura.',
    },
];

const PASOS = [
    {
        titulo: 'Planea',
        texto: 'Consulta rutas, mapa y horarios antes de salir.',
    },
    {
        titulo: 'Acompáñate',
        texto: 'Activa el acompañamiento y la alerta por inactividad si lo necesitas.',
    },
    {
        titulo: 'Actúa',
        texto: 'Ten a un toque el SOS y los números de emergencia.',
    },
];

const PREGUNTAS = [
    {
        p: '¿Me garantiza que una ruta es segura?',
        r: 'No. La aplicación te da información para decidir mejor, pero no puede garantizar seguridad. Que una zona tenga pocos reportes no significa que sea segura.',
    },
    {
        p: '¿Quién ve mi ubicación?',
        r: 'Solo las personas de confianza que tú elijas, solo mientras lo autorices y con la posibilidad de detenerlo en cualquier momento.',
    },
    {
        p: '¿Mis reportes son públicos?',
        r: 'Se muestran únicamente como conteos agregados por zona. No publicamos datos personales y puedes reportar de forma anónima.',
    },
    {
        p: '¿Sustituye a la línea 123?',
        r: 'No. Si estás en peligro inmediato, llama al 123. La app es un apoyo y no reemplaza a las autoridades.',
    },
];

export default function Welcome() {
    const { auth, currentTeam } = usePage().props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';
    const [abierta, setAbierta] = useState<number | null>(0);

    return (
        <>
            <Head title="AcompañaHer: rutas más informadas para mujeres de noche" />
            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-purple focus:px-4 focus:py-2 focus:text-warm-white"
            >
                Saltar al contenido
            </a>

            <header className="sticky top-0 z-40 border-b border-white/10 bg-ink/95 text-warm-white backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3">
                    <a
                        href="#inicio"
                        className="flex shrink-0 items-center gap-2 font-serif text-lg font-semibold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-xl bg-purple">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        AcompañaHer
                    </a>
                    <nav
                        aria-label="Principal"
                        className="hidden items-center gap-5 text-sm whitespace-nowrap xl:flex"
                    >
                        {NAV.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                className="text-warm-white/85 hover:text-lila"
                            >
                                {item.texto}
                            </a>
                        ))}
                    </nav>
                    <div className="flex shrink-0 items-center gap-1 whitespace-nowrap sm:gap-2">
                        <Link
                            href={mapa()}
                            className="hidden rounded-full px-3 py-2 text-sm font-semibold hover:text-lila md:inline-block"
                        >
                            Mapa público
                        </Link>
                        <Link
                            href={rutas()}
                            className="hidden rounded-full px-3 py-2 text-sm font-semibold hover:text-lila md:inline-block"
                        >
                            Rutas peatonales
                        </Link>
                        <ThemeToggle className="text-warm-white" />
                        {auth.user ? (
                            <Link
                                href={dashboardUrl}
                                className="rounded-full bg-purple px-5 py-2 text-sm font-bold ring-1 ring-lila/50 hover:bg-[#7d33bd]"
                            >
                                Ir a mi espacio
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="hidden rounded-full px-4 py-2 text-sm font-semibold hover:text-lila sm:inline-block"
                                >
                                    Iniciar sesión
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full bg-purple px-5 py-2 text-sm font-bold ring-1 ring-lila/50 hover:bg-[#7d33bd]"
                                >
                                    Crear cuenta
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main id="contenido" className="bg-background text-foreground">
                <section
                    id="inicio"
                    className="bg-brand-gradient text-warm-white"
                >
                    <div className="mx-auto grid max-w-6xl items-center gap-10 px-5 py-16 md:py-24 lg:grid-cols-[1.2fr_1fr]">
                        <div>
                            <p className="text-xs font-bold tracking-[0.2em] text-gold uppercase">
                                Para mujeres en el espacio público nocturno
                            </p>
                            <h1 className="mt-4 text-4xl leading-[1.05] font-semibold text-balance md:text-6xl">
                                Un camino de regreso a casa, con{' '}
                                <em className="text-lila not-italic">
                                    información.
                                </em>
                            </h1>
                            <p className="mt-6 max-w-xl text-lg text-warm-white/85">
                                Rutas, reportes de la comunidad y herramientas
                                de acompañamiento para que decidas con más calma
                                cómo volver.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3">
                                <Link
                                    href={auth.user ? dashboardUrl : register()}
                                    className="rounded-full bg-purple px-7 py-3.5 text-sm font-bold shadow-lg ring-1 ring-lila/50 transition hover:bg-[#7d33bd]"
                                >
                                    {auth.user
                                        ? 'Ir a mi espacio →'
                                        : 'Crear mi cuenta →'}
                                </Link>
                                <a
                                    href="#como-funciona"
                                    className="rounded-full border border-warm-white/40 px-7 py-3.5 text-sm font-bold transition hover:bg-warm-white/10"
                                >
                                    Cómo funciona
                                </a>
                            </div>
                            <ul className="mt-8 flex flex-wrap gap-2 text-xs font-semibold">
                                {[
                                    'Privada',
                                    'Anónima si lo prefieres',
                                    'Comunitaria',
                                ].map((t) => (
                                    <li
                                        key={t}
                                        className="rounded-full border border-lila/50 px-3 py-1 text-lila"
                                    >
                                        {t}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <div
                            aria-hidden="true"
                            className="relative mx-auto aspect-square w-full max-w-sm"
                        >
                            <div className="absolute inset-0 rounded-[3rem] bg-plum shadow-2xl ring-1 ring-white/15" />
                            <svg
                                viewBox="0 0 300 300"
                                className="relative size-full"
                            >
                                <path
                                    d="M40 250 C 90 230, 110 160, 160 150 S 240 90, 260 50"
                                    fill="none"
                                    stroke="#e395f2"
                                    strokeWidth="6"
                                    strokeLinecap="round"
                                    strokeDasharray="1 14"
                                />
                                <circle
                                    cx="40"
                                    cy="250"
                                    r="12"
                                    fill="#fdfcfb"
                                />
                                <circle
                                    cx="260"
                                    cy="50"
                                    r="14"
                                    fill="#f6ad69"
                                />
                                <circle
                                    cx="160"
                                    cy="150"
                                    r="60"
                                    fill="#6d28a8"
                                    opacity="0.35"
                                />
                                <circle
                                    cx="230"
                                    cy="220"
                                    r="34"
                                    fill="#e395f2"
                                    opacity="0.2"
                                />
                            </svg>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-5 py-16">
                    <h2 className="max-w-2xl text-3xl font-semibold text-balance md:text-4xl">
                        Tu seguridad no se promete: se acompaña con información.
                    </h2>
                    <ul className="mt-8 grid gap-5 md:grid-cols-3">
                        {PRINCIPIOS.map((p) => (
                            <li
                                key={p.titulo}
                                className="rounded-3xl border bg-card p-6 shadow-sm"
                            >
                                <h3 className="text-xl font-semibold">
                                    {p.titulo}
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    {p.texto}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>

                <section
                    id="funciones"
                    className="bg-secondary/60 py-16 dark:bg-muted"
                >
                    <div className="mx-auto max-w-6xl px-5">
                        <p className="text-xs font-bold tracking-[0.18em] text-purple uppercase dark:text-lila">
                            Funciones
                        </p>
                        <h2 className="mt-2 text-3xl font-semibold md:text-4xl">
                            Ocho herramientas, un mismo propósito
                        </h2>
                        <ol className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {FUNCIONES.map((f, i) => (
                                <li
                                    key={f.titulo}
                                    className="rounded-3xl border bg-card p-6 shadow-sm"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-2xl bg-secondary text-purple dark:text-lila">
                                            <f.icono
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span
                                            className="font-serif text-2xl text-gold"
                                            aria-hidden="true"
                                        >
                                            {String(i + 1).padStart(2, '0')}
                                        </span>
                                    </div>
                                    <h3 className="mt-4 text-lg font-semibold">
                                        {f.titulo}
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {f.texto}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <section
                    id="como-funciona"
                    className="mx-auto max-w-6xl px-5 py-16"
                >
                    <p className="text-xs font-bold tracking-[0.18em] text-purple uppercase dark:text-lila">
                        Cómo funciona
                    </p>
                    <h2 className="mt-2 text-3xl font-semibold md:text-4xl">
                        Tres pasos para tus desplazamientos
                    </h2>
                    <ol className="mt-8 grid gap-5 md:grid-cols-3">
                        {PASOS.map((p, i) => (
                            <li
                                key={p.titulo}
                                className="rounded-3xl bg-ink p-6 text-warm-white dark:bg-plum dark:ring-1 dark:ring-white/15"
                            >
                                <span className="font-serif text-4xl text-gold">
                                    {i + 1}
                                </span>
                                <h3 className="mt-2 text-xl font-semibold">
                                    {p.titulo}
                                </h3>
                                <p className="mt-1 text-warm-white/80">
                                    {p.texto}
                                </p>
                            </li>
                        ))}
                    </ol>
                </section>

                <section
                    id="privacidad"
                    className="bg-brand-gradient text-warm-white"
                >
                    <div className="mx-auto grid max-w-6xl gap-8 px-5 py-16 md:grid-cols-[auto_1fr] md:items-center">
                        <span className="flex size-16 items-center justify-center rounded-3xl bg-warm-white/10">
                            <Lock
                                className="size-8 text-lila"
                                aria-hidden="true"
                            />
                        </span>
                        <div>
                            <h2 className="text-3xl font-semibold">
                                Tu ubicación es tuya
                            </h2>
                            <p className="mt-3 max-w-3xl text-warm-white/85">
                                Pedimos solo los datos necesarios, compartimos
                                tu ubicación únicamente con quien autorices, no
                                conservamos historiales más tiempo del necesario
                                y puedes retirar tus permisos cuando quieras.
                                Los reportes son anónimos y se muestran
                                agregados por zona.
                            </p>
                        </div>
                    </div>
                </section>

                <section id="ayuda" className="mx-auto max-w-3xl px-5 py-16">
                    <p className="text-xs font-bold tracking-[0.18em] text-purple uppercase dark:text-lila">
                        Preguntas frecuentes
                    </p>
                    <h2 className="mt-2 text-3xl font-semibold md:text-4xl">
                        Antes de empezar
                    </h2>
                    <ul className="mt-6 divide-y rounded-3xl border bg-card">
                        {PREGUNTAS.map((q, i) => (
                            <li key={q.p}>
                                <h3>
                                    <button
                                        type="button"
                                        aria-expanded={abierta === i}
                                        aria-controls={`resp-${i}`}
                                        onClick={() =>
                                            setAbierta(abierta === i ? null : i)
                                        }
                                        className="flex w-full items-center justify-between gap-4 px-6 py-4 text-left font-sans text-base font-semibold"
                                    >
                                        {q.p}
                                        <Plus
                                            className={`size-5 shrink-0 text-purple transition dark:text-lila ${abierta === i ? 'rotate-45' : ''}`}
                                            aria-hidden="true"
                                        />
                                    </button>
                                </h3>
                                <div
                                    id={`resp-${i}`}
                                    hidden={abierta !== i}
                                    className="px-6 pb-5 text-muted-foreground"
                                >
                                    {q.r}
                                </div>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-6 rounded-2xl border border-gold/60 bg-gold/15 p-4 text-sm">
                        <strong>Si hay peligro inmediato, llama al 123.</strong>{' '}
                        Esta aplicación orienta; no reemplaza a las autoridades
                        ni a los servicios de atención. Verifica los canales de
                        la Línea Púrpura en tu municipio.
                    </p>
                </section>
            </main>

            <footer className="bg-deep-plum text-warm-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm">
                    <p className="font-serif text-lg font-semibold">
                        AcompañaHer
                    </p>
                    <p className="text-warm-white/70">
                        Prototipo MVP · Los datos mostrados son de ejemplo.
                    </p>
                    <a
                        href="#inicio"
                        className="font-semibold text-lila hover:underline"
                    >
                        Volver arriba ↑
                    </a>
                </div>
            </footer>
        </>
    );
}
