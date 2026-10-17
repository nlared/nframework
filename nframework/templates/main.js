const applicationServerPublicKey = '{{publicKey}}';

const pushButton = document.querySelector('.js-push-btn');

let isSubscribed = false;
let swRegistration = null;

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

function updateBtn() {
  if (Notification.permission === 'denied') {
   // pushButton.textContent = 'Push Messaging Blocked.';
    pushButton.disabled = true;
    updateSubscriptionOnServer(null);
    return;
  }
//pushButton.prop('checked', isSubscribed);
	if (isSubscribed) {
    	pushButton.textContent='Deshabilitar notificaciones';
	} else {
    	pushButton.textContent='Habilitar notificaciones';
	}
	pushButton.disabled = false;
}

// /getPayload guarda la suscripción por sesión del servidor. Solo se envía cuando cambia la suscripción o la
// sesión (data-sync del botón es un hash del id de sesión, que cambia al iniciar sesión): antes se enviaba en
// cada página.
function updateSubscriptionOnServer(subscription) {
  var key = (pushButton && pushButton.dataset.sync ? pushButton.dataset.sync : '') + '|' + (subscription ? subscription.endpoint : '');
  try {
    if (localStorage.getItem('nfPushSync') === key) {
      return;
    }
  } catch (e) {}
  fetch('/getPayload?endpoint=' + encodeURIComponent(JSON.stringify(subscription)), { credentials: 'same-origin' })
    .then(function (response) {
      if (response.ok) {
        try { localStorage.setItem('nfPushSync', key); } catch (e) {}
      }
    });
}

function subscribeUser() {
  const applicationServerKey = urlB64ToUint8Array(applicationServerPublicKey);
  swRegistration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: applicationServerKey
  })
  .then(function(subscription) {
    console.log('User is subscribed.');
    updateSubscriptionOnServer(subscription);
    isSubscribed = true;
    updateBtn();
  })
  .catch(function(err) {
    console.log('Failed to subscribe the user: ', err);
    updateBtn();
  });
}

function unsubscribeUser() {
  swRegistration.pushManager.getSubscription()
  .then(function(subscription) {
    if (subscription) {
      return subscription.unsubscribe();
    }
  })
  .catch(function(error) {
    console.log('Error unsubscribing', error);
  })
  .then(function() {
    updateSubscriptionOnServer(null);

    console.log('User is unsubscribed.');
    isSubscribed = false;

    updateBtn();
  });
}

function initializeUI() {
if( pushButton!==null){	
  pushButton.addEventListener('click', function() {
    pushButton.disabled = true;
    if (isSubscribed) {
      unsubscribeUser();
    } else {
      subscribeUser();
    }
  });

  // Set the initial subscription value
  swRegistration.pushManager.getSubscription()
  .then(function(subscription) {
    isSubscribed = !(subscription === null);

    updateSubscriptionOnServer(subscription);

    if (isSubscribed) {
      console.log('User IS subscribed.');
    } else {
      console.log('User is NOT subscribed.');
    }

    updateBtn();
  });
}
}

if ('serviceWorker' in navigator && 'PushManager' in window) {
  console.log('Service Worker and Push is supported');

  navigator.serviceWorker.register('/sw.js')
  .then(function(swReg) {
    console.log('Service Worker is registered', swReg);

    swRegistration = swReg;
    initializeUI();
  })
  .catch(function(error) {
    console.error('Service Worker Error', error);
  });
} else {
  //console.warn('Push messaging is not supported');
  //pushButton.textContent = 'Push Not Supported';
}