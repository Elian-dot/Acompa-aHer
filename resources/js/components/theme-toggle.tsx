import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

/**
 * Alterna entre tema claro y oscuro. El modo "sistema" sigue disponible
 * en Ajustes → Apariencia.
 */
export default function ThemeToggle({ className }: Props) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={
                isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'
            }
            title={isDark ? 'Tema claro' : 'Tema oscuro'}
            className={cn(
                'inline-flex size-10 items-center justify-center rounded-full border border-current/25 transition hover:bg-current/10 focus-visible:outline-3 focus-visible:outline-lila',
                className,
            )}
        >
            {isDark ? (
                <Sun className="size-4" aria-hidden="true" />
            ) : (
                <Moon className="size-4" aria-hidden="true" />
            )}
        </button>
    );
}
