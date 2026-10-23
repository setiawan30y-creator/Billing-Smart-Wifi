/* ALMARA Billing WiFi — Service Worker. @2026-Alifiandra
 * Hanya meng-cache aset statis yang aman. Halaman HTML, data tenant, invoice,
 * status pembayaran, KTP/foto, dan token TIDAK pernah di-cache.
 * Halaman offline hanya ditampilkan bila perangkat benar-benar offline;
 * bila server sedang mati/error, browser menampilkan pesan aslinya. */
const CACHE = 'almara-static-v2';
const ASSETS = ['/css/app.css', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/offline.html'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  // Hanya hapus cache milik ALMARA versi lama; cache aplikasi lain di origin yang sama tidak disentuh.
  e.waitUntil(caches.keys()
    .then((keys) => Promise.all(keys.filter((k) => k.startsWith('almara-static-') && k !== CACHE).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch((err) => {
      if (self.navigator && self.navigator.onLine === false) {
        return caches.open(CACHE).then((c) => c.match('/offline.html')).then((r) => r || Promise.reject(err));
      }
      return Promise.reject(err);
    }));
    return;
  }

  // Aset statis: network-first (selalu versi terbaru), fallback ke cache ALMARA sendiri.
  if (/^\/(css|icons)\//.test(url.pathname)) {
    e.respondWith(fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(req, copy)); }
      return res;
    }).catch(() => caches.open(CACHE).then((c) => c.match(req, { ignoreSearch: true }))));
  }
});
