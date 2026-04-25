// Media-Hoard Service Worker — native Cache API (no external dependencies)

const CACHE_PAGES    = 'mh-pages-v1';
const CACHE_IMAGES   = 'mh-images-v1';
const CACHE_DATA     = 'mh-data-v1';
const CACHE_ASSETS   = 'mh-assets-v1';
const CACHE_EXTERNAL = 'mh-external-v1';

const ALL_CACHES = [CACHE_PAGES, CACHE_IMAGES, CACHE_DATA, CACHE_ASSETS, CACHE_EXTERNAL];

// Offline fallback page — precached on install so it's always available
const OFFLINE_URL = '/Media-Hoard/offline.html';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function isImageFile(url) {
  return url.pathname.includes('/img/imageFiles/') &&
    /\.(jpe?g|png|gif|webp|avif|svg)$/i.test(url.pathname);
}

function isPageRequest(url) {
  return url.pathname.endsWith('/img/') ||
    url.pathname.endsWith('/img/index.php') ||
    url.pathname.includes('/img/imageFiles/_img.php');
}

function isDataRequest(url) {
  return (url.pathname.includes('/img/') && url.pathname.endsWith('.json')) ||
    url.pathname.includes('_imageCategories.php');
}

function isAssetRequest(url) {
  return url.pathname.includes('/css/') ||
    url.pathname.endsWith('.min.css') ||
    (url.pathname.endsWith('.js') && !url.pathname.includes('sw.js'));
}

function isExternalRequest(url) {
  return url.origin !== self.location.origin;
}

// NetworkFirst with timeout — returns cached fallback if network is slow/offline
async function networkFirst(request, cacheName, timeoutMs = 5000) {
  const cache = await caches.open(cacheName);
  try {
    const networkPromise = fetch(request);
    const timeoutPromise = new Promise((_, reject) =>
      setTimeout(() => reject(new Error('Network timeout')), timeoutMs)
    );
    const response = await Promise.race([networkPromise, timeoutPromise]);
    if (response.ok || response.status === 0) {
      cache.put(request, response.clone());
    }
    return response;
  } catch {
    // Try exact URL first, then ignore query string as fallback
    const cached =
      (await cache.match(request)) ||
      (await cache.match(request, { ignoreSearch: true }));
    if (cached) return cached;
    // For navigation requests serve the offline page
    if (request.mode === 'navigate') {
      const offlineCache = await caches.open(CACHE_PAGES);
      const offlinePage = await offlineCache.match(OFFLINE_URL);
      if (offlinePage) return offlinePage;
    }
    throw new Error('No network and no cache for: ' + request.url);
  }
}

// CacheFirst — serve from cache, fall back to network and store result
async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok || response.status === 0) {
    cache.put(request, response.clone());
  }
  return response;
}

// StaleWhileRevalidate — return cache immediately, refresh cache in background
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkFetch = fetch(request).then(response => {
    if (response.ok || response.status === 0) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => {});
  return cached || networkFetch;
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

self.addEventListener('install', event => {
  // Precache the offline fallback page so it's always available
  event.waitUntil(
    caches.open(CACHE_PAGES)
      .then(cache => cache.add(OFFLINE_URL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => !ALL_CACHES.includes(k)).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// ─── Fetch routing ────────────────────────────────────────────────────────────

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  if (isImageFile(url)) {
    event.respondWith(cacheFirst(request, CACHE_IMAGES));
    return;
  }

  // Treat all same-origin navigation requests (HTML pages) as NetworkFirst
  if (request.mode === 'navigate' || isPageRequest(url)) {
    event.respondWith(networkFirst(request, CACHE_PAGES));
    return;
  }

  if (isDataRequest(url)) {
    event.respondWith(networkFirst(request, CACHE_DATA));
    return;
  }

  if (isAssetRequest(url)) {
    event.respondWith(staleWhileRevalidate(request, CACHE_ASSETS));
    return;
  }

  if (isExternalRequest(url)) {
    event.respondWith(cacheFirst(request, CACHE_EXTERNAL));
    return;
  }
});

console.log('Media-Hoard service worker active (native Cache API).');
