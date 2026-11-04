import { Head, Link, useHttp, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    CheckCircle2,
    Circle,
    Clock,
    FilePenLine,
    Flame,
    HeartHandshake,
    MapPin,
    Route,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useEffectEvent, useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import SectionCard from '@/components/ruta/section-card';
import { useAppUrls } from '@/hooks/use-app-urls';
import { dashboard } from '@/routes';
import { community_statistics } from '@/routes/api/v1';
import type { CommunityStatistics } from '@/types/journey';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
};

const PASOS_INICIO = [
    {
        id: 'contactos',
        titulo: 'Elige tus contactos de confianza',
        detalle:
            'Solo ellas verán tu ubicación, y solo cuando tú lo autorices.',
    },
    {
        id: 'permisos',
        titulo: 'Revisa los permisos de ubicación',
        detalle: 'Puedes activarlos o retirarlos cuando quieras.',
    },
    {
        id: 'sos',
        titulo: 'Revisa cómo pedir ayuda',
        detalle:
            'Las llamadas y los SMS se abren en tu dispositivo y requieren confirmación.',
    },
];

export default function Dashboard({ pendingInvitations = [] }: Props) {
    const { auth } = usePage().props;
    const urls = useAppUrls();
    const statistics = useHttp<Record<string, never>, CommunityStatistics>({});
    const [summaryError, setSummaryError] = useState(false);
    const summary = statistics.response;
    const loadSummary = useEffectEvent(() => {
        void statistics
            .get(community_statistics.url({ query: { period: 30 } }))
            .catch(() => setSummaryError(true));
    });
    const cancelSummary = useEffectEvent(() => statistics.cancel());
    useEffect(() => {
        loadSummary();
        return () => cancelSummary();
    }, []);
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const [hechos, setHechos] = useState<string[]>([]);

    const alternar = (id: string) =>
        setHechos((actual) =>
            actual.includes(id)
                ? actual.filter((item) => item !== id)
                : [...actual, id],
        );

    const accesos = [
        {
            titulo: 'Buscar una ruta',
            detalle: 'Compara alternativas para llegar a casa.',
            href: urls.rutas,
            icono: Route,
        },
        {
            titulo: 'Mapa de calor',
            detalle: 'Zonas con más o menos reportes nocturnos.',
            href: urls.mapa,
            icono: Flame,
        },
        {
            titulo: 'Horarios',
            detalle: 'A qué horas se reportan más incidentes.',
            href: urls.horarios,
            icono: Clock,
        },
        {
            titulo: 'Acompañamiento',
            detalle: 'Comparte tu trayecto con quien tú elijas.',
            href: urls.acompanamiento,
            icono: HeartHandshake,
        },
        {
            titulo: 'Alerta por inactividad',
            detalle: 'Avisa si te detienes y no respondes.',
            href: urls.inactividad,
            icono: BellRing,
        },
        {
            titulo: 'Reportar un incidente',
            detalle: 'Anónimo y sin datos innecesarios.',
            href: urls.reportes,
            icono: FilePenLine,
        },
        {
            titulo: 'Dashboard comunitario',
            detalle: 'Estadísticas agregadas por zona.',
            href: urls.comunidad,
            icono: BarChart3,
        },
    ];

    return (
        <>
            <Head title="Inicio" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="bg-brand-gradient relative overflow-hidden rounded-[2rem] p-6 text-warm-white shadow-lg md:p-10">
                    <p className="text-xs font-bold tracking-[0.18em] text-lila uppercase">
                        Tu espacio
                    </p>
                    <h1 className="mt-3 max-w-xl text-3xl leading-tight font-semibold text-balance md:text-5xl">
                        Hola, {auth.user.name.split(' ')[0]}. Que tu regreso a
                        casa sea{' '}
                        <em className="font-semibold text-lila not-italic">
                            más informado.
                        </em>
                    </h1>
                    <p className="mt-4 max-w-xl text-base text-warm-white/85">
                        Esta aplicación te da información para decidir mejor. No
                        promete seguridad absoluta: es una herramienta de apoyo,
                        no un reemplazo de las autoridades.
                    </p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <Link
                            href={urls.rutas}
                            className="inline-flex items-center gap-2 rounded-full bg-purple px-6 py-3 text-sm font-bold text-warm-white shadow-md ring-1 ring-lila/50 transition hover:bg-[#7d33bd]"
                        >
                            <MapPin className="size-4" aria-hidden="true" />
                            Planear mi ruta
                        </Link>
                        <Link
                            href={urls.sos}
                            className="inline-flex items-center gap-2 rounded-full border border-warm-white/40 px-6 py-3 text-sm font-bold text-warm-white transition hover:bg-warm-white/10"
                        >
                            <ShieldAlert
                                className="size-4"
                                aria-hidden="true"
                            />
                            Abrir el botón SOS
                        </Link>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <SectionCard
                        headingId="accesos"
                        title="¿Qué necesitas hoy?"
                        description="Cada módulo explica qué hace antes de que lo uses."
                    >
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {accesos.map((acceso) => (
                                <li key={acceso.titulo}>
                                    <Link
                                        href={acceso.href}
                                        className="group flex h-full items-start gap-3 rounded-2xl border bg-background p-4 transition hover:border-purple hover:shadow-md"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-purple dark:text-lila">
                                            <acceso.icono
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span>
                                            <span className="block font-semibold">
                                                {acceso.titulo}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {acceso.detalle}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>

                    <SectionCard
                        headingId="primeros-pasos"
                        title="Primeros pasos"
                        description="Tres acciones para sentirte preparada."
                    >
                        <ol className="space-y-3">
                            {PASOS_INICIO.map((paso, indice) => {
                                const hecho = hechos.includes(paso.id);

                                return (
                                    <li key={paso.id}>
                                        <button
                                            type="button"
                                            onClick={() => alternar(paso.id)}
                                            aria-pressed={hecho}
                                            className="flex w-full items-start gap-3 rounded-2xl border bg-background p-3 text-left transition hover:border-purple"
                                        >
                                            {hecho ? (
                                                <CheckCircle2
                                                    className="mt-0.5 size-5 shrink-0 text-success"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <Circle
                                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            <span>
                                                <span className="block text-sm font-semibold">
                                                    {indice + 1}. {paso.titulo}
                                                    <span className="sr-only">
                                                        {hecho
                                                            ? ' (hecho)'
                                                            : ' (pendiente)'}
                                                    </span>
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {paso.detalle}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                        <p className="mt-4 text-sm text-muted-foreground">
                            {hechos.length} de {PASOS_INICIO.length} pasos
                            completados.
                        </p>
                    </SectionCard>
                </div>

                <SectionCard
                    headingId="resumen-comunidad"
                    title="La comunidad en los últimos 30 días"
                    description="Reportes verificados en Girardot, sin datos personales."
                    action={
                        <Link
                            href={urls.comunidad}
                            className="text-sm font-semibold underline"
                        >
                            Ver estadísticas
                        </Link>
                    }
                >
                    {!summary && !summaryError && (
                        <p role="status">Consultando el resumen…</p>
                    )}
                    {summaryError && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            No pudimos cargar el resumen en este momento.
                        </p>
                    )}
                    {summary && (
                        <p className="text-sm">
                            <strong className="font-serif text-2xl">
                                {summary.totals.verified}
                            </strong>{' '}
                            reportes verificados.{' '}
                            {summary.meta.status === 'insufficient_data'
                                ? 'Aún hay pocos datos para mostrar tendencias.'
                                : summary.totals.change_percent !== null
                                  ? `${summary.totals.change_percent > 0 ? '+' : ''}${summary.totals.change_percent} % frente a los 30 días anteriores.`
                                  : ''}
                        </p>
                    )}
                    <p className="mt-3 text-sm text-muted-foreground">
                        Los reportes son aportes de la comunidad, no hechos
                        verificados por una autoridad. Pocos reportes en una
                        zona no significan que sea segura. AcompañaHer orienta y
                        no reemplaza a las autoridades: si hay peligro
                        inmediato, llama al 123.
                    </p>
                </SectionCard>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Inicio',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
