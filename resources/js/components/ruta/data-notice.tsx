import { Info } from 'lucide-react';
import { ACTUALIZACION_EJEMPLO, FUENTE_EJEMPLO } from '@/lib/ruta';

type Props = {
    reportes?: number;
    periodo?: string;
    confianza?: 'Baja' | 'Media' | 'Alta';
    children?: React.ReactNode;
};

export default function DataNotice({
    reportes,
    periodo,
    confianza,
    children,
}: Props) {
    return (
        <aside
            aria-label="Fuente y límites de los datos"
            className="border-purple/40 rounded-2xl border border-dashed bg-secondary/50 p-4 text-sm dark:border-lila/40"
        >
            <div className="flex items-start gap-3">
                <Info
                    className="text-purple mt-0.5 size-4 shrink-0 dark:text-lila"
                    aria-hidden="true"
                />
                <div className="space-y-2">
                    <dl className="flex flex-wrap gap-x-6 gap-y-1">
                        <div className="flex gap-1.5">
                            <dt className="font-semibold">Fuente:</dt>
                            <dd className="text-muted-foreground">
                                {FUENTE_EJEMPLO}
                            </dd>
                        </div>
                        {periodo && (
                            <div className="flex gap-1.5">
                                <dt className="font-semibold">Periodo:</dt>
                                <dd className="text-muted-foreground">
                                    {periodo}
                                </dd>
                            </div>
                        )}
                        {reportes !== undefined && (
                            <div className="flex gap-1.5">
                                <dt className="font-semibold">Reportes:</dt>
                                <dd className="text-muted-foreground">
                                    {reportes}
                                </dd>
                            </div>
                        )}
                        {confianza && (
                            <div className="flex gap-1.5">
                                <dt className="font-semibold">Confianza:</dt>
                                <dd className="text-muted-foreground">
                                    {confianza}
                                </dd>
                            </div>
                        )}
                        <div className="flex gap-1.5">
                            <dt className="font-semibold">Actualizado:</dt>
                            <dd className="text-muted-foreground">
                                {ACTUALIZACION_EJEMPLO}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-muted-foreground">
                        {children ??
                            'Los reportes son aportes de la comunidad y no hechos verificados. Que una zona tenga pocos reportes no significa que sea segura.'}
                    </p>
                </div>
            </div>
        </aside>
    );
}
