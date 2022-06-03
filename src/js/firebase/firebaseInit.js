var firebaseConfig = {
    apiKey: "AIzaSyBFgjh1LQRreqtI7zzaTRQ6hAUIRBxfJqk",
    authDomain: "test-web-push-c2d98.firebaseapp.com",
    databaseURL: "https://test-web-push-c2d98.firebaseio.com",
    projectId: "test-web-push-c2d98",
    storageBucket: "test-web-push-c2d98.appspot.com",
    messagingSenderId: "460183049925",
    appId: "1:460183049925:web:7a894f44e90d3e209042ac",
    measurementId: "G-EJ8N8MSE5R"
};

function createCookie(e, s) {
    document.cookie = e + "=" + s + ";max-age=31536000"
}

function accessCookie(e) {
    let s = e + "=",
        n = document.cookie.split(";");
    for (let e = 0; e < n.length; e++) {
        let o = n[e].trim();
        if (0 == o.indexOf(s)) return o.substring(s.length, o.length)
    }
    return ""
}
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

function sendTokenToServer(e) {
    let s = new FormData;
    s.append("method", "admin\\options\\functions\\AppConfig@cs_update_token"), s.append("gen_token", e), s.append("device_id", accessCookie("cs_wp_ultimate_device_id")), s.append("current_user", `${UPN_Notifier.current_user.user_id}`), fetch(`${UPN_Notifier.ajax_url}`, {
        method: "POST",
        body: s
    }).then(e => e.json()).then(e => {
        console.log(e)
    }).catch(function(e) {
        console.log(e)
    })
}
navigator.serviceWorker.register(`${UPN_Notifier.asset_url}plugins/firebase/js/firebaseMessagingSW.min.js`).then(e => {
    messaging.useServiceWorker(e), messaging.getToken().then(e => {
        if (e) {
            return accessCookie("cs_wp_ultimate_device_id") ? sendTokenToServer(e) : (createCookie("cs_wp_ultimate_device_id", `${e}`), sendTokenToServer(e)), e
        }
    }).catch(e => {
        console.log("An error occurred while retrieving token. ", e)
    }), 
    messaging.onMessage(payload => {
        console.log( 'onMessage: ', payload);
        if (window.Notification && Notification.permission !== 'denied') {
            Notification.requestPermission((status) => {
                // status is "granted", if accepted by user
                var n = new Notification(payload.notification.title, {
                    body: payload.notification.body,
                    icon: payload.notification.icon,
                });
            });
        }
    }), 
    messaging.onTokenRefresh(() => {
        messaging.getToken().then(e => (sendTokenToServer(e), e)).catch(e => {
            console.log("Unable to retrieve refreshed token ", e)
        })
    })
});