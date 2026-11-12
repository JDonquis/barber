/*
 * Service Worker — PWA de clientes por barbería.
 *
 * Se registra con scope /reservar/{slug}. Su función principal es recibir
 * notificaciones Web Push y abrir el flujo de reserva al tocarlas.
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', () => {
    // El navegador maneja las navegaciones de forma nativa.
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }

    const title = data.title || 'Tu barbería';
    const options = {
        body: data.body || 'Tienes un nuevo recordatorio.',
        icon: data.icon || undefined,
        badge: data.icon || undefined,
        tag: data.tag || 'barber-reminder',
        renotify: true,
        data: {
            url: data.url || '/',
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : '/';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            try {
                const clientUrl = new URL(client.url);
                const targetUrl = new URL(url, self.location.origin);

                if (clientUrl.origin === targetUrl.origin) {
                    await client.focus();

                    if (client.navigate) {
                        await client.navigate(targetUrl.href);
                    }

                    return;
                }
            } catch (error) {
                // Continúa con la siguiente ventana.
            }
        }

        if (self.clients.openWindow) {
            await self.clients.openWindow(url);
        }
    })());
});
