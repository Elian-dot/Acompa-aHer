import { Head, useHttp } from '@inertiajs/react';
import { Eye, MapPinOff, ShieldAlert, TimerOff, WifiOff } from 'lucide-react';
import { useEffect, useEffectEvent, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import GeographicMap from '@/components/ruta/geographic-map';
import PushToggle from '@/components/ruta/push-toggle';
import ThemeToggle from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';
import InstallAppButton from '@/components/install-app-button';
import { useNow } from '@/hooks/use-now';
import { ago, formatClock } from '@/lib/time';
import { acknowledge, show } from '@/routes/api/v1/tracking';
import { destroy, store } from '@/routes/api/v1/tracking/push_subscription';
import { page as trackingPage } from '@/routes/tracking';
import type { TrackingState } from '@/types/journey';
import type { MapConfiguration } from '@/types/safety';

type Props = {
    token: string;
    vapidPublicKey: string | null;
    mapConfiguration: MapConfiguration;
};

const POLL_MS = 10_000;
const STALE_AFTER_MS = 2 * 60_000;

export default function Seguimiento({
    token,
    vapidPublicKey,
    mapConfiguration,
}: Props) {
    const http = useHttp<Record<string, never>, { data: TrackingState }>({});
    const action = useHttp<Record<string, never>, { data: TrackingState }>({});
    const [state, setState] = useState<TrackingState | null>(null);
    const [problem, setProblem] = useState('');
    const [invalid, setInvalid] = useState(false);
    const now = useNow();

    const load = useEffectEvent(async () => {
        try {
            const response = await http.get(show.url(token));
            setState(response.data);
            setProblem('');
        } catch (error) {
            const status = (error as { response?: { status?: number } })
                .response?.status;
            if (status === 404) setInvalid(true);
            else
                setProblem(
                    'No pudimos actualizar el seguimiento. Reintentando…',
                );
        }
    });
    const cancel = useEffectEvent(() => http.cancel());

    useEffect(() => {
        void load();
        const id = setInterval(() => void load(), POLL_MS);
        const onVisible = () =>
            document.visibilityState === 'visible' && void load();
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
            cancel();
        };
    }, [token]);

    const alertStatus =
        state?.state === 'active' ? state.alert?.status : undefined;
    useEffect(() => {
        if (
            alertStatus !== 'escalated' ||
            document.visibilityState === 'visible' ||
            Notification.permission !== 'granted'
        )
            return;
        void navigator.serviceWorker?.ready.then((registration) =>
            registration.showNotification('Alerta de AcompañaHer', {
                body: `${state?.owner_name ?? 'Tu contacto'} no ha respondido. Abre el seguimiento.`,
                tag: 'tracking-alert',
            }),
        );
    }, [alertStatus, state?.owner_name]);

    async function acknowledgeAlert() {
        try {
            const response = await action.post(acknowledge.url(token));
            setState(response.data);
        } catch {
            setProblem(
                'No pudimos registrar tu confirmación. Inténtalo de nuevo.',
            );
        }
    }

    const active = state?.state === 'active' ? state : null;
    const location = active?.location ?? null;
    const stale = location
        ? now - new Date(location.recorded_at).getTime() > STALE_AFTER_MS
        : false;
    const name = state?.owner_name ?? 'tu contacto';

    return (
        <>
            <Head title="Seguimiento de un trayecto" />
            <div className="min-h-svh bg-background text-foreground">
                <header className="border-b border-white/10 bg-ink text-warm-white">
                    <div className="mx-auto flex max-w-3xl items-center justify-between px-5 py-3">
                        <span className="flex items-center gap-2 font-serif text-lg font-semibold">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-purple">
                                <AppLogoIcon className="size-5 fill-current" />
                            </span>
                            AcompañaHer
                        </span>
                        <ThemeToggle className="text-warm-white" />
                    </div>
                </header>

                <main className="mx-auto flex max-w-3xl flex-col gap-5 p-5">
                    {invalid && (
                        <section
                            className="rounded-3xl border p-6"
                            role="alert"
                        >
                            <h1 className="text-2xl font-semibold">
                                Este enlace no es válido
                            </h1>
                            <p className="mt-2 text-muted-foreground">
                                Puede que esté mal copiado o que la persona que
                                te lo envió haya generado uno nuevo. Pídele que
                                te lo comparta otra vez.
                            </p>
                        </section>
                    )}

                    {!invalid && !state && (
                        <p role="status">Abriendo el seguimiento…</p>
                    )}

                    {state && state.state !== 'active' && (
                        <section
                            className="rounded-3xl border p-6"
                            role="status"
                        >
                            <TimerOff
                                className="mb-3 size-8 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h1 className="text-2xl font-semibold">
                                {state.state === 'ended' &&
                                    `El acompañamiento de ${name} terminó`}
                                {state.state === 'revoked' &&
                                    `${name} retiró tu acceso`}
                                {state.state === 'expired' &&
                                    'Este enlace venció'}
                            </h1>
                            <p className="mt-2 text-muted-foreground">
                                {state.state === 'ended'
                                    ? 'Ya no se comparte su ubicación. No necesitas hacer nada más.'
                                    : 'Ya no puedes ver su ubicación. Si necesitas acompañarla otra vez, pídele un enlace nuevo.'}
                            </p>
                        </section>
                    )}

                    {active && (
                        <>
                            <div>
                                <p className="text-xs font-bold tracking-[0.18em] text-purple uppercase dark:text-lila">
                                    Estás acompañando a
                                </p>
                                <h1 className="text-3xl font-semibold">
                                    {name}
                                </h1>
                                <p className="mt-1 inline-flex items-center gap-2 text-sm text-muted-foreground">
                                    <Eye
                                        className="size-4"
                                        aria-hidden="true"
                                    />{' '}
                                    Solo tú puedes ver este seguimiento, hasta
                                    las {formatClock(active.expires_at)}.
                                </p>
                            </div>

                            {active.alert &&
                                active.alert.status !== 'resolved' && (
                                    <div
                                        role="alert"
                                        className="rounded-3xl border-2 border-danger bg-danger/10 p-5"
                                    >
                                        <h2 className="inline-flex items-center gap-2 text-xl font-semibold">
                                            <ShieldAlert
                                                className="size-5 text-danger"
                                                aria-hidden="true"
                                            />{' '}
                                            {name} no ha respondido
                                        </h2>
                                        <p className="mt-2 text-sm">
                                            Se detuvo y no confirmó que está
                                            bien (
                                            {ago(
                                                active.alert.triggered_at,
                                                now,
                                            )}
                                            ). Intenta comunicarte con ella. Si
                                            crees que está en peligro, llama al
                                            123.
                                        </p>
                                        <div className="mt-4 flex flex-wrap gap-3">
                                            <Button
                                                asChild
                                                variant="destructive"
                                            >
                                                <a href="tel:123">
                                                    Llamar al 123
                                                </a>
                                            </Button>
                                            {active.alert.status ===
                                                'escalated' && (
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        void acknowledgeAlert()
                                                    }
                                                    disabled={action.processing}
                                                >
                                                    Ya vi la alerta
                                                </Button>
                                            )}
                                            {active.alert.status ===
                                                'acknowledged' && (
                                                <p className="self-center text-sm font-semibold">
                                                    Registramos que viste la
                                                    alerta.
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}

                            {active.paused_until && (
                                <p className="rounded-2xl border p-3 text-sm">
                                    {name} pausó la alerta hasta las{' '}
                                    {formatClock(active.paused_until)}: se
                                    detuvo a propósito.
                                </p>
                            )}

                            <section
                                aria-labelledby="mapa-seguimiento"
                                className="space-y-2"
                            >
                                <h2 id="mapa-seguimiento" className="sr-only">
                                    Mapa
                                </h2>
                                <GeographicMap
                                    configuration={mapConfiguration}
                                    points={[
                                        ...(location
                                            ? [
                                                  {
                                                      latitude:
                                                          location.latitude,
                                                      longitude:
                                                          location.longitude,
                                                      label: `Ubicación de ${name}`,
                                                  },
                                              ]
                                            : [
                                                  {
                                                      ...active.origin,
                                                      label: 'Origen',
                                                  },
                                              ]),
                                        {
                                            ...active.destination,
                                            label: 'Destino',
                                        },
                                    ]}
                                />
                                {location ? (
                                    <p
                                        className={
                                            stale
                                                ? 'flex items-center gap-2 text-sm font-semibold text-danger'
                                                : 'text-sm text-muted-foreground'
                                        }
                                        role="status"
                                    >
                                        {stale && (
                                            <WifiOff
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        Última ubicación{' '}
                                        {ago(location.recorded_at, now)}{' '}
                                        (precisión de unos{' '}
                                        {Math.round(location.accuracy_meters)}{' '}
                                        m).
                                        {stale &&
                                            ' Hace rato que no llega una ubicación nueva: puede haber perdido la señal o la conexión.'}
                                    </p>
                                ) : (
                                    <p
                                        className="flex items-center gap-2 text-sm text-muted-foreground"
                                        role="status"
                                    >
                                        <MapPinOff
                                            className="size-4"
                                            aria-hidden="true"
                                        />{' '}
                                        Aún no llega su primera ubicación.
                                        Mostramos el punto de partida.
                                    </p>
                                )}
                            </section>

                            <section
                                aria-labelledby="avisos-seguimiento"
                                className="space-y-3"
                            >
                                <h2
                                    id="avisos-seguimiento"
                                    className="text-xl font-semibold"
                                >
                                    Avisos en este dispositivo
                                </h2>
                                <PushToggle
                                    publicKey={vapidPublicKey}
                                    subscribeUrl={store.url(token)}
                                    unsubscribeUrl={destroy.url(token)}
                                    tracking={{ path: trackingPage.url(token) }}
                                    description={`Recibe una notificación si ${name} no responde, aunque cierres esta página. Puedes desactivarla cuando quieras.`}
                                />
                                <InstallAppButton />
                            </section>
                        </>
                    )}

                    {problem && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            {problem}
                        </p>
                    )}

                    <p className="text-xs text-muted-foreground">
                        AcompañaHer orienta, pero no reemplaza a las
                        autoridades. Si hay peligro inmediato, llama al 123. No
                        compartas este enlace: da acceso a la ubicación de{' '}
                        {name}.
                    </p>
                </main>
            </div>
        </>
    );
}
