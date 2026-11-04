import type { Delivery, ParticipantStatus } from '@/types/journey';

export const INACTIVITY_PREFERENCE_KEY = 'acompaniaher.inactivity_minutes';

export function storedInactivityMinutes(fallback: number): number {
    try {
        const value = Number(localStorage.getItem(INACTIVITY_PREFERENCE_KEY));
        return Number.isFinite(value) && value > 0 ? value : fallback;
    } catch {
        return fallback;
    }
}

export function storeInactivityMinutes(minutes: number): void {
    try {
        localStorage.setItem(INACTIVITY_PREFERENCE_KEY, String(minutes));
    } catch {
        // Sin almacenamiento local la preferencia simplemente no se recuerda.
    }
}

export const participantStatusLabel: Record<ParticipantStatus, string> = {
    pending: 'Aún no abre el enlace',
    opened: 'Ya abrió el enlace',
    revoked: 'Acceso retirado',
    expired: 'Enlace vencido',
};

const failureLabels: Record<string, string> = {
    no_subscription: 'No tiene avisos activados en ningún dispositivo.',
    subscription_expired: 'Su dispositivo ya no recibe avisos.',
    push_error: 'El servicio de notificaciones no respondió.',
    push_not_configured: 'Los avisos no están configurados en el servidor.',
    alert_resolved: 'Se canceló porque la alerta ya se había resuelto.',
    journey_closed: 'Se canceló porque el acompañamiento terminó.',
    participant_inactive: 'El enlace ya no estaba vigente.',
};

export function deliveryLabel(delivery: Delivery): string {
    switch (delivery.status) {
        case 'sent':
            return 'Entregado al servicio de notificaciones. Esto no confirma que lo haya visto.';
        case 'pending':
            return 'Enviando…';
        default:
            return (
                failureLabels[delivery.failure_code ?? ''] ??
                'No se pudo entregar.'
            );
    }
}
