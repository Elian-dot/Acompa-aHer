import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import type { FormEvent } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { requestErrorMessage } from '@/lib/safety';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/api/v1/emergency-contacts';
import type { Contact } from '@/types/safety';

type ContactForm = { name: string; phone: string; email: string };

export default function Contactos() {
    const list = useHttp<Record<string, never>, { data: Contact[] }>({});
    const form = useHttp<ContactForm, { data: Contact }>({
        name: '',
        phone: '',
        email: '',
    });
    const deletion = useHttp<Record<string, never>>({});
    const [editing, setEditing] = useState<number | null>(null);
    const [message, setMessage] = useState('');
    const [success, setSuccess] = useState('');
    function load() {
        return list
            .get(index.url())
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    const initialLoad = useEffectEvent(() => {
        void load();
    });
    const cancel = useEffectEvent(() => list.cancel());
    useEffect(() => {
        initialLoad();
        return () => cancel();
    }, []);
    function submit(event: FormEvent) {
        event.preventDefault();
        setMessage('');
        setSuccess('');
        const action =
            editing === null
                ? form.post(store.url())
                : form.patch(update.url(editing));
        void action
            .then(() => {
                form.reset();
                setEditing(null);
                setSuccess('Contacto guardado.');
                return load();
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    function remove(contact: Contact) {
        if (
            !window.confirm(
                `¿Eliminar a ${contact.name}? También se revocarán sus accesos y participaciones de acompañamiento.`,
            )
        )
            return;
        setMessage('');
        setSuccess('');
        void deletion
            .delete(destroy.url(contact.id))
            .then(() => {
                if (editing === contact.id) {
                    setEditing(null);
                    form.reset();
                }
                setSuccess(
                    'Contacto eliminado y accesos dependientes retirados.',
                );
                return load();
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    return (
        <>
            <Head title="Contactos de emergencia" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Tu red de confianza"
                    title="Contactos de emergencia"
                    description="Guarda hasta 20 contactos. Sus datos son privados y se almacenan cifrados. No necesitan tener una cuenta."
                />
                <FormErrors errors={form.errors} message={message} />
                {success && (
                    <p
                        role="status"
                        className="rounded-2xl border bg-secondary p-4"
                    >
                        {success}
                    </p>
                )}
                <SectionCard
                    headingId="editar-contacto"
                    title={
                        editing === null
                            ? 'Añadir un contacto'
                            : 'Editar contacto'
                    }
                >
                    <form onSubmit={submit} className="grid gap-4">
                        <label className="grid gap-2 text-sm font-medium">
                            Nombre
                            <Input
                                required
                                maxLength={100}
                                autoComplete="off"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-medium">
                            Teléfono internacional
                            <Input
                                required
                                type="tel"
                                autoComplete="off"
                                placeholder="+573001234567"
                                value={form.data.phone}
                                onChange={(event) =>
                                    form.setData('phone', event.target.value)
                                }
                            />
                            <span className="text-xs text-muted-foreground">
                                Incluye + y el código del país, sin espacios.
                            </span>
                        </label>
                        <label className="grid gap-2 text-sm font-medium">
                            Correo electrónico (opcional)
                            <Input
                                type="email"
                                maxLength={254}
                                autoComplete="off"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                            />
                        </label>
                        <div className="flex flex-wrap gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Guardando…'
                                    : 'Guardar contacto'}
                            </Button>
                            {editing !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setEditing(null);
                                        form.resetAndClearErrors();
                                    }}
                                >
                                    Cancelar edición
                                </Button>
                            )}
                        </div>
                    </form>
                </SectionCard>
                <SectionCard headingId="lista-contactos" title="Tus contactos">
                    {list.processing && (
                        <p role="status">Consultando contactos…</p>
                    )}
                    {list.response?.data.length === 0 && (
                        <p>No tienes contactos guardados.</p>
                    )}
                    <ul className="space-y-3">
                        {list.response?.data.map((contact) => (
                            <li
                                key={contact.id}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4"
                            >
                                <div>
                                    <p className="font-semibold">
                                        {contact.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {contact.phone}
                                    </p>
                                    {contact.email && (
                                        <p className="text-sm text-muted-foreground">
                                            {contact.email}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        disabled={
                                            form.processing ||
                                            deletion.processing
                                        }
                                        onClick={() => {
                                            setEditing(contact.id);
                                            form.clearErrors();
                                            form.setData({
                                                name: contact.name,
                                                phone: contact.phone,
                                                email: contact.email ?? '',
                                            });
                                        }}
                                    >
                                        Editar
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={deletion.processing}
                                        onClick={() => remove(contact)}
                                    >
                                        Eliminar
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </SectionCard>
            </div>
        </>
    );
}
