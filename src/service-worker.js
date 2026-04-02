const CACHE_NAME = 'unimon-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/images/unimon-logo.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
