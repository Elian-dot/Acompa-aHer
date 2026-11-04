import { Info } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    source: string;
    period?: string;
    sampleSize?: number;
    confidence?: string;
    updatedAt?: string;
    children?: ReactNode;
};

/**
 * Fuente, periodo, tamaño de muestra y límites de lo que se muestra. Siempre visible.
 */
export default function DataNotice({
    source,
    period,
    sampleSize,
    confidence,
    updatedAt,
    children,
}: Props) {
    const items: [string, string][] = [['Fuente', source]];
    if (period) items.push(['Periodo', period]);
    if (sampleSize !== undefined) items.push(['Reportes', String(sampleSize)]);
    if (confidence) items.push(['Confianza', confidence]);
    if (updatedAt) items.push(['Actualizado', updatedAt]);

    return (
        <aside
            aria-label="Fuente y límites de los datos"
            className="rounded-2xl border border-dashed border-purple/40 bg-secondary/50 p-4 text-sm dark:border-lila/40"
        >
            <div className="flex items-start gap-3">
                <Info
                    className="mt-0.5 size-4 shrink-0 text-purple dark:text-lila"
                    aria-hidden="true"
                />
                <div className="space-y-2">
                    <dl className="flex flex-wrap gap-x-6 gap-y-1">
                        {items.map(([label, value]) => (
                            <div key={label} className="flex gap-1.5">
                                <dt className="font-semibold">{label}:</dt>
                                <dd className="text-muted-foreground">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {children && (
                        <p className="text-muted-foreground">{children}</p>
                    )}
                </div>
            </div>
        </aside>
    );
}
