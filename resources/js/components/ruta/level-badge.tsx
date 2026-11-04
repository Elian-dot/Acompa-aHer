import { cn } from '@/lib/utils';
import { NIVELES } from '@/lib/ruta';
import type { NivelRiesgo } from '@/lib/ruta';

type Props = {
    nivel: NivelRiesgo;
    className?: string;
};

/**
 * Indicador de nivel: color + patrón + texto, nunca solo color.
 */
export default function LevelBadge({ nivel, className }: Props) {
    const info = NIVELES[nivel];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 rounded-full border bg-card px-2.5 py-1 text-xs font-semibold',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'size-3.5 rounded-sm border border-foreground/40',
                    info.patron,
                )}
                style={{ backgroundColor: info.color, color: '#24112f' }}
            />
            {info.etiqueta}
        </span>
    );
}
