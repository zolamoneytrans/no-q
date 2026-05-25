const CACHE_NAME = 'carwash-v1';
const STATIC_CACHE = 'static-v1';
const DYNAMIC_CACHE = 'dynamic-v1';

// Static assets that rarely change – we can cache them on install
const urlsToCache = [
  '/carwash-connect/offline.html',
  '/carwash-connect/3.jpg',
  // Include any other static assets like CSS/JS if you have them (optional)
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // Never cache logout, QR, or payment pages
  if (url.pathname.includes('/logout.php') ||
      url.pathname.includes('/qr.php') ||
      url.pathname.includes('/payfast-')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // For dynamic PHP pages (like index.php, search.php, user-dashboard.php, business-profile.php)
  // Use network-first: try network, fallback to cache if offline, then offline page.
  if (url.pathname.endsWith('.php') && !url.pathname.includes('offline')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone the response and store in dynamic cache for offline use
          const responseClone = response.clone();
          caches.open(DYNAMIC_CACHE).then(cache => {
            cache.put(event.request, responseClone);
          });
          return response;
        })
        .catch(() => {
          // If offline, try to serve from cache
          return caches.match(event.request)
            .then(cached => cached || caches.match('/carwash-connect/offline.html'));
        })
    );
    return;
  }

  // For static assets, use cache-first
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
      .catch(() => caches.match('/carwash-connect'))
  );
});

// Clean up old caches when a new service worker activates
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== STATIC_CACHE && key !== DYNAMIC_CACHE) {
            return caches.delete(key);
          }
        })
      );
    })
  );
});