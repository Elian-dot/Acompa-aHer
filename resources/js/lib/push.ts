/**
 * Utilidades de Web Push compartidas por la usuaria y por los contactos.
 */
export type PushSupport = 'supported' | 'unsupported' | 'insecure';

export function pushSupport(): PushSupport {
    if (typeof window === 'undefined') return 'unsupported';
    if (!window.isSecureContext) return 'insecure';
    return 'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
        ? 'supported'
        : 'unsupported';
}

function urlBase64ToUint8Array(value: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const output = new Uint8Array(new ArrayBuffer(raw.length));
    for (let index = 0; index < raw.length; index += 1) {
        output[index] = raw.charCodeAt(index);
    }
    return output;
}

export async function currentSubscription(): Promise<PushSubscription | null> {
    if (pushSupport() !== 'supported') return null;
    const registration = await navigator.serviceWorker.ready;
    return registration.pushManager.getSubscription();
}

export async function subscribeToPush(
    publicKey: string,
): Promise<PushSubscription> {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('permission-denied');
    }
    const registration = await navigator.serviceWorker.ready;
    const existing = await registration.pushManager.getSubscription();
    return (
        existing ??
        registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        })
    );
}

export function subscriptionBody(subscription: PushSubscription) {
    const json = subscription.toJSON();
    return {
        endpoint: subscription.endpoint,
        keys: {
            p256dh: json.keys?.p256dh ?? '',
            auth: json.keys?.auth ?? '',
        },
        contentEncoding: 'aes128gcm',
        expirationTime: subscription.expirationTime,
    };
}

/**
 * Guarda en el dispositivo la dirección privada del seguimiento. El servidor no
 * conserva el enlace, así que la notificación lo recupera desde aquí al tocarla.
 */
export async function rememberTrackingPath(
    participantId: number,
    path: string,
): Promise<void> {
    if (!('caches' in window)) return;
    const cache = await caches.open('acompaniaher-tracking');
    await cache.put(
        `/__tracking/${participantId}`,
        new Response(path, { headers: { 'Content-Type': 'text/plain' } }),
    );
}
