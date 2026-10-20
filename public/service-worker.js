'use strict';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = {};
    }

    const title = typeof payload.title === 'string'
        ? payload.title
        : 'Open Phonebook';
    const body = typeof payload.body === 'string'
        ? payload.body
        : '';
    const url = typeof payload.url === 'string'
        && payload.url.startsWith('/')
        ? payload.url
        : '/';

    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            icon: '/assets/pwa-icon.svg',
            badge: '/assets/pwa-icon.svg',
            data: {url},
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const path = event.notification.data
        && typeof event.notification.data.url === 'string'
        ? event.notification.data.url
        : '/';

    event.waitUntil((async () => {
        const target = new URL(path, self.location.origin);
        const windows = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of windows) {
            if (new URL(client.url).origin !== target.origin) {
                continue;
            }

            if ('navigate' in client) {
                await client.navigate(target.href);
            }

            return client.focus();
        }

        return self.clients.openWindow(target.href);
    })());
});
