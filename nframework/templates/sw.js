// Service worker de nframework: notificaciones push y una página sin conexión.
// Solo intercepta la navegación entre páginas. El resto (CSS, JS, imágenes, AJAX) va directo a la red: antes
// toda petición GET pasaba por aquí y en las herramientas del navegador cada recurso aparecía dos veces.
const CACHE = 'nf-offline-v2';
const OFFLINE_URL = '/__offline';
const OFFLINE_HTML = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
  + '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Sin conexión</title>'
  + '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;'
  + 'min-height:100vh;margin:0;color:#333;text-align:center}button{padding:.6em 1.2em;font-size:1em}</style>'
  + '</head><body><div><h1>Sin conexión</h1><p>Revise su conexión a internet e intente de nuevo.</p>'
  + '<button onclick="location.reload()">Reintentar</button></div></body></html>';

self.addEventListener('install', function (event) {
  // La página sin conexión se genera aquí mismo: no depende de que una URL del sitio o de un CDN responda.
  event.waitUntil(
    caches.open(CACHE)
      .then(function (cache) {
        return cache.put(OFFLINE_URL, new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }));
      })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  // Borra cachés de versiones anteriores (la vieja 'offline') y toma el control de las pestañas abiertas.
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys.filter(function (key) { return key !== CACHE; }).map(function (key) { return caches.delete(key); }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.mode !== 'navigate') {
    return;   // sin respondWith: el navegador hace la petición normal
  }
  event.respondWith(
    fetch(event.request).catch(function () {
      return caches.match(OFFLINE_URL);
    })
  );
});

// ---------------------------------------------------------------- notificaciones push

const applicationServerPublicKey = '{{publicKey}}';

function urlB64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = self.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

// El mensaje puede ser texto o JSON {title, body, url, icon}.
self.addEventListener('push', function (event) {
  let data = {};
  const text = event.data ? event.data.text() : '';
  try {
    data = JSON.parse(text);
  } catch (e) {
    data = { body: text };
  }
  const title = data.title || self.location.hostname;
  const options = { body: data.body || '', data: { url: data.url || '/' } };
  if (data.icon) {
    options.icon = data.icon;
  }
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(self.clients.openWindow(url));
});

self.addEventListener('pushsubscriptionchange', function (event) {
  event.waitUntil(
    self.registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlB64ToUint8Array(applicationServerPublicKey)
    }).then(function (subscription) {
      return fetch('/getPayload?endpoint=' + encodeURIComponent(JSON.stringify(subscription)), { credentials: 'same-origin' });
    })
  );
});
