/* AcompañaHer: service worker.
 * - Guarda solo recursos estáticos y la página sin conexión.
 * - Nunca guarda páginas ni respuestas de la API: contienen datos personales y de ubicación.
 * - Muestra las notificaciones push de alertas y abre el seguimiento al tocarlas.
 */
const VERSION = 'v1';
const STATIC_CACHE = `acompaniaher-static-${VERSION}`;
const TRACKING_CACHE = 'acompaniaher-tracking';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/favicon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key !== STATIC_CACHE && key !== TRACKING_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/favicon.svg'
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (
        request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        url.pathname.startsWith('/api/')
    ) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(STATIC_CACHE)
                                .then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }

    event.waitUntil(
        self.registration.showNotification(
            data.title || 'Aviso de AcompañaHer',
            {
                body: data.body || 'Abre la aplicación para ver el aviso.',
                tag: data.tag || 'acompaniaher-alert',
                icon: '/icons/icon-192.png',
                badge: '/icons/icon-192.png',
                lang: 'es',
                requireInteraction: true,
                renotify: true,
                vibrate: [250, 120, 250, 120, 250],
                data: {
                    url: data.url || '/',
                    participant: data.participant || null,
                },
            },
        ),
    );
});

async function resolveTarget(data) {
    if (data.participant) {
        const cache = await caches.open(TRACKING_CACHE);
        const stored = await cache.match(`/__tracking/${data.participant}`);
        if (stored) {
            const path = await stored.text();
            if (path.startsWith('/seguimiento/')) {
                return path;
            }
        }
    }
    return data.url || '/';
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        (async () => {
            const target = new URL(
                await resolveTarget(event.notification.data || {}),
                self.location.origin,
            ).href;
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });
            const open =
                windows.find((client) => client.url === target) || windows[0];
            if (open) {
                if ('navigate' in open && open.url !== target) {
                    await open.navigate(target);
                }
                return open.focus();
            }
            return self.clients.openWindow(target);
        })(),
    );
});
