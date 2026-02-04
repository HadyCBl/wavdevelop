const CACHE_NAME = 'microsystem-cache-v2';
const urlsToCache = [
  '/',
  '../../index.php',
  '../../404.php',
  '../includes/img/mguate.png'
];

// Instalación del Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Captura de solicitudes
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => 
      caches.match(event.request).then(response => 
        response || caches.match('../../404.php')
      )
    )
  );
});

// Activación del Service Worker
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (!cacheWhitelist.includes(cacheName)) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

