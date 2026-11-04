import { Head, useHttp } from '@inertiajs/react';
import { CheckCircle2, LocateFixed } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FormErrors from '@/components/ruta/form-errors';
import GeographicMap from '@/components/ruta/geographic-map';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGeolocation } from '@/hooks/use-geolocation';
import { localIncidentTimestamp, requestErrorMessage } from '@/lib/safety';
import { store } from '@/routes/api/v1/reports';
import type { Category, MapConfiguration } from '@/types/safety';

type ReportForm = {
    report_category_id: string;
    latitude: string;
    longitude: string;
    occurred_at: string;
    description: string;
    is_anonymous: boolean;
    consent: boolean;
    attachments: File[];
};

export default function Reportes({
    categories,
    mapConfiguration,
    canReport,
}: {
    categories: Category[];
    mapConfiguration: MapConfiguration;
    canReport: boolean;
}) {
    const form = useHttp<
        ReportForm,
        { data: { id: string; status: string }; message: string }
    >({
        report_category_id: '',
        latitude: '',
        longitude: '',
        occurred_at: '',
        description: '',
        is_anonymous: true,
        consent: false,
        attachments: [],
    });
    const [message, setMessage] = useState('');
    const [receipt, setReceipt] = useState('');
    const location = useGeolocation();
    const selected =
        form.data.latitude !== '' &&
        form.data.longitude !== '' &&
        Number.isFinite(Number(form.data.latitude)) &&
        Number.isFinite(Number(form.data.longitude));
    const point = {
        latitude: Number(form.data.latitude),
        longitude: Number(form.data.longitude),
    };

    function submit(event: FormEvent) {
        event.preventDefault();
        setMessage('');
        form.transform((data) => ({
            ...data,
            occurred_at: localIncidentTimestamp(data.occurred_at),
        }));
        void form
            .post(store.url())
            .then((result) => {
                setReceipt(result.data.id);
                form.reset();
            })
            .catch((error: unknown) => setMessage(requestErrorMessage(error)));
    }

    return (
        <>
            <Head title="Reportar un incidente" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Comunidad"
                    title="Reporta lo que viste, con cuidado"
                    description="Tu aporte será revisado antes de aparecer en el mapa. No incluyas nombres ni datos que identifiquen a una persona."
                />
                {receipt && (
                    <div
                        role="status"
                        className="rounded-3xl border bg-card p-6"
                    >
                        <CheckCircle2
                            className="mb-2 size-8 text-success"
                            aria-hidden="true"
                        />
                        <h2 className="text-xl font-semibold">
                            Reporte recibido y guardado
                        </h2>
                        <p className="mt-2 text-sm">
                            Estado: pendiente de revisión. Referencia:{' '}
                            <span className="break-all">{receipt}</span>.
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            La moderadora no verá tu identidad. Un reporte
                            anónimo no conserva la relación con tu cuenta.
                        </p>
                        <Button
                            variant="outline"
                            className="mt-4"
                            onClick={() => setReceipt('')}
                        >
                            Hacer otro reporte
                        </Button>
                    </div>
                )}
                {!canReport && (
                    <FormErrors message="Tu cuenta no tiene permiso para enviar reportes." />
                )}
                {!mapConfiguration.coverageAvailable && (
                    <FormErrors message="La cobertura de Girardot todavía no está disponible. El envío se habilitará cuando se cargue el límite geográfico." />
                )}
                {!receipt && canReport && (
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        <FormErrors errors={form.errors} message={message} />
                        <SectionCard headingId="que-paso" title="¿Qué ocurrió?">
                            <fieldset
                                disabled={form.processing}
                                className="grid gap-5"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="categoria">
                                        Tipo de incidente
                                    </Label>
                                    <select
                                        id="categoria"
                                        required
                                        value={form.data.report_category_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'report_category_id',
                                                e.target.value,
                                            )
                                        }
                                        className="h-11 rounded-xl border border-input bg-background px-3"
                                    >
                                        <option value="">
                                            Selecciona una opción
                                        </option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="fecha">
                                        Fecha y hora del incidente · Colombia
                                    </Label>
                                    <Input
                                        id="fecha"
                                        type="datetime-local"
                                        required
                                        value={form.data.occurred_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'occurred_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="descripcion">
                                        Descripción (opcional)
                                    </Label>
                                    <textarea
                                        id="descripcion"
                                        maxLength={500}
                                        rows={4}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        className="rounded-xl border bg-background p-3"
                                        placeholder="Cuéntanos qué pasó, sin nombres ni datos personales."
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {form.data.description.length}/500
                                        caracteres.
                                    </p>
                                </div>
                            </fieldset>
                        </SectionCard>
                        <SectionCard
                            headingId="ubicacion"
                            title="Ubicación del incidente"
                            description="Toca el mapa, usa tu ubicación o introduce las coordenadas. Guardamos el punto para el análisis; el mapa público muestra áreas agregadas."
                        >
                            <div className="space-y-4">
                                <GeographicMap
                                    configuration={mapConfiguration}
                                    points={
                                        selected
                                            ? [
                                                  {
                                                      ...point,
                                                      label: 'Ubicación del incidente',
                                                  },
                                              ]
                                            : []
                                    }
                                    onSelect={(value) =>
                                        form.setData((current) => ({
                                            ...current,
                                            latitude: value.latitude.toFixed(6),
                                            longitude:
                                                value.longitude.toFixed(6),
                                        }))
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={
                                        location.locating || form.processing
                                    }
                                    onClick={() =>
                                        location.locate((value) =>
                                            form.setData((current) => ({
                                                ...current,
                                                latitude:
                                                    value.latitude.toFixed(6),
                                                longitude:
                                                    value.longitude.toFixed(6),
                                            })),
                                        )
                                    }
                                >
                                    <LocateFixed aria-hidden="true" />{' '}
                                    {location.locating
                                        ? 'Buscando ubicación…'
                                        : 'Usar mi ubicación'}
                                </Button>
                                <p
                                    role="status"
                                    className="text-sm text-muted-foreground"
                                >
                                    {location.message}
                                </p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="latitud">Latitud</Label>
                                        <Input
                                            id="latitud"
                                            type="number"
                                            step="any"
                                            min="-90"
                                            max="90"
                                            required
                                            value={form.data.latitude}
                                            onChange={(e) =>
                                                form.setData(
                                                    'latitude',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="longitud">
                                            Longitud
                                        </Label>
                                        <Input
                                            id="longitud"
                                            type="number"
                                            step="any"
                                            min="-180"
                                            max="180"
                                            required
                                            value={form.data.longitude}
                                            onChange={(e) =>
                                                form.setData(
                                                    'longitude',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        </SectionCard>
                        <SectionCard
                            headingId="privacidad"
                            title="Tu privacidad"
                        >
                            <div className="space-y-5">
                                <label className="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-5 accent-primary"
                                        checked={form.data.is_anonymous}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_anonymous',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        <strong>
                                            Reportar de forma anónima
                                        </strong>
                                        <span className="mt-1 block text-sm text-muted-foreground">
                                            El reporte no conserva tu cuenta ni
                                            tu IP. Si desactivas esta opción, se
                                            asociará a tu cuenta de forma
                                            privada. La sesión y la
                                            infraestructura tienen sus propias
                                            políticas de datos.
                                        </span>
                                    </span>
                                </label>
                                <div className="grid gap-2">
                                    <Label htmlFor="adjuntos">
                                        Imágenes opcionales · máximo 2
                                    </Label>
                                    <Input
                                        id="adjuntos"
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        multiple
                                        onChange={(e) =>
                                            form.setData(
                                                'attachments',
                                                Array.from(
                                                    e.target.files ?? [],
                                                ),
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        JPG o PNG, hasta 4 MB y 3000 × 3000
                                        píxeles por imagen. Retiramos metadatos;
                                        evita rostros, placas y cualquier
                                        contenido identificable. Solo las
                                        moderadoras autorizadas pueden
                                        descargarlas.
                                    </p>
                                </div>
                                <label className="flex items-start gap-3">
                                    <input
                                        required
                                        type="checkbox"
                                        className="mt-1 size-5 accent-primary"
                                        checked={form.data.consent}
                                        onChange={(e) =>
                                            form.setData(
                                                'consent',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        Entiendo que se enviarán la ubicación,
                                        la fecha y los datos que he indicado
                                        para revisar el incidente.
                                    </span>
                                </label>
                            </div>
                        </SectionCard>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={
                                form.processing ||
                                !mapConfiguration.coverageAvailable
                            }
                        >
                            {form.processing
                                ? 'Guardando reporte…'
                                : 'Enviar reporte'}
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
