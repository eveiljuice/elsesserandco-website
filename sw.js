/**
 * Elsesser & Co. — Service Worker
 *
 * Стратегии:
 *  - Statics (CSS/JS/шрифты/иконки)  → stale-while-revalidate
 *  - HTML (страницы)                 → network-first с offline fallback
 *  - Картинки                        → cache-first с лимитом
 *  - API (/php/, /includes/, POST)   → пропускается (network only)
 *
 * Также обрабатывает push-уведомления (#15).
 */

const VERSION = 'eco-v1';
const STATIC_CACHE  = `static-${VERSION}`;
const PAGES_CACHE   = `pages-${VERSION}`;
const IMAGES_CACHE  = `images-${VERSION}`;

const STATIC_ASSETS = [
    '/',
    '/css/reset.css',
    '/css/variables.css',
    '/css/style.css',
    '/css/responsive.css',
    '/js/navigation.js',
    '/js/favorites.js',
    '/js/autocomplete.js',
    '/manifest.webmanifest',
    '/offline.html',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => ![STATIC_CACHE, PAGES_CACHE, IMAGES_CACHE].includes(k))
                .map(k => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Не кешируем API/админку/чат — всегда сеть
    if (url.pathname.startsWith('/php/') ||
        url.pathname.startsWith('/includes/') ||
        url.pathname.startsWith('/admin/') ||
        url.pathname.startsWith('/agent/') ||
        url.pathname.startsWith('/oauth/')) {
        return;
    }

    // Картинки
    if (req.destination === 'image') {
        event.respondWith(cacheFirst(req, IMAGES_CACHE, 60));
        return;
    }

    // Статика
    if (['style', 'script', 'font'].includes(req.destination)) {
        event.respondWith(staleWhileRevalidate(req, STATIC_CACHE));
        return;
    }

    // HTML / прочее
    if (req.mode === 'navigate' || req.destination === 'document' || req.destination === '') {
        event.respondWith(networkFirst(req, PAGES_CACHE));
    }
});

async function cacheFirst(req, cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    if (cached) return cached;
    try {
        const resp = await fetch(req);
        if (resp.ok) {
            cache.put(req, resp.clone());
            trimCache(cacheName, maxEntries);
        }
        return resp;
    } catch { return cached || new Response('', { status: 504 }); }
}

async function staleWhileRevalidate(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req);
    const fetchPromise = fetch(req).then(resp => {
        if (resp.ok) cache.put(req, resp.clone());
        return resp;
    }).catch(() => cached);
    return cached || fetchPromise;
}

async function networkFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    try {
        const resp = await fetch(req);
        if (resp.ok) cache.put(req, resp.clone());
        return resp;
    } catch {
        const cached = await cache.match(req);
        return cached || caches.match('/offline.html');
    }
}

async function trimCache(cacheName, maxEntries) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length > maxEntries) {
        await cache.delete(keys[0]);
        trimCache(cacheName, maxEntries);
    }
}

// =====================
// Web Push (#15)
// =====================
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { title: 'Elsesser & Co.', body: event.data && event.data.text() }; }

    const title   = data.title || 'Elsesser & Co.';
    const options = {
        body:  data.body  || '',
        icon:  data.icon  || '/images/favicon.png',
        badge: data.badge || '/images/favicon.png',
        data:  { url: data.url || '/' },
        tag:   data.tag   || 'eco-notification',
        renotify: !!data.renotify,
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            const existing = list.find(c => c.url.includes(self.location.origin));
            if (existing) { existing.focus(); existing.navigate(target); return; }
            self.clients.openWindow(target);
        })
    );
});
