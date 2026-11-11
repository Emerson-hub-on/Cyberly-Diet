// Service worker do Cyberly Diet.
// Não guarda dados do usuário: as páginas sempre vêm da internet.
// Sem conexão, mostra a página "Sem conexão com internet".
const CACHE = 'cyberly-offline-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return; // CSS/JS/fotos/envios seguem o caminho normal

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
