/*
 * Zion POS service worker — offline fallback (PHP-PLAN §3).
 *
 *  - App shell (POS + assets) is precached on first visit so the till loads
 *    with no network.
 *  - Same-origin GET pages & assets go network-first; the last working copy
 *    is cached as it comes back, so a repeat visit works offline.
 *  - POST writes are never intercepted: they come from offline.js, which
 *    already queues them in IndexedDB and flushes on reconnect.
 */
const CACHE = 'zion-pos-v3';
const SHELL = '/pos';
const PRECACHE = [
    SHELL,
    '/css/app.css',
    '/vendor/bootstrap/bootstrap.min.css',
    '/vendor/bootstrap/bootstrap.bundle.min.js',
    '/vendor/alpine.min.js',
    '/js/pos.js',
    '/js/offline.js',
    '/icon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) =>
            Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => null)))
                .then(() => self.skipWaiting()),
        ),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    // Never serve another user's data out of the cache.
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/settings')) return;

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/vendor/'))) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(url.pathname, copy));
                }
                return response;
            })
            .catch(async () => {
                const hits = await caches.match(url.pathname, { ignoreSearch: true });
                if (hits) return hits;
                if (request.mode === 'navigate') return (await caches.match(SHELL)) || Response.error();
                return Response.error();
            }),
    );
});
