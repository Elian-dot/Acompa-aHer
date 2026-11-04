import { Head, Link, useHttp } from '@inertiajs/react';
import {
    BatteryLow,
    CheckCircle2,
    Copy,
    Eye,
    MapPinOff,
    Radio,
    RefreshCw,
    Send,
    ShieldOff,
    WifiOff,
} from 'lucide-react';
import { useEffect, useEffectEvent, useState } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import GeographicMap from '@/components/ruta/geographic-map';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useGeolocation } from '@/hooks/use-geolocation';
import { useJourney } from '@/hooks/use-journey';
import { useLocationSharing } from '@/hooks/use-location-sharing';
import { useNow } from '@/hooks/use-now';
import { apiErrorMessage } from '@/lib/api-errors';
import { participantStatusLabel, storedInactivityMinutes } from '@/lib/journey';
import { ago } from '@/lib/time';
import { contacts as contactsPage } from '@/routes';
import { index } from '@/routes/api/v1/emergency-contacts';
import type { Contact, Coordinate, MapConfiguration } from '@/types/safety';

type Limits = {
    minInactivityMinutes: number;
    maxInactivityMinutes: number;
    defaultInactivityMinutes: number;
    maxPauseMinutes: number;
    maxShareHours: number;
};

type Props = {
    mapConfiguration: MapConfiguration;
    canManageContacts: boolean;
    canStartJourney: boolean;
    limits: Limits;
};

const SHARE_HOURS = [1, 2, 4, 8, 12];
const PAUSES = [5, 10, 15, 30];

export default function Acompanamiento({
    mapConfiguration,
    canManageContacts,
    canStartJourney,
    limits,
}: Props) {
    const journey = useJourney();
    const contactsHttp = useHttp<Record<string, never>, { data: Contact[] }>(
        {},
    );
    const location = useGeolocation();
    const now = useNow();
    const [origin, setOrigin] = useState<Coordinate | null>(null);
    const [destination, setDestination] = useState<Coordinate | null>(null);
    const [selected, setSelected] = useState<number[]>([]);
    const [shareHours, setShareHours] = useState(2);
    const [inactivity, setInactivity] = useState(() =>
        storedInactivityMinutes(limits.defaultInactivityMinutes),
    );
    const [consent, setConsent] = useState(false);
    const [problem, setProblem] = useState('');
    const [pause, setPause] = useState(10);
    const [confirmEnd, setConfirmEnd] = useState(false);
    const [copied, setCopied] = useState<number | null>(null);
    const [battery, setBattery] = useState<{
        level: number;
        charging: boolean;
    } | null>(null);

    const active = journey.journey?.status === 'active';
    const sharing = useLocationSharing(active, journey.sendLocation);
    const loadContacts = useEffectEvent(() => {
        if (canManageContacts)
            void contactsHttp
                .get(index.url())
                .catch((error: unknown) => setProblem(apiErrorMessage(error)));
    });
    const cancelContacts = useEffectEvent(() => contactsHttp.cancel());

    useEffect(() => {
        loadContacts();
        return () => cancelContacts();
    }, [canManageContacts]);

    useEffect(() => {
        const nav = navigator as Navigator & {
            getBattery?: () => Promise<{
                level: number;
                charging: boolean;
                addEventListener: (type: string, listener: () => void) => void;
            }>;
        };
        void nav.getBattery?.().then((manager) => {
            const update = () =>
                setBattery({
                    level: manager.level,
                    charging: manager.charging,
                });
            update();
            manager.addEventListener('levelchange', update);
            manager.addEventListener('chargingchange', update);
        });
    }, []);

    const contacts = contactsHttp.response?.data ?? [];

    async function start() {
        setProblem('');
        if (!origin || !destination) {
            setProblem('Indica dónde estás y a dónde vas.');
            return;
        }
        if (selected.length === 0) {
            setProblem('Elige al menos una persona de confianza.');
            return;
        }
        await journey.start({
            origin,
            destination,
            contact_ids: selected,
            inactivity_minutes: inactivity,
            share_hours: shareHours,
            consent,
        });
    }

    async function copy(participantId: number, url: string) {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(participantId);
            setTimeout(() => setCopied(null), 3000);
        } catch {
            setProblem(
                'No pudimos copiar el enlace. Mantén pulsado el texto para copiarlo.',
            );
        }
    }

    if (!canStartJourney) {
        return (
            <>
                <Head title="Acompañamiento virtual" />
                <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                    <FormErrors message="Tu cuenta no tiene permiso para iniciar un acompañamiento." />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Acompañamiento virtual" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Acompañarme"
                    title="Acompañamiento en tiempo real"
                    description="Comparte tu ubicación en vivo solo con las personas que elijas y solo durante tu trayecto. Ellas la ven con un enlace privado que puedes retirar en cualquier momento."
                    ayuda={
                        <p>
                            Al iniciar, tu ubicación se envía cada pocos
                            segundos mientras esta pantalla esté abierta. Si te
                            detienes y no respondes, la alerta por inactividad
                            avisa a tus contactos. Al terminar, el trayecto y
                            sus ubicaciones se eliminan en 24 horas.
                        </p>
                    }
                />

                <FormErrors message={problem || journey.message} />

                {journey.journey === undefined && (
                    <p role="status">Consultando tu acompañamiento…</p>
                )}

                {journey.journey === null && (
                    <>
                        <SectionCard
                            headingId="contactos"
                            title="¿Con quién la compartes?"
                            description="Solo ellas verán tu ubicación, y solo con el enlace privado que les envíes."
                        >
                            {!canManageContacts && (
                                <FormErrors message="Tu cuenta no tiene permiso para gestionar contactos." />
                            )}
                            {contactsHttp.processing && (
                                <p role="status">Consultando tus contactos…</p>
                            )}
                            {canManageContacts &&
                                !contactsHttp.processing &&
                                contacts.length === 0 && (
                                    <p className="text-sm">
                                        Aún no tienes contactos.{' '}
                                        <Link
                                            href={contactsPage()}
                                            className="font-semibold underline"
                                        >
                                            Añade una persona de confianza
                                        </Link>{' '}
                                        para poder acompañarte.
                                    </p>
                                )}
                            <fieldset className="grid gap-3 sm:grid-cols-2">
                                <legend className="sr-only">
                                    Contactos autorizados
                                </legend>
                                {contacts.map((contact) => (
                                    <div
                                        key={contact.id}
                                        className="flex items-center gap-3 rounded-2xl border p-4"
                                    >
                                        <Checkbox
                                            id={`contact-${contact.id}`}
                                            checked={selected.includes(
                                                contact.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                setSelected((current) =>
                                                    checked === true
                                                        ? [
                                                              ...current,
                                                              contact.id,
                                                          ]
                                                        : current.filter(
                                                              (id) =>
                                                                  id !==
                                                                  contact.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`contact-${contact.id}`}
                                            className="flex flex-col font-normal"
                                        >
                                            <span className="font-semibold">
                                                {contact.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {contact.phone}
                                            </span>
                                        </Label>
                                    </div>
                                ))}
                            </fieldset>
                        </SectionCard>

                        <SectionCard
                            headingId="trayecto"
                            title="Tu trayecto"
                            description="Obtén tu ubicación actual y toca el mapa para elegir tu destino."
                        >
                            <div className="space-y-4">
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            location.locate(setOrigin)
                                        }
                                        disabled={location.locating}
                                    >
                                        {origin
                                            ? 'Actualizar mi ubicación'
                                            : 'Usar mi ubicación actual'}
                                    </Button>
                                    <p
                                        role="status"
                                        className="mt-2 text-sm text-muted-foreground"
                                    >
                                        {location.message ||
                                            (origin
                                                ? 'Origen listo.'
                                                : 'Aún no tenemos tu ubicación.')}
                                    </p>
                                </div>
                                <GeographicMap
                                    configuration={mapConfiguration}
                                    points={[
                                        ...(origin
                                            ? [{ ...origin, label: 'Origen' }]
                                            : []),
                                        ...(destination
                                            ? [
                                                  {
                                                      ...destination,
                                                      label: 'Destino',
                                                  },
                                              ]
                                            : []),
                                    ]}
                                    onSelect={setDestination}
                                />
                                <p className="text-sm text-muted-foreground">
                                    {destination
                                        ? 'Destino elegido. Toca otro punto para cambiarlo.'
                                        : 'Toca el mapa para elegir tu destino.'}
                                </p>
                            </div>
                        </SectionCard>

                        <SectionCard
                            headingId="ajustes"
                            title="Ajustes y consentimiento"
                        >
                            <div className="grid gap-5">
                                <div className="grid gap-2 sm:max-w-sm">
                                    <Label htmlFor="inactivity">
                                        Avisar si me detengo más de:{' '}
                                        <strong>{inactivity} min</strong>
                                    </Label>
                                    <input
                                        id="inactivity"
                                        type="range"
                                        min={limits.minInactivityMinutes}
                                        max={limits.maxInactivityMinutes}
                                        step={1}
                                        value={inactivity}
                                        onChange={(event) =>
                                            setInactivity(
                                                Number(event.target.value),
                                            )
                                        }
                                        className="w-full accent-purple"
                                    />
                                </div>
                                <div className="grid gap-2 sm:max-w-xs">
                                    <Label htmlFor="share-hours">
                                        Vigencia del enlace
                                    </Label>
                                    <select
                                        id="share-hours"
                                        value={shareHours}
                                        onChange={(event) =>
                                            setShareHours(
                                                Number(event.target.value),
                                            )
                                        }
                                        className="h-11 rounded-xl border border-input bg-background px-3 text-sm"
                                    >
                                        {SHARE_HOURS.filter(
                                            (hours) =>
                                                hours <= limits.maxShareHours,
                                        ).map((hours) => (
                                            <option key={hours} value={hours}>
                                                {hours}{' '}
                                                {hours === 1 ? 'hora' : 'horas'}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="consent"
                                        checked={consent}
                                        onCheckedChange={(checked) =>
                                            setConsent(checked === true)
                                        }
                                        className="mt-0.5"
                                    />
                                    <Label
                                        htmlFor="consent"
                                        className="font-normal"
                                    >
                                        Autorizo compartir mi ubicación en vivo
                                        con las personas elegidas durante el
                                        trayecto. Puedo retirar su acceso o
                                        terminar cuando quiera.
                                    </Label>
                                </div>
                                <div>
                                    <Button
                                        size="lg"
                                        onClick={() => void start()}
                                        disabled={journey.busy || !consent}
                                    >
                                        Iniciar acompañamiento
                                    </Button>
                                </div>
                            </div>
                        </SectionCard>
                    </>
                )}

                {journey.journey && (
                    <>
                        <div
                            role="status"
                            className="bg-brand-gradient flex flex-wrap items-center gap-x-5 gap-y-2 rounded-3xl p-5 text-warm-white"
                        >
                            <span className="inline-flex items-center gap-2 font-semibold">
                                <Radio
                                    className="size-5 text-lila"
                                    aria-hidden="true"
                                />{' '}
                                Acompañamiento activo
                            </span>
                            <span className="inline-flex items-center gap-1.5 text-sm">
                                {sharing.gps === 'ok' ? (
                                    <Radio
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <MapPinOff
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {sharing.gps === 'ok' &&
                                    `GPS: señal de ${Math.round(sharing.accuracy ?? 0)} m`}
                                {sharing.gps === 'waiting' &&
                                    'GPS: buscando señal…'}
                                {sharing.gps === 'denied' &&
                                    'GPS bloqueado: permite la ubicación en el navegador'}
                                {sharing.gps === 'unavailable' &&
                                    'GPS no disponible en este dispositivo'}
                                {sharing.gps === 'insecure' &&
                                    'La ubicación necesita una conexión segura (HTTPS)'}
                            </span>
                            <span className="inline-flex items-center gap-1.5 text-sm">
                                {!sharing.online && (
                                    <WifiOff
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {sharing.online
                                    ? sharing.lastSentAt
                                        ? `Última ubicación enviada ${ago(new Date(sharing.lastSentAt).toISOString(), now)}`
                                        : 'Conectada'
                                    : 'Sin conexión: enviaremos tu ubicación al reconectar'}
                            </span>
                            {battery &&
                                battery.level < 0.2 &&
                                !battery.charging && (
                                    <span className="inline-flex items-center gap-1.5 text-sm text-gold">
                                        <BatteryLow
                                            className="size-4"
                                            aria-hidden="true"
                                        />{' '}
                                        Batería baja (
                                        {Math.round(battery.level * 100)} %)
                                    </span>
                                )}
                        </div>

                        {journey.journey.alert?.status === 'preliminary' && (
                            <div
                                role="alert"
                                className="rounded-3xl border-2 border-gold bg-gold/15 p-5"
                            >
                                <h2 className="text-xl font-semibold">
                                    ¿Estás bien?
                                </h2>
                                <p className="mt-1 text-sm">
                                    Llevas tiempo sin moverte. Si no respondes,
                                    avisaremos a tus contactos.
                                </p>
                                <Button
                                    className="mt-3"
                                    onClick={() => void journey.confirm()}
                                    disabled={journey.busy}
                                >
                                    Estoy bien
                                </Button>
                            </div>
                        )}

                        <SectionCard headingId="mapa-vivo" title="Tu trayecto">
                            <GeographicMap
                                configuration={mapConfiguration}
                                points={[
                                    ...(journey.journey.location
                                        ? [
                                              {
                                                  latitude:
                                                      journey.journey.location
                                                          .latitude,
                                                  longitude:
                                                      journey.journey.location
                                                          .longitude,
                                                  label: 'Tu ubicación',
                                              },
                                          ]
                                        : [
                                              {
                                                  ...journey.journey.origin,
                                                  label: 'Origen',
                                              },
                                          ]),
                                    {
                                        ...journey.journey.destination,
                                        label: 'Destino',
                                    },
                                ]}
                            />
                            <p className="mt-2 text-sm text-muted-foreground">
                                {journey.journey.location
                                    ? `Última ubicación guardada ${ago(journey.journey.location.recorded_at, now)}.`
                                    : 'Esperando tu primera ubicación…'}
                            </p>
                        </SectionCard>

                        <SectionCard headingId="acciones" title="Controles">
                            <div className="flex flex-wrap items-end gap-3">
                                <Button
                                    onClick={() => void journey.confirm()}
                                    disabled={journey.busy}
                                >
                                    <CheckCircle2 aria-hidden="true" /> Estoy
                                    bien
                                </Button>
                                <div className="flex items-end gap-2">
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor="pause"
                                            className="text-xs"
                                        >
                                            Voy a detenerme
                                        </Label>
                                        <select
                                            id="pause"
                                            value={pause}
                                            onChange={(event) =>
                                                setPause(
                                                    Number(event.target.value),
                                                )
                                            }
                                            className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
                                        >
                                            {PAUSES.filter(
                                                (minutes) =>
                                                    minutes <=
                                                    limits.maxPauseMinutes,
                                            ).map((minutes) => (
                                                <option
                                                    key={minutes}
                                                    value={minutes}
                                                >
                                                    {minutes} min
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            void journey.confirm(pause)
                                        }
                                        disabled={journey.busy}
                                    >
                                        Pausar la alerta
                                    </Button>
                                </div>
                            </div>
                            {journey.journey.paused_until && (
                                <p className="mt-3 text-sm">
                                    Alerta en pausa hasta las{' '}
                                    {new Date(
                                        journey.journey.paused_until,
                                    ).toLocaleTimeString('es-CO', {
                                        hour: 'numeric',
                                        minute: '2-digit',
                                        timeZone: 'America/Bogota',
                                    })}
                                    .
                                </p>
                            )}
                            <div className="mt-5 border-t pt-4">
                                {!confirmEnd ? (
                                    <Button
                                        variant="outline"
                                        onClick={() => setConfirmEnd(true)}
                                    >
                                        Terminar el acompañamiento
                                    </Button>
                                ) : (
                                    <div
                                        className="flex flex-wrap items-center gap-3"
                                        role="group"
                                        aria-label="Confirmar cómo terminó el trayecto"
                                    >
                                        <span className="text-sm font-semibold">
                                            ¿Cómo termina?
                                        </span>
                                        <Button
                                            onClick={() =>
                                                void journey.close('completed')
                                            }
                                            disabled={journey.busy}
                                        >
                                            Llegué bien
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                void journey.close('cancelled')
                                            }
                                            disabled={journey.busy}
                                        >
                                            Cancelar el trayecto
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            onClick={() => setConfirmEnd(false)}
                                        >
                                            Seguir acompañada
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </SectionCard>

                        <SectionCard
                            headingId="quienes"
                            title="Quién puede ver tu ubicación"
                            description="Cada persona entra con su propio enlace privado."
                        >
                            <ul className="space-y-3">
                                {journey.journey.participants.map(
                                    (participant) => {
                                        const link = journey.share.find(
                                            (entry) =>
                                                entry.participant_id ===
                                                participant.id,
                                        );
                                        const usable =
                                            participant.status === 'pending' ||
                                            participant.status === 'opened';
                                        return (
                                            <li
                                                key={participant.id}
                                                className="rounded-2xl border p-4"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="inline-flex items-center gap-2 font-semibold">
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />{' '}
                                                        {participant.name}
                                                    </p>
                                                    <span className="text-sm text-muted-foreground">
                                                        {
                                                            participantStatusLabel[
                                                                participant
                                                                    .status
                                                            ]
                                                        }
                                                        {participant.push_enabled &&
                                                            ' · avisos activados'}
                                                    </span>
                                                </div>
                                                {usable && (
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        {link ? (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        void copy(
                                                                            participant.id,
                                                                            link.tracking_url,
                                                                        )
                                                                    }
                                                                >
                                                                    <Copy aria-hidden="true" />{' '}
                                                                    {copied ===
                                                                    participant.id
                                                                        ? 'Enlace copiado'
                                                                        : 'Copiar enlace'}
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <a
                                                                        href={
                                                                            link.sms_url
                                                                        }
                                                                    >
                                                                        <Send aria-hidden="true" />{' '}
                                                                        Enviar
                                                                        por SMS
                                                                    </a>
                                                                </Button>
                                                                {'share' in
                                                                    navigator && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            void navigator
                                                                                .share(
                                                                                    {
                                                                                        title: 'Acompáñame en AcompañaHer',
                                                                                        url: link.tracking_url,
                                                                                    },
                                                                                )
                                                                                .catch(
                                                                                    () =>
                                                                                        undefined,
                                                                                )
                                                                        }
                                                                    >
                                                                        Compartir…
                                                                    </Button>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    void journey.renewLink(
                                                                        participant.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    journey.busy
                                                                }
                                                            >
                                                                <RefreshCw aria-hidden="true" />{' '}
                                                                Generar enlace
                                                                nuevo
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                void journey.revoke(
                                                                    participant.id,
                                                                )
                                                            }
                                                            disabled={
                                                                journey.busy
                                                            }
                                                        >
                                                            <ShieldOff aria-hidden="true" />{' '}
                                                            Retirar acceso
                                                        </Button>
                                                    </div>
                                                )}
                                                {link && (
                                                    <p className="mt-2 text-xs break-all text-muted-foreground">
                                                        {link.tracking_url}
                                                    </p>
                                                )}
                                            </li>
                                        );
                                    },
                                )}
                            </ul>
                            <p className="mt-4 text-sm text-muted-foreground">
                                El enlace solo se muestra aquí. Envíalo desde tu
                                dispositivo: la aplicación no puede comprobar
                                que el mensaje llegó. Si pierdes un enlace,
                                genera uno nuevo y el anterior deja de
                                funcionar.
                            </p>
                        </SectionCard>
                    </>
                )}
            </div>
        </>
    );
}
