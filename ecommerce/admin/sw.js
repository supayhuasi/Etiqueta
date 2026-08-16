const CACHE_NAME = 'admin-pwa-v1';
const PRECACHE_URLS = [
    'assets/pwa/icon-192.png',
    'assets/pwa/icon-512.png',
    'assets/pwa/icon-192-maskable.png',
    'assets/pwa/icon-512-maskable.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

// Solo se sirven desde caché los íconos estáticos; el resto (páginas PHP,
// datos) siempre va a la red para evitar servir contenido desactualizado
// o de otra sesión.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin === self.location.origin && PRECACHE_URLS.some((p) => url.pathname.endsWith(p))) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});
