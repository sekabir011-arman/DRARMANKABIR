const CACHE_NAME = 'dr-arman-care-v4';
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

  // Network-first for API calls (PHP backend)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request, { cache: 'no-store' })
        .then(response => {
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

  // Network-first for JS assets — ensures dynamic imports always get the latest version
  // (prevents React hook errors from mismatched React instances across chunks)
  if (url.pathname.startsWith('/assets/') && url.pathname.endsWith('.js')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (!response || response.status !== 200 || response.type === 'opaque') {
            return response;
          }
          const toCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, toCache));
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Cache-first for non-JS static assets (CSS, fonts, images)
  if (url.pathname.startsWith('/assets/')) {
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
          if (event.request.mode === 'navigate') {
            return caches.match('/index.html');
          }
        });
      })
    );
    return;
  }

  // Cache-first for everything else (app shell, other assets)
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
});const CACHE_NAME = 'dr-arman-care-v4';
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
const CACHE_NAME = 'dr-arman-care-v4';
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

/**
 * Strip cache-busting query params from the URL for consistent cache keys.
 */
function cacheKeyUrl(requestUrl) {
  const url = new URL(requestUrl);
  // Strip common cache-busting query params
  url.search = '';
  // Normalize trailing slash
  if (url.pathname.endsWith('/') && url.pathname.length > 1) {
    url.pathname = url.pathname.slice(0, -1);
  }
  return url.toString();
}

/**
 * Network-first strategy: try the network, fallback to cache.
 * Used for JavaScript and CSS bundles to ensure users always get the latest code.
 */
function networkFirst(event, cacheName) {
  const cacheKey = event.request.url;
  return fetch(event.request)
    .then((response) => {
      if (!response || response.status !== 200 || response.type === 'opaque') {
        return response;
      }
      const toCache = response.clone();
      caches.open(cacheName).then((cache) => {
        cache.put(cacheKey, toCache);
      });
      return response;
    })
    .catch(() => caches.match(cacheKey));
}

/**
 * Cache-first strategy: serve from cache, fallback to network.
 * Used for images, fonts, and other static assets that rarely change.
 */
function cacheFirst(event, cacheName) {
  const cacheKey = event.request.url;
  return caches.match(cacheKey).then((cached) => {
    if (cached) return cached;
    return fetch(event.request).then((response) => {
      if (!response || response.status !== 200 || response.type === 'opaque') {
        return response;
      }
      const toCache = response.clone();
      caches.open(cacheName).then((cache) => cache.put(cacheKey, toCache));
      return response;
    });
  });
}

// Fetch: network-first for API/external, cache-first for assets
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET and chrome-extension requests
  if (event.request.method !== 'GET') return;
  if (url.protocol === 'chrome-extension:') return;

  // Network-first for API calls (PHP backend)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request, { cache: 'no-store' })
        .then(response => {
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

  // ─── Asset-specific strategies ─────────────────────────────────────────

  const pathname = url.pathname;

  // Network-first for JavaScript bundles (critical: avoid stale React instances)
  if (pathname.endsWith('.js')) {
    event.respondWith(networkFirst(event, CACHE_NAME));
    return;
  }

  // Network-first for CSS (refresh on change)
  if (pathname.endsWith('.css')) {
    event.respondWith(networkFirst(event, CACHE_NAME));
    return;
  }

  // Network-first for HTML (always get fresh page)
  if (pathname.endsWith('.html') || pathname === '/') {
    event.respondWith(networkFirst(event, CACHE_NAME));
    return;
  }

  // Cache-first for fonts, images, and other static assets
  if (
    pathname.endsWith('.woff2') ||
    pathname.endsWith('.woff') ||
    pathname.endsWith('.ttf') ||
    pathname.endsWith('.eot') ||
    pathname.endsWith('.png') ||
    pathname.endsWith('.jpg') ||
    pathname.endsWith('.jpeg') ||
    pathname.endsWith('.gif') ||
    pathname.endsWith('.svg') ||
    pathname.endsWith('.ico') ||
    pathname.endsWith('.webp') ||
    pathname.endsWith('.pdf')
  ) {
    event.respondWith(cacheFirst(event, CACHE_NAME));
    return;
  }

  // Default: network-first for everything else
  event.respondWith(networkFirst(event, CACHE_NAME));
});
        if (event.request.mode === 'navigate') {
          return caches.match('/index.html');
        }
      });
    })
  );
});
