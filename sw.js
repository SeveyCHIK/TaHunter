const CACHE_NAME = 'tahunter-cache-v3';

self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    // Удаляем все кэши
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    return caches.delete(cache);
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', event => {
    // Временно отключаем кэширование, всегда берем из сети
    event.respondWith(fetch(event.request));
});