const CACHE_NAME = 'dr-arman-care-v3';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/manifest.json',
];

// Install: cache the app shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch: network-first for API/external, cache-first for assets
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET and chrome-extension requests
  if (event.request.method !== 'GET') return;
  if (url.protocol === 'chrome-extension:') return;

  // Network-first for API calls (PHP backend) - don't cache API responses
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request, { cache: 'no-store' })
        .then(response => {
          // Clone and cache successful GET responses for offline fallback
          if (response.ok) {
            const toCache = response.clone();
            caches.open(CACHE_NAME + '-api').then(cache => {
              cache.put(event.request, toCache);
            });
          }
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Network-first for external resources
  if (
    url.hostname.includes('whatsapp') ||
    url.hostname.includes('fonts.googleapis') ||
    url.hostname.includes('fonts.gstatic')
  ) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request))
    );
    return;
  }

  // Cache-first for everything else (app shell, assets)
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached;
      return fetch(event.request).then((response) => {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }
        const toCache = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, toCache));
        return response;
      }).catch(() => {
        // Fallback to index.html for navigation requests
        if (event.request.mode === 'navigate') {
          return caches.match('/index.html');
        }
      });
    })
  );
});
