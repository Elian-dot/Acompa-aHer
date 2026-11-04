import { Head, Link } from '@inertiajs/react';
import { BellRing, CheckCircle2, CircleAlert, Clock } from 'lucide-react';
import { useState } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import PageHeader from '@/components/ruta/page-header';
import PushToggle from '@/components/ruta/push-toggle';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useJourney } from '@/hooks/use-journey';
import { useNow } from '@/hooks/use-now';
import {
    deliveryLabel,
    storeInactivityMinutes,
    storedInactivityMinutes,
} from '@/lib/journey';
import { countdown, formatClock } from '@/lib/time';
import { acompanamiento } from '@/routes';
import { destroy, store } from '@/routes/api/v1/push_subscriptions';
import { usePage } from '@inertiajs/react';

type Props = {
    canStartJourney: boolean;
    vapidPublicKey: string | null;
    limits: {
        minInactivityMinutes: number;
        maxInactivityMinutes: number;
        defaultInactivityMinutes: number;
        maxPauseMinutes: number;
    };
};

export default function Inactividad({
    canStartJourney,
    vapidPublicKey,
    limits,
}: Props) {
    const { currentTeam } = usePage().props;
    const journeyState = useJourney({ pollMs: 8000 });
    const now = useNow();
    const [minutes, setMinutes] = useState(() =>
        storedInactivityMinutes(limits.defaultInactivityMinutes),
    );
    const journey = journeyState.journey;
    const alert = journey?.alert ?? null;
    const dueIn = journey
        ? new Date(journey.inactivity_due_at).getTime() - now
        : null;
    const escalationIn = alert?.escalation_due_at
        ? new Date(alert.escalation_due_at).getTime() - now
        : null;

    function changeMinutes(value: number) {
        setMinutes(value);
        storeInactivityMinutes(value);
    }

    return (
        <>
            <Head title="Alerta por inactividad" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Acompañarme"
                    title="Alerta por inactividad"
                    description="Si te detienes durante el tiempo que definas y no confirmas que estás bien, primero te avisamos a ti. Si sigues sin responder, avisamos a tus contactos de confianza."
                    ayuda={
                        <p>
                            Cuenta como actividad moverte más de 30 metros con
                            buena señal de GPS, o pulsar “Estoy bien”. Un GPS
                            impreciso no cuenta como movimiento ni como quietud.
                            Si vas a quedarte en un lugar, pausa la alerta desde
                            el acompañamiento.
                        </p>
                    }
                />

                <FormErrors message={journeyState.message} />

                <SectionCard
                    headingId="tiempo"
                    title="Tiempo de inactividad"
                    description="Se aplica al próximo acompañamiento que inicies. En uno ya iniciado no puede cambiarse."
                >
                    <div className="grid max-w-md gap-2">
                        <Label htmlFor="minutes">
                            Avisar si me detengo más de:{' '}
                            <strong>{minutes} min</strong>
                        </Label>
                        <input
                            id="minutes"
                            type="range"
                            min={limits.minInactivityMinutes}
                            max={limits.maxInactivityMinutes}
                            step={1}
                            value={minutes}
                            onChange={(event) =>
                                changeMinutes(Number(event.target.value))
                            }
                            className="w-full accent-purple"
                            aria-describedby="minutes-help"
                        />
                        <p
                            id="minutes-help"
                            className="text-sm text-muted-foreground"
                        >
                            Entre {limits.minInactivityMinutes} y{' '}
                            {limits.maxInactivityMinutes} minutos. Después del
                            aviso para ti, esperamos unos 2 minutos antes de
                            avisar a tus contactos. Esta preferencia se guarda
                            solo en este dispositivo.
                        </p>
                    </div>
                </SectionCard>

                <SectionCard
                    headingId="avisos"
                    title="Avisos en este dispositivo"
                    description="Para que la alerta te llegue aunque tengas la aplicación cerrada."
                >
                    <PushToggle
                        publicKey={vapidPublicKey}
                        subscribeUrl={store.url()}
                        unsubscribeUrl={destroy.url()}
                        description="Te mostraremos un aviso con vibración cuando lleves tiempo sin moverte, antes de avisar a nadie más."
                    />
                </SectionCard>

                <SectionCard headingId="estado" title="Estado actual">
                    {journey === undefined && (
                        <p role="status">Consultando tu acompañamiento…</p>
                    )}
                    {journey === null && (
                        <div className="space-y-3 text-sm">
                            <p>
                                No tienes un acompañamiento activo, así que no
                                hay ninguna alerta corriendo.
                            </p>
                            {canStartJourney && (
                                <Button asChild>
                                    <Link
                                        href={
                                            currentTeam
                                                ? acompanamiento(
                                                      currentTeam.slug,
                                                  )
                                                : '/'
                                        }
                                    >
                                        Iniciar un acompañamiento
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                    {journey && (
                        <div className="space-y-4" aria-live="polite">
                            {!alert && (
                                <div className="flex flex-wrap items-center gap-3 rounded-2xl border p-4">
                                    <Clock
                                        className="size-5 text-purple dark:text-lila"
                                        aria-hidden="true"
                                    />
                                    {journey.paused_until ? (
                                        <p>
                                            Alerta en pausa hasta las{' '}
                                            {formatClock(journey.paused_until)}.
                                        </p>
                                    ) : (
                                        <p>
                                            Si no te mueves ni confirmas, el
                                            aviso para ti saltará en{' '}
                                            <strong className="tabular-nums">
                                                {dueIn !== null && dueIn > 0
                                                    ? countdown(dueIn)
                                                    : 'unos segundos'}
                                            </strong>
                                            .
                                        </p>
                                    )}
                                </div>
                            )}

                            {alert?.status === 'preliminary' && (
                                <div
                                    role="alert"
                                    className="rounded-3xl border-2 border-gold bg-gold/15 p-5"
                                >
                                    <h3 className="inline-flex items-center gap-2 text-xl font-semibold">
                                        <BellRing
                                            className="size-5"
                                            aria-hidden="true"
                                        />{' '}
                                        ¿Estás bien?
                                    </h3>
                                    <p className="mt-2">
                                        Avisaremos a tus contactos en{' '}
                                        <strong className="tabular-nums">
                                            {escalationIn !== null
                                                ? countdown(escalationIn)
                                                : '—'}
                                        </strong>{' '}
                                        si no respondes.
                                    </p>
                                    <div className="mt-4 flex flex-wrap gap-3">
                                        <Button
                                            size="lg"
                                            onClick={() =>
                                                void journeyState.confirm()
                                            }
                                            disabled={journeyState.busy}
                                        >
                                            Estoy bien
                                        </Button>
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            onClick={() =>
                                                void journeyState.confirm(
                                                    limits.maxPauseMinutes,
                                                )
                                            }
                                            disabled={journeyState.busy}
                                        >
                                            Cancelar la alerta (
                                            {limits.maxPauseMinutes} min)
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {alert && alert.status !== 'preliminary' && (
                                <div
                                    role="alert"
                                    className="rounded-3xl border-2 border-danger p-5"
                                >
                                    <h3 className="inline-flex items-center gap-2 text-xl font-semibold">
                                        <CircleAlert
                                            className="size-5 text-danger"
                                            aria-hidden="true"
                                        />
                                        {alert.status === 'acknowledged'
                                            ? 'Un contacto vio tu alerta'
                                            : 'Avisamos a tus contactos'}
                                    </h3>
                                    <p className="mt-2 text-sm">
                                        Si ya estás bien, confírmalo para cerrar
                                        la alerta.
                                    </p>
                                    <Button
                                        className="mt-3"
                                        onClick={() =>
                                            void journeyState.confirm()
                                        }
                                        disabled={journeyState.busy}
                                    >
                                        <CheckCircle2 aria-hidden="true" />{' '}
                                        Estoy bien
                                    </Button>
                                </div>
                            )}

                            {alert && alert.deliveries.length > 0 && (
                                <div>
                                    <h3 className="mb-2 text-base font-semibold">
                                        Registro de entrega
                                    </h3>
                                    <ul className="space-y-2 text-sm">
                                        {alert.deliveries.map(
                                            (delivery, position) => (
                                                <li
                                                    key={position}
                                                    className="rounded-2xl border p-3"
                                                >
                                                    <div className="flex flex-wrap justify-between gap-2">
                                                        <span className="font-semibold">
                                                            {delivery.recipient}{' '}
                                                            ·{' '}
                                                            {delivery.stage ===
                                                            'preliminary'
                                                                ? 'aviso previo'
                                                                : 'alerta'}
                                                        </span>
                                                        <span
                                                            className={
                                                                delivery.status ===
                                                                'failed'
                                                                    ? 'font-semibold text-danger'
                                                                    : delivery.status ===
                                                                        'sent'
                                                                      ? 'font-semibold text-success'
                                                                      : ''
                                                            }
                                                        >
                                                            {delivery.status ===
                                                            'sent'
                                                                ? 'Entregado'
                                                                : delivery.status ===
                                                                    'failed'
                                                                  ? 'No entregado'
                                                                  : 'En curso'}
                                                        </span>
                                                    </div>
                                                    <p className="text-muted-foreground">
                                                        {deliveryLabel(
                                                            delivery,
                                                        )}
                                                    </p>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </SectionCard>

                <SectionCard headingId="limites" title="Qué tenemos en cuenta">
                    <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                        <li>
                            Una señal de GPS imprecisa (más de 100 m) no cuenta
                            como movimiento: no debería generar falsas alertas
                            por pequeños saltos.
                        </li>
                        <li>
                            Si pierdes la conexión, tu aplicación guarda la
                            última ubicación y la envía al reconectar. Una
                            alerta no se cancela sola por una desconexión.
                        </li>
                        <li>
                            El plazo lo controla el servidor, no el reloj de tu
                            teléfono.
                        </li>
                        <li>
                            Un acompañamiento sin terminar se cierra solo a las
                            12 horas.
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </>
    );
}
