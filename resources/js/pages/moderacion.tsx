import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent, useState } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { reportStatusLabel, requestErrorMessage } from '@/lib/safety';
import { show as attachmentUrl } from '@/routes/api/v1/attachments';
import { index, update } from '@/routes/api/v1/moderation';
import type { ModerationReport } from '@/types/safety';

type ListResponse = {
    data: ModerationReport[];
    meta: { current_page: number; last_page: number };
};
type Decision = {
    status: 'verified' | 'discarded';
    privacy_reviewed: boolean;
    coverage_reviewed: boolean;
    moderation_reason: string;
};

export default function Moderacion({
    discardReasons,
}: {
    discardReasons: Record<string, string>;
}) {
    const list = useHttp<Record<string, never>, ListResponse>({});
    const form = useHttp<Decision, { data: ModerationReport }>({
        status: 'verified',
        privacy_reviewed: false,
        coverage_reviewed: false,
        moderation_reason: '',
    });
    const [status, setStatus] = useState<ModerationReport['status']>('pending');
    const [page, setPage] = useState(1);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const [success, setSuccess] = useState('');
    function load() {
        setMessage('');
        return list
            .get(index.url({ query: { status, page } }))
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    const refresh = useEffectEvent(() => {
        void load();
    });
    const cancel = useEffectEvent(() => list.cancel());
    useEffect(() => {
        refresh();
        return () => cancel();
    }, [status, page]);
    const selected =
        list.response?.data.find((report) => report.id === selectedId) ??
        list.response?.data[0];
    function decide(decision: Decision['status']) {
        if (!selected) return;
        setMessage('');
        setSuccess('');
        form.transform((data) => ({ ...data, status: decision }));
        void form
            .patch(update.url(selected.id))
            .then(() => {
                setSuccess(
                    decision === 'verified'
                        ? 'Reporte verificado. Ya puede alimentar las consultas públicas agregadas.'
                        : 'Reporte descartado. No aparecerá en las consultas públicas.',
                );
                setSelectedId(null);
                form.reset();
                return load();
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }
    return (
        <>
            <Head title="Moderación de reportes" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Moderación comunitaria"
                    title="Reportes por revisar"
                    description="Verifica o descarta cada reporte. Solo los verificados alimentan el mapa. La identidad de la autora no se muestra en esta bandeja."
                />
                <div className="flex flex-wrap gap-3">
                    {(['pending', 'verified', 'discarded'] as const).map(
                        (value) => (
                            <Button
                                key={value}
                                aria-pressed={status === value}
                                variant={
                                    status === value ? 'default' : 'outline'
                                }
                                disabled={form.processing}
                                onClick={() => {
                                    setStatus(value);
                                    setPage(1);
                                    setSelectedId(null);
                                    form.resetAndClearErrors();
                                }}
                            >
                                {reportStatusLabel[value]}
                            </Button>
                        ),
                    )}
                    <Button
                        variant="outline"
                        disabled={list.processing}
                        onClick={() => {
                            void load();
                        }}
                    >
                        Actualizar bandeja
                    </Button>
                </div>
                <FormErrors errors={form.errors} message={message} />
                {success && (
                    <p
                        role="status"
                        className="rounded-2xl border bg-secondary p-4"
                    >
                        {success}
                    </p>
                )}
                {list.processing ? (
                    <p role="status" className="animate-pulse">
                        Consultando reportes…
                    </p>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <SectionCard
                            headingId="bandeja"
                            title="Bandeja de reportes"
                        >
                            {list.response?.data.length === 0 && (
                                <p>No hay reportes en este estado.</p>
                            )}
                            <ul className="space-y-3">
                                {list.response?.data.map((report) => (
                                    <li key={report.id}>
                                        <button
                                            type="button"
                                            disabled={form.processing}
                                            aria-pressed={
                                                selected?.id === report.id
                                            }
                                            onClick={() => {
                                                setSelectedId(report.id);
                                                form.resetAndClearErrors();
                                            }}
                                            className={`w-full rounded-2xl border-2 p-4 text-left ${selected?.id === report.id ? 'border-primary bg-secondary' : 'border-border'}`}
                                        >
                                            <strong>{report.category}</strong>
                                            <span className="mt-2 block text-sm">
                                                {new Date(
                                                    report.occurred_at,
                                                ).toLocaleString('es-CO', {
                                                    timeZone: 'America/Bogota',
                                                })}
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                #{report.id.slice(0, 8)} ·{' '}
                                                {report.is_anonymous
                                                    ? 'Anónimo'
                                                    : 'Asociado a una cuenta'}{' '}
                                                ·{' '}
                                                {
                                                    reportStatusLabel[
                                                        report.status
                                                    ]
                                                }
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            {list.response && (
                                <div className="mt-4 flex items-center gap-3">
                                    <Button
                                        variant="outline"
                                        disabled={page <= 1}
                                        onClick={() => setPage(page - 1)}
                                    >
                                        Anterior
                                    </Button>
                                    <span className="text-sm">
                                        Página {list.response.meta.current_page}{' '}
                                        de {list.response.meta.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        disabled={
                                            page >= list.response.meta.last_page
                                        }
                                        onClick={() => setPage(page + 1)}
                                    >
                                        Siguiente
                                    </Button>
                                </div>
                            )}
                        </SectionCard>
                        {selected && (
                            <SectionCard
                                headingId="detalle"
                                title={`Reporte #${selected.id.slice(0, 8)}`}
                            >
                                <dl className="grid gap-4">
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            Categoría
                                        </dt>
                                        <dd className="font-semibold">
                                            {selected.category}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm text-muted-foreground">
                                            Descripción
                                        </dt>
                                        <dd className="mt-1 rounded-2xl bg-secondary p-4">
                                            {selected.description ||
                                                'Sin descripción.'}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="mt-4 rounded-2xl border p-4 text-sm">
                                    {selected.is_anonymous
                                        ? 'Reporte anónimo: no tiene una cuenta asociada. '
                                        : 'La identidad asociada se mantiene privada. '}
                                    No se muestran coordenadas exactas en esta
                                    bandeja.
                                </p>
                                {selected.attachments.length > 0 && (
                                    <div className="mt-4">
                                        <h3 className="font-semibold">
                                            Adjuntos privados
                                        </h3>
                                        <ul className="mt-2 space-y-2">
                                            {selected.attachments.map(
                                                (attachment, index) => (
                                                    <li key={attachment.id}>
                                                        <a
                                                            href={attachmentUrl.url(
                                                                attachment.id,
                                                            )}
                                                            className="text-sm underline"
                                                        >
                                                            Descargar imagen{' '}
                                                            {index + 1} ·{' '}
                                                            {Math.ceil(
                                                                attachment.size_bytes /
                                                                    1024,
                                                            )}{' '}
                                                            KB
                                                        </a>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                                {selected.status === 'pending' ? (
                                    <div className="mt-5 space-y-4">
                                        <h3 className="font-semibold">
                                            Antes de verificar
                                        </h3>
                                        <label className="flex items-start gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={
                                                    form.data.privacy_reviewed
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'privacy_reviewed',
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            <span>
                                                Revisé el texto y los adjuntos;
                                                no contienen datos que
                                                identifiquen a una persona.
                                            </span>
                                        </label>
                                        <label className="flex items-start gap-3 text-sm">
                                            <input
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={
                                                    form.data.coverage_reviewed
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'coverage_reviewed',
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            <span>
                                                Revisé la información y entiendo
                                                que el sistema comprueba la
                                                ubicación dentro de Girardot al
                                                verificar.
                                            </span>
                                        </label>
                                        <label className="grid gap-2 text-sm font-medium">
                                            Motivo si lo descartas
                                            <select
                                                className="h-11 rounded-xl border bg-background px-3"
                                                value={
                                                    form.data.moderation_reason
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'moderation_reason',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Selecciona un motivo
                                                </option>
                                                {Object.entries(
                                                    discardReasons,
                                                ).map(([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <div className="flex flex-wrap gap-3">
                                            <Button
                                                disabled={
                                                    form.processing ||
                                                    !form.data
                                                        .privacy_reviewed ||
                                                    !form.data.coverage_reviewed
                                                }
                                                onClick={() =>
                                                    decide('verified')
                                                }
                                            >
                                                {form.processing
                                                    ? 'Guardando decisión…'
                                                    : 'Verificar reporte'}
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                disabled={
                                                    form.processing ||
                                                    !form.data.moderation_reason
                                                }
                                                onClick={() =>
                                                    decide('discarded')
                                                }
                                            >
                                                Descartar
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-5 text-sm">
                                        Decisión registrada:{' '}
                                        {reportStatusLabel[selected.status]}.
                                        {selected.moderation_reason
                                            ? ` Motivo: ${discardReasons[selected.moderation_reason] ?? 'Revisión de moderación'}.`
                                            : ''}
                                    </p>
                                )}
                            </SectionCard>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
