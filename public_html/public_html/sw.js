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

self.addEventListener('push', event => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { body: event.data ? event.data.text() : '' };
  }
  
  const title = data.title || 'No Q';
  const options = {
    body: data.body || 'You have a new notification.',
    icon: '/favicon-96x96.png',
    badge: '/favicon-96x96.png',
    vibrate: [200, 100, 200, 100, 200],
    sound: 'default', // Note: some browsers may require an absolute URL to an audio file
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const urlToOpen = new URL(event.notification.data.url, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      let matchingClient = null;
      for (let i = 0; i < windowClients.length; i++) {
        const windowClient = windowClients[i];
        if (windowClient.url === urlToOpen) {
          matchingClient = windowClient;
          break;
        }
      }
      if (matchingClient) {
        return matchingClient.focus();
      } else {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});