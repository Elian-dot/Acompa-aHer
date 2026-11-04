import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';

/**
 * Ofrece instalar AcompañaHer como aplicación cuando el navegador lo permite.
 */
export default function InstallAppButton({
    className,
}: {
    className?: string;
}) {
    const { canInstall, install } = useInstallPrompt();

    if (!canInstall) return null;

    return (
        <Button
            type="button"
            variant="outline"
            className={className}
            onClick={() => void install()}
        >
            <Download aria-hidden="true" /> Instalar la aplicación
        </Button>
    );
}
