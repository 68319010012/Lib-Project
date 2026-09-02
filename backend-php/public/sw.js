// Service worker for the installed app.
//
// Scope of what this may cache is deliberately narrow. Every page in this app
// is generated per-session and several show who is checked in RIGHT NOW, so a
// stale page is worse than no page: an admin acting on yesterday's list would
// force-check-out the wrong student. Only the static shell (fonts, CSS, JS,
// icons) is cached; every navigation and every API call goes to the network.
//
// Bump CACHE_VERSION whenever the shell changes shape. Assets are requested
// with ?v=<filemtime> (see partials/head.php), so a changed file already
// arrives under a new URL — the version bump is for evicting the old entries,
// not for correctness.
//
// The icons in manifest.webmanifest are the exception: it is static JSON, so
// nothing stamps a filemtime into those URLs and they carry a hand-written
// ?v=N instead. Without it the URL never changes, and a replaced icon file is
// invisible to anyone who had already opened the site — this cache serves the
// old bytes, and so does Chrome's own separate manifest-icon cache, which no
// amount of clearing site data or reinstalling the app reaches. Change the
// icons and you must raise that ?v=N in the manifest too; bumping only this
// constant is not enough.
const CACHE_VERSION = 'ntc-shell-v3';

// The offline page has to be in the cache before it is ever needed, so it is
// the one navigable URL precached here.
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
  OFFLINE_URL,
  '/assets/img/logo-badge.svg',
  '/assets/img/pwa/icon-192.png?v=2',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    Promise.resolve()
      .then(() => caches.open(CACHE_VERSION))
      .then((cache) => cache.addAll(PRECACHE))
      // Don't block installation on a single asset 404, or on CacheStorage
      // being unavailable entirely — a worker that never precached still does
      // its main job, which is keeping navigations on the network.
      .catch(() => undefined)
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.resolve()
      .then(() => caches.keys())
      .then((keys) => Promise.all(
        keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
      ))
      .catch(() => undefined)
      .then(() => self.clients.claim())
  );
});

// Only /assets/* is cacheable, and only when fetched with GET. Anything else —
// pages, /me, /checkin, /admin/* — must reach the server.
function isCacheableAsset(url) {
  return url.origin === self.location.origin && url.pathname.startsWith('/assets/');
}

// CacheStorage is not always usable: private-browsing modes in some browsers,
// storage disabled by policy, an exhausted quota, and headless Chrome all make
// caches.open()/match() REJECT rather than come back empty. A rejection handed
// to respondWith() becomes a failed request, so an unguarded cache lookup would
// stop assets loading on exactly those browsers. Every cache call below is
// wrapped so that any failure degrades to "no cache", never to a broken page.
function safeMatch(request) {
  try {
    return caches.match(request).catch(() => undefined);
  } catch (e) {
    return Promise.resolve(undefined);
  }
}

function safePut(request, response) {
  try {
    caches.open(CACHE_VERSION)
      .then((cache) => cache.put(request, response))
      .catch(() => undefined);
  } catch (e) {
    // storage unavailable — serving from the network is still correct
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Navigations: network only, falling back to the offline page. Never serve a
  // cached page — see the note at the top about stale check-in state.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => safeMatch(OFFLINE_URL).then((hit) => (
        hit || new Response(
          '<!doctype html><meta charset="utf-8"><title>ออฟไลน์</title>'
          + '<p style="font-family:sans-serif;text-align:center;padding:2rem">'
          + 'ยังไม่ได้เชื่อมต่ออินเทอร์เน็ต</p>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        )
      )))
    );
    return;
  }

  if (!isCacheableAsset(url)) return;

  // Static assets: cache-first. They are immutable per ?v=<filemtime>, so a
  // hit is always the right answer and the app opens instantly offline-ish.
  event.respondWith(
    safeMatch(request).then((hit) => {
      if (hit) return hit;
      return fetch(request).then((response) => {
        if (response && response.ok && response.type === 'basic') {
          safePut(request, response.clone());
        }
        return response;
      });
    })
  );
});
