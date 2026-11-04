import { Head, Link, useHttp } from '@inertiajs/react';
import { Phone, ShieldAlert } from 'lucide-react';
import { useEffect, useEffectEvent, useState } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { useGeolocation } from '@/hooks/use-geolocation';
import { requestErrorMessage } from '@/lib/safety';
import { contacts } from '@/routes';
import { index } from '@/routes/api/v1/emergency-contacts';
import { store } from '@/routes/api/v1/sos';
import type { Contact, Coordinate } from '@/types/safety';

type SosForm = {
    confirmed: boolean;
    contact_ids: number[];
    latitude: number | null;
    longitude: number | null;
};

type PreparedSos = {
    data: {
        id: number;
        name: string;
        phone: string;
        call_url: string;
        sms_url: string;
        status: 'prepared';
    }[];
    message: string;
    notice: string;
};

export default function Sos({
    canManageContacts,
}: {
    canManageContacts: boolean;
}) {
    const list = useHttp<Record<string, never>, { data: Contact[] }>({});
    const form = useHttp<SosForm, PreparedSos>({
        confirmed: false,
        contact_ids: [],
        latitude: null,
        longitude: null,
    });
    const [message, setMessage] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [prepared, setPrepared] = useState<PreparedSos | null>(null);
    const location = useGeolocation();
    const load = useEffectEvent(() => {
        if (canManageContacts)
            void list
                .get(index.url())
                .catch((error: unknown) =>
                    setMessage(requestErrorMessage(error)),
                );
    });
    const cancel = useEffectEvent(() => list.cancel());
    useEffect(() => {
        load();
        return () => cancel();
    }, [canManageContacts]);
    function setLocation(point: Coordinate) {
        form.setData((current) => ({ ...current, ...point }));
    }
    function prepare() {
        setMessage('');
        form.transform((data) => ({ ...data, confirmed: true }));
        void form
            .post(store.url())
            .then((result) => {
                setPrepared(result);
                setConfirming(false);
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    return (
        <>
            <Head title="Pedir ayuda" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Emergencia"
                    title="Pedir ayuda ahora"
                    description="Llama o prepara un SMS para tus contactos. Cada envío se confirma en tu dispositivo; esta aplicación no puede comprobar su entrega."
                />
                <SectionCard headingId="llamar" title="Llamar">
                    <Button asChild variant="destructive" size="lg">
                        <a href="tel:123">
                            <Phone aria-hidden="true" /> Abrir llamada al 123
                        </a>
                    </Button>
                    <p className="mt-3 text-sm text-muted-foreground">
                        Se abrirá la aplicación de llamadas del dispositivo. Si
                        no admite llamadas, usa otro teléfono.
                    </p>
                </SectionCard>
                <FormErrors errors={form.errors} message={message} />
                {!canManageContacts ? (
                    <FormErrors message="Tu cuenta no tiene permiso para gestionar contactos de emergencia." />
                ) : (
                    <>
                        <SectionCard
                            headingId="contactos-sos"
                            title="Avisar a mis contactos"
                            action={
                                <Link
                                    href={contacts()}
                                    className="text-sm font-semibold underline"
                                >
                                    Gestionar contactos
                                </Link>
                            }
                        >
                            {list.processing && (
                                <p role="status">Consultando tus contactos…</p>
                            )}
                            {list.response?.data.length === 0 && (
                                <p>Añade un contacto para preparar un aviso.</p>
                            )}
                            <ul className="space-y-3">
                                {list.response?.data.map((contact) => (
                                    <li
                                        key={contact.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4"
                                    >
                                        <label className="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={form.data.contact_ids.includes(
                                                    contact.id,
                                                )}
                                                disabled={
                                                    form.processing ||
                                                    prepared !== null
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'contact_ids',
                                                        event.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .contact_ids,
                                                                  contact.id,
                                                              ]
                                                            : form.data.contact_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      contact.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span>
                                                <strong>{contact.name}</strong>
                                                <span className="block text-sm text-muted-foreground">
                                                    {contact.phone}
                                                </span>
                                            </span>
                                        </label>
                                        <Button asChild variant="outline">
                                            <a href={`tel:${contact.phone}`}>
                                                <Phone aria-hidden="true" />{' '}
                                                Llamar
                                            </a>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            {!prepared && (
                                <div className="mt-5 space-y-4">
                                    <Button
                                        variant="outline"
                                        disabled={
                                            location.locating || form.processing
                                        }
                                        onClick={() =>
                                            location.locate(setLocation)
                                        }
                                    >
                                        {location.locating
                                            ? 'Buscando ubicación…'
                                            : 'Incluir mi ubicación en el mensaje'}
                                    </Button>
                                    <p
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {location.message}
                                    </p>
                                    {form.data.latitude !== null && (
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                form.setData((data) => ({
                                                    ...data,
                                                    latitude: null,
                                                    longitude: null,
                                                }))
                                            }
                                        >
                                            Quitar ubicación
                                        </Button>
                                    )}
                                    <p className="rounded-2xl bg-secondary p-4 text-sm">
                                        Necesito ayuda.{' '}
                                        {form.data.latitude !== null
                                            ? 'Esta es mi ubicación aproximada: [enlace al mapa].'
                                            : 'No pude obtener mi ubicación.'}
                                    </p>
                                    {!confirming ? (
                                        <Button
                                            variant="destructive"
                                            size="lg"
                                            disabled={
                                                form.data.contact_ids.length ===
                                                0
                                            }
                                            onClick={() => setConfirming(true)}
                                        >
                                            <ShieldAlert aria-hidden="true" />{' '}
                                            Preparar aviso
                                        </Button>
                                    ) : (
                                        <div
                                            role="region"
                                            aria-label="Confirmar preparación del aviso"
                                            className="space-y-4 rounded-2xl border-2 border-destructive p-5"
                                        >
                                            <h3 className="text-lg font-semibold">
                                                ¿Preparar el mensaje para los
                                                contactos seleccionados?
                                            </h3>
                                            <p className="text-sm">
                                                Incluiremos{' '}
                                                {form.data.latitude !== null
                                                    ? 'tu ubicación y el mensaje'
                                                    : 'el mensaje sin ubicación'}
                                                . Todavía no se ha enviado nada.
                                                Después tendrás que abrir y
                                                enviar cada SMS.
                                            </p>
                                            <div className="flex flex-wrap gap-3">
                                                <Button
                                                    variant="destructive"
                                                    disabled={form.processing}
                                                    onClick={prepare}
                                                >
                                                    {form.processing
                                                        ? 'Preparando…'
                                                        : 'Sí, preparar mensajes'}
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    disabled={form.processing}
                                                    onClick={() =>
                                                        setConfirming(false)
                                                    }
                                                >
                                                    Cancelar
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </SectionCard>
                        {prepared && (
                            <SectionCard
                                headingId="mensajes"
                                title="Mensajes preparados, pendientes de envío"
                            >
                                <p role="status" className="text-sm">
                                    {prepared.notice}
                                </p>
                                <blockquote className="mt-4 rounded-2xl bg-secondary p-4 text-sm break-words">
                                    {prepared.message}
                                </blockquote>
                                <ul className="mt-4 space-y-3">
                                    {prepared.data.map((contact) => (
                                        <li
                                            key={contact.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4"
                                        >
                                            <span>
                                                {contact.name}
                                                <span className="block text-sm text-muted-foreground">
                                                    Entrega sin confirmar
                                                </span>
                                            </span>
                                            <Button asChild>
                                                <a href={contact.sms_url}>
                                                    Abrir SMS
                                                </a>
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Si el dispositivo no permite abrir SMS,
                                    llama directamente o copia el mensaje en tu
                                    aplicación de mensajería.
                                </p>
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    onClick={() => {
                                        setPrepared(null);
                                        form.reset();
                                    }}
                                >
                                    Preparar otro aviso
                                </Button>
                            </SectionCard>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
