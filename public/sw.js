const CACHE_NAME = "brighton-pwa-v3";
const APP_SHELL = [
  "./mobile/index.html",
  "./mobile/timeline.html",
  "./mobile/enemies.html",
  "./mobile/character-builder.html",
  "./mobile/rules.html",
  "./mobile/wargame.html",
  "./runtime-config.js",
  "./manifest.webmanifest",
  "./images/Icon.png",
  "./images/Banner.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== "GET") return;

  if (url.pathname.includes("/api/")) {
    event.respondWith(
      fetch(req).catch(() => caches.match(req))
    );
    return;
  }

  const isNav = req.mode === "navigate";
  event.respondWith(
    fetch(req)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
        return res;
      })
      .catch(async () => {
        const cached = await caches.match(req);
        if (cached) return cached;
        if (isNav) {
          return caches.match("./mobile/index.html");
        }
        return Response.error();
      })
  );
});
