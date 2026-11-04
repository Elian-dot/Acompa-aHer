/**
 * Registro del service worker. Se limita a contextos seguros y no interfiere con
 * el desarrollo local con Vite.
 */
export function registerServiceWorker(): void {
    if (
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator) ||
        !window.isSecureContext ||
        import.meta.env.DEV
    ) {
        return;
    }
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js', { scope: '/' });
    });
}
