/**
 * GenzSMP PWA Service Worker v2
 * CACHE BUSTED: security.js removed, old v1 cache purged
 */
const CACHE_NAME = 'genzsmp-pwa-v2';
const ASSETS_TO_CACHE = [
  '/',
  '/trading',
  '/images/logo.png',
  '/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Never cache API or JS requests - always go to network
  if (event.request.url.includes('/api/') || event.request.url.endsWith('.js')) {
    return;
  }

  // Network-first strategy: try network, fallback to cache
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});

