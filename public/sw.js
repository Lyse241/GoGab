/*
 * Service worker de Gogab (PWA).
 *
 * Volontairement minimal : l'application affiche des données personnelles et en temps réel
 * (commandes, offres, soldes), donc AUCUNE page ni réponse d'API n'est mise en cache.
 *  - navigation sans réseau : page « Pas de connexion » (/offline.html) ;
 *  - fichiers de /build/assets (nom avec empreinte, jamais modifiés) : cache d'abord ;
 *  - tout le reste (Inertia, JSON, POST, autres sites) : réseau, sans intervention.
 * Changer VERSION invalide l'ancien cache.
 */
const VERSION = 'gogab-v2';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(VERSION)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Notification push reçue (application fermée comprise) : { title, body, url }.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Gogab', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag || undefined,
            data: { url: data.url || '/' },
        }),
    );
});

// Toucher la notification : ouvre (ou ramène au premier plan) la page concernée.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => new URL(client.url).origin === self.location.origin);
            if (open) {
                return open.focus().then(() => open.navigate(target));
            }

            return self.clients.openWindow(target);
        }),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Ouverture d'une page : réseau, et la page « hors ligne » seulement si le réseau échoue.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Fichiers compilés, à empreinte : cache d'abord.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(VERSION).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
});
