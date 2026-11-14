/*
 * Service Worker — DonBarber
 *
 * Estrategia:
 *  - Navegación: el navegador la maneja de forma nativa para respetar redirects
 *    (login, logout, OAuth, sesión expirada). Sin conexión, muestra /offline.html.
 *  - Estáticos (build, iconos, logo, fuentes): cache-first con revalidación en segundo plano.
 *  - Ignora peticiones que no sean GET y las de otros orígenes (salvo fuentes).
 *
 * Para publicar cambios: incrementa CACHE_VERSION.
 */
const CACHE_VERSION = 'donbarber-v7';
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/donbarberlogo.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
];

const FONT_HOSTS = [
    'fonts.bunny.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))
            )
            .then(() => self.clients.claim())
    );
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/donbarberlogo.png' ||
        url.pathname === '/manifest.webmanifest' ||
        /\.(css|js|mjs|png|jpe?g|svg|webp|gif|ico|woff2?|ttf|eot)$/.test(url.pathname)
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // La PWA de clientes por barbería maneja su propio service worker.
    if (url.pathname.startsWith('/reservar/')) {
        return;
    }

    // Fuentes externas: cache-first.
    if (url.origin !== self.location.origin) {
        if (FONT_HOSTS.includes(url.host)) {
            event.respondWith(
                caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
                    return response;
                }).catch(() => cached))
            );
        }
        return;
    }

    // Navegación: con conexión dejamos que el navegador la maneje de forma
    // nativa. Así puede seguir redirects (login, logout, OAuth, sesión expirada)
    // sin que respondWith rechace una respuesta redirigida. Sin conexión,
    // mostramos la página offline precargada.
    if (request.mode === 'navigate') {
        if (self.navigator.onLine === false) {
            event.respondWith(caches.match(OFFLINE_URL));
        }

        return;
    }

    // Estáticos: cache-first con revalidación.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response && response.status === 200 && response.type === 'basic' && !response.redirected) {
                            const copy = response.clone();
                            caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    })
                    .catch(() => cached);

                return cached || network;
            })
        );
    }
});
