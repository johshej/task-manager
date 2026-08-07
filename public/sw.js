// Minimal service worker: exists only to satisfy PWA installability criteria.
// No caching - this app is Livewire-driven and always needs a live network
// round-trip, so an offline/stale-cache experience would be worse than none.
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
