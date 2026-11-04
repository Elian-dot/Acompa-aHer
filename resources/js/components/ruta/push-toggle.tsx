import { Bell, BellOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { usePushDevice } from '@/hooks/use-push-device';

type Props = {
    publicKey: string | null;
    subscribeUrl: string;
    unsubscribeUrl: string;
    tracking?: { path: string };
    description: string;
};

/**
 * Activa los avisos push en este dispositivo, con mensajes claros cuando el
 * navegador no los permite (sin HTTPS, permiso denegado o sin soporte).
 */
export default function PushToggle({ description, ...options }: Props) {
    const { support, status, message, busy, enable, disable } =
        usePushDevice(options);

    if (support === 'insecure') {
        return (
            <p className="rounded-2xl border p-4 text-sm text-muted-foreground">
                Los avisos solo funcionan en conexiones seguras (HTTPS). Abre la
                aplicación desde su dirección segura.
            </p>
        );
    }

    if (support === 'unsupported') {
        return (
            <p className="rounded-2xl border p-4 text-sm text-muted-foreground">
                Este navegador no admite notificaciones. En iPhone, instala
                primero la aplicación desde “Compartir → Añadir a pantalla de
                inicio”.
            </p>
        );
    }

    return (
        <div className="space-y-3 rounded-2xl border p-4">
            <p className="text-sm text-muted-foreground">{description}</p>
            <div className="flex flex-wrap items-center gap-3">
                {status === 'on' ? (
                    <>
                        <span className="inline-flex items-center gap-2 text-sm font-semibold text-success">
                            <Bell className="size-4" aria-hidden="true" />{' '}
                            Avisos activados en este dispositivo
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void disable()}
                            disabled={busy}
                        >
                            <BellOff aria-hidden="true" /> Desactivar
                        </Button>
                    </>
                ) : (
                    <Button
                        type="button"
                        onClick={() => void enable()}
                        disabled={
                            busy || status === 'denied' || status === 'unknown'
                        }
                    >
                        <Bell aria-hidden="true" /> Activar avisos
                    </Button>
                )}
            </div>
            {status === 'denied' && (
                <p role="status" className="text-sm text-danger">
                    Bloqueaste las notificaciones de este sitio. Permítelas en
                    los ajustes del navegador para recibir avisos.
                </p>
            )}
            {message && (
                <p role="status" className="text-sm">
                    {message}
                </p>
            )}
        </div>
    );
}
