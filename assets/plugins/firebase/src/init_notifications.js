/* eslint-disable */
var firebaseConfig = {
  apiKey: UPN_Notifier.apiKey,
  authDomain: UPN_Notifier.authDomain,
  databaseURL: UPN_Notifier.databaseURL,
  projectId: UPN_Notifier.projectId,
  storageBucket: UPN_Notifier.storageBucket,
  messagingSenderId: UPN_Notifier.messagingSenderId,
  appId: UPN_Notifier.appId,
  measurementId: UPN_Notifier.measurementId
};

function createCookie(cookieName, cookieValue) {
  let expireTime = 31536000;
  document.cookie = cookieName + '=' + cookieValue + ';max-age=' + expireTime;
  console.log('cookie created');
}

function accessCookie(cookieName) {
  let name = cookieName + '=';
  let allCookieArray = document.cookie.split(';');
  for (let i = 0; i < allCookieArray.length; i++) {
    let temp = allCookieArray[i].trim();
    if (temp.indexOf(name) == 0)
      return temp.substring(name.length, temp.length);
  }
  return '';
}

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

function sendTokenToServer(currentToken) {
    console.log(currentToken);
    var formData = {
      action : 'upn_ajax',
      method : "admin\\options\\functions\\AppConfig@cs_update_token",
      gen_token: currentToken,
      device_id: accessCookie('cs_wp_ultimate_device_id'),
      current_user: UPN_Notifier.current_user,
    };
    $.ajax({
      url: `${UPN_Notifier.ajax_url}`,
      type: 'POST',
      data: formData,
      contentType: false,
      cache: false,
      processData: false
    })
    .done(function( data ) {
      console.log( data );
    })
    .fail(function( errorThrown ) {
      console.log( 'Error: ' + errorThrown.responseText );
    });
}

navigator.serviceWorker
  .register(
    `${UPN_Notifier.asset_url}plugins/firebase/js/firebase.messaging.sw.min.js`
  )
  .then((registration) => {
    messaging.useServiceWorker(registration);

    messaging
      .getToken()
      .then((currentToken) => {
        if (currentToken) {
          console.log('intra aici');
          let uniqDeviceId = accessCookie('cs_wp_ultimate_device_id');
          if (uniqDeviceId) {
            console.log('exista deja token generat');
            console.log(currentToken);
          } else {
            createCookie('cs_wp_ultimate_device_id', `${currentToken}`);
            sendTokenToServer(currentToken);
          }

          // console.log(accessCookie('mihai'));
          // createCookie('mihai', 'token1');
          // sendTokenToServer(currentToken);
          // console.log('avem permisiune');
          // console.log(currentToken);

          return currentToken;
        } else {
          console.log('Request permission to generate one.');
        }
      })
      .catch((err) => {
        console.log('An error occurred while retrieving token. ', err);
        //setTokenSentToServer(false);
      });

    // afisare atunci cand suntem pe pagina si primim notificare
    messaging.onMessage((payload) => {
      console.log('onMessage: ', payload);
      // if (window.Notification && Notification.permission !== 'denied') {
      //   Notification.requestPermission((status) => {
      //     // status is "granted", if accepted by user
      //     var n = new Notification(payload.data.title, {
      //       body: payload.data.body,
      //       icon: payload.data.icon,
      //     });
      //   });
      // }
    });

    // refresh token
    messaging.onTokenRefresh(() => {
      messaging
        .getToken()
        .then((refreshedToken) => {
          console.log('Token refreshed.');
          sendTokenToServer( refreshedToken )
          return refreshedToken;
        })
        .catch((err) => {
          console.log('Unable to retrieve refreshed token ', err);
        });
    });
  });
