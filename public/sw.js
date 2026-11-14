// Service worker ligero: instala la app y cachea la interfaz. Los datos siempre vienen del servidor a bordo.
const VERSION = 'v1';
const CACHE = `salud-astro-${VERSION}`;
const OFFLINE = '/offline.html';

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/manifest.webmanifest', '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin) return;
    // Livewire y Reverb: nunca se cachean (datos en vivo).
    if (url.pathname.startsWith('/livewire') || url.pathname.startsWith('/broadcasting')) return;

    // Archivos compilados (nombre con hash): primero caché, se rellena al primer uso.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        e.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copia = res.clone();
                caches.open(CACHE).then((c) => c.put(req, copia));
                return res;
            }))
        );
        return;
    }

    // Páginas: primero el servidor; si no responde, la pantalla de aviso.
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    }
});
