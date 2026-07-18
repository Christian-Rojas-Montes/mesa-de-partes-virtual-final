const CACHE_VERSION = 'mpv-static-v2';
const STATIC_RESOURCES = [
    '/offline.html',
    '/images/logo-pedro-p-diaz.jpg',
    '/manifest.webmanifest',
    '/icons/app-icon-192.svg',
    '/icons/app-icon-512.svg',
    '/icons/app-icon-maskable.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(STATIC_RESOURCES)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    const privatePathPrefixes = [
        '/panel/', '/consulta-expedientes', '/reportes', '/notificaciones',
        '/api/', '/sanctum/', '/storage/',
    ];
    const isPrivatePath = privatePathPrefixes.some((prefix) => url.pathname.startsWith(prefix))
        || url.pathname.includes('/documentos/')
        || url.pathname.includes('/respuesta/');

    if (isPrivatePath) {
        event.respondWith(fetch(request, { cache: 'no-store', credentials: 'same-origin' }));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request, { cache: 'no-store', credentials: 'same-origin' }).catch(() => caches.match('/offline.html')));
        return;
    }

    const isSafeStaticResource = url.pathname.startsWith('/build/assets/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.webmanifest';

    if (!isSafeStaticResource) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request, { credentials: 'omit' }).then((response) => {
            const cacheControl = response.headers.get('Cache-Control') || '';
            if (response.ok && response.type === 'basic' && !cacheControl.includes('no-store') && !response.headers.has('Set-Cookie')) {
                const copy = response.clone();
                caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
            }

            return response;
        })),
    );
});
