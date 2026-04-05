/**
 * Liga WOC - Service Worker
 * Caches static assets for fast loads and basic offline support.
 */
const CACHE_NAME = 'ligawoc-v1';
const STATIC_ASSETS = [
    '/LIGAWOC/',
    '/LIGAWOC/assets/css/main.css',
    '/LIGAWOC/assets/css/esports.css',
    '/LIGAWOC/assets/js/app.js',
    '/LIGAWOC/assets/img/logo.png',
];

// Install: cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS).catch(() => {
                // Non-fatal — some assets may not exist yet
            });
        })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first for API/PHP, cache-first for static assets
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Skip non-GET and cross-origin requests
    if (event.request.method !== 'GET' || url.origin !== location.origin) return;

    // API routes and PHP pages: network first, no caching
    if (url.pathname.includes('/api/') || url.pathname.endsWith('.php')) {
        return; // Let the browser handle normally
    }

    // Static assets: cache first, then network
    if (url.pathname.match(/\.(css|js|png|jpg|webp|svg|woff2?)$/)) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                }).catch(() => cached);
            })
        );
    }
});
