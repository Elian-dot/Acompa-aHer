import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    currentSubscription,
    pushSupport,
    rememberTrackingPath,
    subscribeToPush,
    subscriptionBody,
} from '@/lib/push';
import type { PushSupport } from '@/lib/push';

type Options = {
    publicKey: string | null;
    subscribeUrl: string;
    unsubscribeUrl: string;
    /** Solo para contactos: se guarda la dirección privada para abrirla al tocar el aviso. */
    tracking?: { path: string };
};

type Status = 'unknown' | 'off' | 'on' | 'denied';

/**
 * Activa o desactiva las notificaciones push de este dispositivo.
 */
export function usePushDevice({
    publicKey,
    subscribeUrl,
    unsubscribeUrl,
    tracking,
}: Options) {
    const http = useHttp<Record<string, never>, { participant?: number }>({});
    const [support, setSupport] = useState<PushSupport>('supported');
    const [status, setStatus] = useState<Status>('unknown');
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const supportValue = pushSupport();
        setSupport(supportValue);
        if (supportValue !== 'supported') return;
        if (Notification.permission === 'denied') {
            setStatus('denied');
            return;
        }
        void currentSubscription().then((subscription) =>
            setStatus(subscription ? 'on' : 'off'),
        );
    }, []);

    async function enable() {
        if (!publicKey) {
            setMessage(
                'Las notificaciones aún no están configuradas en el servidor.',
            );
            return;
        }
        setBusy(true);
        setMessage('');
        try {
            const subscription = await subscribeToPush(publicKey);
            http.transform(() => subscriptionBody(subscription));
            const response = await http.post(subscribeUrl);
            if (tracking && response.participant) {
                await rememberTrackingPath(response.participant, tracking.path);
            }
            setStatus('on');
            setMessage('Notificaciones activadas en este dispositivo.');
        } catch (error) {
            if (
                error instanceof Error &&
                error.message === 'permission-denied'
            ) {
                setStatus('denied');
                setMessage(
                    'El navegador bloqueó las notificaciones. Permítelas en los ajustes del sitio y vuelve a intentarlo.',
                );
            } else {
                setMessage(
                    'No pudimos activar las notificaciones. Revisa tu conexión e inténtalo de nuevo.',
                );
            }
        } finally {
            setBusy(false);
        }
    }

    async function disable() {
        setBusy(true);
        setMessage('');
        try {
            const subscription = await currentSubscription();
            if (subscription) {
                http.transform(() => ({ endpoint: subscription.endpoint }));
                await http.delete(unsubscribeUrl);
                await subscription.unsubscribe();
            }
            setStatus('off');
            setMessage('Notificaciones desactivadas en este dispositivo.');
        } catch {
            setMessage('No pudimos desactivar las notificaciones.');
        } finally {
            setBusy(false);
        }
    }

    return { support, status, message, busy, enable, disable };
}
