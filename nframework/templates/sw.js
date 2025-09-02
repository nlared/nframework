var urlsToCache = {{tocache|json_encode()|raw}};

self.addEventListener('install', function(event) {
  event.waitUntil(preLoad());
});

var preLoad = function(){
  console.log('Installing web app');
  return caches.open('offline').then(function(cache) {
    console.log('caching index and important routes');
    return cache.addAll(urlsToCache);
  });
};
self.addEventListener('fetch', function(event) {
	if (event.request.method != 'GET') return;
 	event.respondWith(checkResponse(event.request).catch(function() {
		return returnFromCache(event.request);
	}));
	//event.waitUntil(addToCache(event.request));
});

var checkResponse = function(request){
	return new Promise(function(fulfill, reject) {
	    fetch(request).then(function(response){
	    	if(response.status !== 404) {
	        	fulfill(response);
	    	}else{
	        	reject();
	    	}
	    }, reject);
	});
};

var addToCache = function(request){
	return caches.open('offline').then(function (cache) {
    	return fetch(request).then(function (response) {
    		console.log(response.url + ' was cached');
    		return cache.put(request, response);
    	});
	});
};

var returnFromCache = function(request){
	return caches.open('offline').then(function (cache) {
    	return cache.match(request).then(function (matching) {
    		if(!matching || matching.status == 404) {
    			return cache.match('offline.html');
    		}else{
    			return matching;
    		}
    	});
	});
};


const applicationServerPublicKey = '{{publicKey}}';

/* eslint-enable max-len */

function urlB64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

self.addEventListener('push', function(event) {
  console.log('[Service Worker] Push Received.');
  console.log('[Service Worker] Push had this data: \"\${event.data.text()}\"');

  const title = 'Push Codelab';
  const options = {
    body: event.data.text()
    //icon: 'images/icon.png',
   // badge: 'images/badge.png'
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
  console.log('[Service Worker] Notification click Received.');
  event.notification.close();
  event.waitUntil(
    clients.openWindow('https://developers.google.com/web/')
  );
});

self.addEventListener('pushsubscriptionchange', function(event) {
  console.log('[Service Worker]: \'pushsubscriptionchange\' event fired.');
  const applicationServerKey = urlB64ToUint8Array(applicationServerPublicKey);
  event.waitUntil(
    self.registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: applicationServerKey
    })
    .then(function(newSubscription) {
      // TODO: Send to application server
      fetch('/getPayload?endpoint=' + JSON.stringify(newSubscription));
      console.log('[Service Worker] New subscription: ', newSubscription);
    })
  );
});