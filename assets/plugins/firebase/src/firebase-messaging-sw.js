/* eslint-disable */
if( 'function' === typeof importScripts) {
  importScripts('https://www.gstatic.com/firebasejs/7.15.5/firebase-app.js');
  importScripts(
    'https://www.gstatic.com/firebasejs/7.15.5/firebase-messaging.js'
  );
}

// const firebaseConfig = {
//   apiKey: "AIzaSyBFgjh1LQRreqtI7zzaTRQ6hAUIRBxfJqk",
//   authDomain: "test-web-push-c2d98.firebaseapp.com",
//   databaseURL: "https://test-web-push-c2d98.firebaseio.com",
//   projectId: "test-web-push-c2d98",
//   storageBucket: "test-web-push-c2d98.appspot.com",
//   messagingSenderId: "460183049925",
//   appId: "1:460183049925:web:7a894f44e90d3e209042ac",
//   measurementId: "G-EJ8N8MSE5R"
// };

const firebaseConfig = {
  apiKey: UPN_Notifier.apiKey,
  authDomain: UPN_Notifier.authDomain,
  databaseURL: UPN_Notifier.databaseURL,
  projectId: UPN_Notifier.projectId,
  storageBucket: UPN_Notifier.storageBucket,
  messagingSenderId: UPN_Notifier.messagingSenderId,
  appId: UPN_Notifier.appId,
  measurementId: UPN_Notifier.measurementId
};

// Initialize Firebase
firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

messaging.setBackgroundMessageHandler(function (payload) {
  console.log(
    '[firebase-messaging-sw.js] Received background message ',
    payload
  );

  // Customize notification here
  const title = payload.data.title;
  const options = {
    body: payload.data.body,
    icon: payload.data.icon,

    click_action: payload.data.click_action,
    data: payload.data.click_action,
    // click_action: 'https://dev.aa-team.com/',
  };

  console.log(options);
  return self.registration.showNotification(title, options);
});
