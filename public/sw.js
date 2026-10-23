/* ALMARA Billing WiFi — Service Worker. @2026-Alifiandra
 * Hanya meng-cache aset statis yang aman. Halaman HTML, data tenant, invoice,
 * status pembayaran, KTP/foto, dan token TIDAK pernah di-cache. */
const CACHE = 'almara-static-v1';
const ASSETS = ['/css/app.css', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/offline.html'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Navigasi: selalu ke jaringan; bila offline tampilkan halaman offline (tanpa data).
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Aset statis aman: cache-first.
  if (/^\/(css|icons)\//.test(url.pathname)) {
    e.respondWith(caches.match(req, { ignoreSearch: true }).then((hit) => hit || fetch(req).then((res) => {
      const copy = res.clone();
      if (res.ok) caches.open(CACHE).then((c) => c.put(req, copy));
      return res;
    })));
  }
});
