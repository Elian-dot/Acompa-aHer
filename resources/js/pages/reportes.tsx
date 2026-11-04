import { Head } from '@inertiajs/react';
import { CheckCircle2, LocateFixed, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import PageHeader from '@/components/ruta/page-header';
import SectionCard from '@/components/ruta/section-card';
import SimulationBanner from '@/components/ruta/simulation-banner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { TIPOS_INCIDENTE, ZONAS_EJEMPLO } from '@/lib/ruta';

const MAX_DESCRIPCION = 500;

export default function Reportes() {
    const [tipo, setTipo] = useState('');
    const [descripcion, setDescripcion] = useState('');
    const [zona, setZona] = useState('');
    const [anonimo, setAnonimo] = useState(true);
    const [consentimiento, setConsentimiento] = useState(false);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [enviado, setEnviado] = useState(false);
    const [ubicacionEstado, setUbicacionEstado] = useState('');

    const usarUbicacion = () => {
        if (!('geolocation' in navigator)) {
            setUbicacionEstado(
                'Tu dispositivo no permite obtener la ubicación. Elige la zona de la lista.',
            );

            return;
        }

        setUbicacionEstado('Buscando tu ubicación aproximada…');
        navigator.geolocation.getCurrentPosition(
            () => {
                setZona(ZONAS_EJEMPLO[0].id);
                setUbicacionEstado(
                    'Ubicación obtenida. Solo se guardará la zona aproximada, nunca tus coordenadas exactas.',
                );
            },
            () =>
                setUbicacionEstado(
                    'No pudimos acceder a tu ubicación. Elige la zona manualmente.',
                ),
            { timeout: 8000 },
        );
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        const nuevos: Record<string, string> = {};

        if (!tipo) {
            nuevos.tipo = 'Elige el tipo de incidente.';
        }

        if (!zona) {
            nuevos.zona = 'Elige una zona aproximada.';
        }

        if (descripcion.trim().length < 10) {
            nuevos.descripcion =
                'Cuéntanos un poco más (mínimo 10 caracteres).';
        }

        if (!consentimiento) {
            nuevos.consentimiento =
                'Necesitamos tu consentimiento para enviar el reporte.';
        }

        setErrores(nuevos);
        setEnviado(Object.keys(nuevos).length === 0);
    };

    if (enviado) {
        return (
            <>
                <Head title="Reporte enviado" />
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
                    <div
                        role="status"
                        className="rounded-3xl border bg-card p-8 text-center shadow-sm"
                    >
                        <CheckCircle2
                            className="mx-auto size-12 text-[#2e8b57]"
                            aria-hidden="true"
                        />
                        <h1 className="mt-4 text-2xl font-semibold">
                            Reporte recibido en el prototipo
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Gracias por aportar. En esta versión de prueba el
                            reporte no se guarda en un servidor. Cuando esté
                            activo, aparecerá en el mapa solo como parte de un
                            conteo por zona,{' '}
                            {anonimo
                                ? 'sin tu nombre'
                                : 'asociado a tu cuenta solo para moderación'}
                            .
                        </p>
                        <Button
                            className="mt-6 rounded-full"
                            onClick={() => {
                                setEnviado(false);
                                setTipo('');
                                setDescripcion('');
                                setZona('');
                                setConsentimiento(false);
                            }}
                        >
                            Hacer otro reporte
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Reportar un incidente" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="Comunidad"
                    title="Reporta lo que viste, con cuidado"
                    description="Tus reportes ayudan a otras mujeres a decidir mejor. Pedimos solo lo necesario y nunca publicamos datos personales."
                    ayuda={
                        <p>
                            Elige el tipo de incidente y una zona aproximada.
                            Puedes reportar de forma anónima. No incluyas
                            nombres, placas ni datos que identifiquen a nadie.
                        </p>
                    }
                />

                <SimulationBanner>
                    Este formulario es una demostración de la interfaz: todavía
                    no envía información a ningún servidor.
                </SimulationBanner>

                <form
                    onSubmit={enviar}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    <SectionCard headingId="que-paso" title="¿Qué ocurrió?">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="tipo">Tipo de incidente</Label>
                                <select
                                    id="tipo"
                                    value={tipo}
                                    onChange={(e) => setTipo(e.target.value)}
                                    aria-invalid={Boolean(errores.tipo)}
                                    aria-describedby="err-tipo"
                                    className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        Selecciona una opción
                                    </option>
                                    {TIPOS_INCIDENTE.filter(
                                        (t) => t.id !== 'todos',
                                    ).map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.etiqueta}
                                        </option>
                                    ))}
                                </select>
                                <p
                                    id="err-tipo"
                                    role="alert"
                                    className="text-sm text-red-700 dark:text-red-300"
                                >
                                    {errores.tipo}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="descripcion">Descripción</Label>
                                <textarea
                                    id="descripcion"
                                    value={descripcion}
                                    maxLength={MAX_DESCRIPCION}
                                    onChange={(e) =>
                                        setDescripcion(e.target.value)
                                    }
                                    rows={4}
                                    aria-invalid={Boolean(errores.descripcion)}
                                    aria-describedby="ayuda-desc err-desc"
                                    className="rounded-xl border border-input bg-background p-3 text-sm"
                                    placeholder="Ej. La calle no tiene luz entre la esquina y el parque."
                                />
                                <p
                                    id="ayuda-desc"
                                    className="text-xs text-muted-foreground"
                                >
                                    {descripcion.length}/{MAX_DESCRIPCION}.
                                    Evita nombres, placas o datos personales.
                                </p>
                                <p
                                    id="err-desc"
                                    role="alert"
                                    className="text-sm text-red-700 dark:text-red-300"
                                >
                                    {errores.descripcion}
                                </p>
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        headingId="donde"
                        title="¿Dónde?"
                        description="Usamos una zona aproximada para proteger tu privacidad."
                    >
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="zona">Zona aproximada</Label>
                                <select
                                    id="zona"
                                    value={zona}
                                    onChange={(e) => setZona(e.target.value)}
                                    aria-invalid={Boolean(errores.zona)}
                                    aria-describedby="err-zona"
                                    className="h-10 rounded-xl border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        Selecciona una zona
                                    </option>
                                    {ZONAS_EJEMPLO.map((z) => (
                                        <option key={z.id} value={z.id}>
                                            {z.nombre}
                                        </option>
                                    ))}
                                </select>
                                <p
                                    id="err-zona"
                                    role="alert"
                                    className="text-sm text-red-700 dark:text-red-300"
                                >
                                    {errores.zona}
                                </p>
                            </div>
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="rounded-full"
                                    onClick={usarUbicacion}
                                >
                                    <LocateFixed aria-hidden="true" /> Usar mi
                                    ubicación (opcional)
                                </Button>
                                <p
                                    role="status"
                                    className="mt-2 text-sm text-muted-foreground"
                                >
                                    {ubicacionEstado}
                                </p>
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        headingId="privacidad"
                        title="Privacidad y consentimiento"
                    >
                        <div className="space-y-4">
                            <div className="flex items-start gap-3 rounded-2xl bg-secondary/60 p-4">
                                <Checkbox
                                    id="anonimo"
                                    checked={anonimo}
                                    onCheckedChange={(v) =>
                                        setAnonimo(v === true)
                                    }
                                    className="mt-0.5"
                                />
                                <div>
                                    <Label htmlFor="anonimo">
                                        Enviar de forma anónima
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Tu nombre y correo no se asocian al
                                        reporte.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-2 text-sm text-muted-foreground">
                                <ShieldCheck
                                    className="text-purple mt-0.5 size-4 shrink-0 dark:text-lila"
                                    aria-hidden="true"
                                />
                                <p>
                                    Recopilamos: tipo de incidente, descripción,
                                    zona aproximada y fecha. No publicamos datos
                                    personales ni tu ubicación exacta.
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="consentimiento"
                                    checked={consentimiento}
                                    onCheckedChange={(v) =>
                                        setConsentimiento(v === true)
                                    }
                                    aria-describedby="err-consent"
                                    className="mt-0.5"
                                />
                                <Label
                                    htmlFor="consentimiento"
                                    className="font-normal"
                                >
                                    Entiendo qué información se recopila y
                                    autorizo su uso para el mapa comunitario.
                                </Label>
                            </div>
                            <p
                                id="err-consent"
                                role="alert"
                                className="text-sm text-red-700 dark:text-red-300"
                            >
                                {errores.consentimiento}
                            </p>
                        </div>
                    </SectionCard>

                    <Button type="submit" size="lg" className="rounded-full">
                        Enviar reporte
                    </Button>
                </form>
            </div>
        </>
    );
}
