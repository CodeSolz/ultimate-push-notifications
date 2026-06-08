<?php namespace UltimatePushNotifications\admin\options\functions\firebasejs;

/**
 * Firebase JS
 *
 * @package Functions
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class FirebaseJs {

	/**
	 * Firebase SDK version used for CDN imports
	 */
	const FIREBASE_SDK_VERSION = '11.0.0';

	/**
	 * Firebase Init App (front-end)
	 * Generates firebaseInit.min.js using Firebase 11.x compat SDK.
	 *
	 * @param object $args Firebase config object
	 * @return string|void
	 */
	public static function firebase_init( $args ) {
		if ( ! \is_object( $args ) || empty( $args ) ) {
			return;
		}

		$config = 'var firebaseConfig={apiKey:"' . $args->apiKey . '",authDomain:"' . $args->authDomain . '",databaseURL:"' . $args->databaseURL . '",projectId:"' . $args->projectId . '",storageBucket:"' . $args->storageBucket . '",messagingSenderId:"' . $args->messagingSenderId . '",appId:"' . $args->appId . '",measurementId:"' . $args->measurementId . '"};';

		// Minified front-end init using Firebase 11 compat API:
		// - Removed deprecated useServiceWorker(), onTokenRefresh()
		// - Added vapidKey from UPN_Notifier (localized via wp_localize_script)
		// - Added rich notification (image) support in onMessage
		// - Added serviceWorkerRegistration option for getToken()
		$js = 'function createCookie(n,v){document.cookie=n+"="+v+";max-age=31536000"}function accessCookie(n){let q=n+"=",c=document.cookie.split(";");for(let i=0;i<c.length;i++){let s=c[i].trim();if(s.indexOf(q)===0)return s.substring(q.length,s.length)}return""}function sendTokenToServer(t){let f=new FormData;f.append("method","admin\\\\options\\\\functions\\\\AppConfig@cs_update_token"),f.append("gen_token",t),f.append("device_id",accessCookie("cs_wp_ultimate_device_id")),f.append("current_user",String(UPN_Notifier.current_user.user_id)),fetch(UPN_Notifier.ajax_url,{method:"POST",body:f}).then(function(r){return r.json()}).then(function(d){console.log(d)}).catch(function(e){console.error(e)})}firebase.initializeApp(firebaseConfig);if(typeof Notification!=="undefined"&&Notification.permission!=="denied"){navigator.serviceWorker.register(UPN_Notifier.asset_url+"plugins/firebase/js/firebaseMessagingSW.min.js").then(function(s){var m=firebase.messaging(),o={serviceWorkerRegistration:s};if(UPN_Notifier.vapidKey&&UPN_Notifier.vapidKey!==""){o.vapidKey=UPN_Notifier.vapidKey}m.getToken(o).then(function(t){if(t){if(!accessCookie("cs_wp_ultimate_device_id")){createCookie("cs_wp_ultimate_device_id",t)}sendTokenToServer(t)}}).catch(function(e){console.error("An error occurred while retrieving token.",e)});m.onMessage(function(p){var title=(p.data&&p.data.title)?p.data.title:(p.notification?p.notification.title:"");var opts={body:(p.data&&p.data.body)?p.data.body:"",icon:(p.data&&p.data.icon)?p.data.icon:""};if(p.data&&p.data.image){opts.image=p.data.image}if(title){new Notification(title,opts)}})}).catch(function(e){console.error("Service worker registration failed:",e)})}';

		return $config . $js;
	}

	/**
	 * Firebase Messaging Service Worker
	 * Generates firebaseMessagingSW.min.js using Firebase 11.x compat SDK.
	 * - Uses onBackgroundMessage() (replaces removed setBackgroundMessageHandler)
	 * - Supports rich notifications (image field)
	 * - Handles notificationclick for deep-link navigation
	 *
	 * @param object $args Firebase config object
	 * @return string|void
	 */
	public static function firebase_msg_sw( $args ) {
		if ( ! \is_object( $args ) || empty( $args ) ) {
			return;
		}

		$sdk = self::FIREBASE_SDK_VERSION;

		return 'importScripts("https://www.gstatic.com/firebasejs/' . $sdk . '/firebase-app-compat.js");importScripts("https://www.gstatic.com/firebasejs/' . $sdk . '/firebase-messaging-compat.js");const firebaseConfig={apiKey:"' . $args->apiKey . '",authDomain:"' . $args->authDomain . '",databaseURL:"' . $args->databaseURL . '",projectId:"' . $args->projectId . '",storageBucket:"' . $args->storageBucket . '",messagingSenderId:"' . $args->messagingSenderId . '",appId:"' . $args->appId . '",measurementId:"' . $args->measurementId . '"};firebase.initializeApp(firebaseConfig);const messaging=firebase.messaging();messaging.onBackgroundMessage(function(p){const title=(p.data&&p.data.title)?p.data.title:(p.notification?p.notification.title:"Notification"),opts={body:(p.data&&p.data.body)?p.data.body:"",icon:(p.data&&p.data.icon)?p.data.icon:"",image:(p.data&&p.data.image)?p.data.image:"",data:{click_action:(p.data&&p.data.click_action)?p.data.click_action:"/"}};return self.registration.showNotification(title,opts)});self.addEventListener("notificationclick",function(e){e.notification.close();var u=(e.notification.data&&e.notification.data.click_action)?e.notification.data.click_action:"/";e.waitUntil(clients.openWindow(u))});';
	}


}
