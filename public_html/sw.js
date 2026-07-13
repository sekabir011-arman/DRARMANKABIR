/**
 * Dr. Arman Kabir Care — Service Worker
 * Cache name v6 forces fresh cache, fixing React #321 caused by ?v=3.0 query string
 * on the main entry script (ESM treats same file with different query strings as
 * separate modules, creating duplicate React instances).
 * Network-first for JS/CSS ensures fresh bundles.
 */
const CACHE_NAME = 'dr-arman-care-v6';
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/manifest.json',
];

// ── Install: cache app shell ──────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// ── Activate: clean old caches, take control ──────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ── Helpers ───────────────────────────────────────────────────────────────

/** Strip cache-busting query strings from JS/CSS URLs so ESM imports match */
function normalizeUrl(url) {
  if (/\.(js|css|woff2?|ttf|eot|png|jpg|jpeg|gif|svg|ico|webp|pdf)$/i.test(url.pathname)) {
    const cleaned = new URL(url.origin + url.pathname);
    return cleaned;
  }
  return url;
}

function networkFirst(event) {
  const normalized = normalizeUrl(new URL(event.request.url));
  const request = normalized.href !== event.request.url
    ? new Request(normalized, event.request)
    : event.request;

  return fetch(request)
    .then((response) => {
      if (!response || response.status !== 200 || response.type === 'opaque') {
        return response;
      }
      const toCache = response.clone();
      caches.open(CACHE_NAME).then((cache) => {
        cache.put(request, toCache);
      });
      return response;
    })
    .catch(() => caches.match(request));
}

function cacheFirst(event) {
  const normalized = normalizeUrl(new URL(event.request.url));
  const request = normalized.href !== event.request.url
    ? new Request(normalized, event.request)
    : event.request;

  return caches.match(request).then((cached) => {
    if (cached) return cached;
    return fetch(request).then((response) => {
      if (!response || response.status !== 200 || response.type === 'opaque') {
        return response;
      }
      const toCache = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(request, toCache));
      return response;
    });
  });
}

// ── Fetch: strategy per asset type ────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET and chrome-extension
  if (event.request.method !== 'GET') return;
  if (url.protocol === 'chrome-extension:') return;

  // API calls: network-first
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirst(event));
    return;
  }

  // External resources: network-first with cache fallback
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

  const pathname = url.pathname;

  // JavaScript bundles: always network-first (prevents stale React instances)
  if (pathname.endsWith('.js')) {
    event.respondWith(networkFirst(event));
    return;
  }

  // CSS: network-first (refresh on change)
  if (pathname.endsWith('.css')) {
    event.respondWith(networkFirst(event));
    return;
  }

  // HTML: network-first (always get fresh page)
  if (pathname.endsWith('.html') || pathname === '/') {
    event.respondWith(networkFirst(event));
    return;
  }

  // Fonts, images, PDFs: cache-first (rarely change)
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
    event.respondWith(cacheFirst(event));
    return;
  }

  // Default: network-first
  event.respondWith(networkFirst(event));
});
