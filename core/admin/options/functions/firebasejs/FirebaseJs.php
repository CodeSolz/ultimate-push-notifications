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
     * Firebase Init App
     *
     * @param [type] $args
     * @return void
     */
    public static function firebase_init( $args ){
        if( ! \is_object($args) || empty( $args ) ) {
            return;
        }
        return 'var firebaseConfig={apiKey:"'.$args->apiKey.'",authDomain:"'.$args->authDomain.'",databaseURL:"'.$args->databaseURL.'",projectId:"'.$args->projectId.'",storageBucket:"'.$args->storageBucket.'",messagingSenderId:"'.$args->messagingSenderId.'",appId:"'.$args->appId.'",measurementId:"'.$args->measurementId.'"};function createCookie(e,s){document.cookie=e+"="+s+";max-age=31536000"}function accessCookie(e){let s=e+"=",n=document.cookie.split(";");for(let e=0;e<n.length;e++){let o=n[e].trim();if(0==o.indexOf(s))return o.substring(s.length,o.length)}return""}firebase.initializeApp(firebaseConfig);const messaging=firebase.messaging();function sendTokenToServer(e){let s=new FormData;s.append("method","admin\\options\\functions\\AppConfig@cs_update_token"),s.append("gen_token",e),s.append("device_id",accessCookie("cs_wp_ultimate_device_id")),s.append("current_user",`${UPN_Notifier.current_user.user_id}`),fetch(`${UPN_Notifier.ajax_url}`,{method:"POST",body:s}).then(e=>e.json()).then(e=>{console.log(e)}).catch(function(e){console.log(e)})}navigator.serviceWorker.register(`${UPN_Notifier.asset_url}plugins/firebase/js/firebaseMessagingSW.min.js`).then(e=>{messaging.useServiceWorker(e),messaging.getToken().then(e=>{if(e){return accessCookie("cs_wp_ultimate_device_id")?sendTokenToServer(e):(createCookie("cs_wp_ultimate_device_id",`${e}`),sendTokenToServer(e)),e}}).catch(e=>{console.log("An error occurred while retrieving token. ",e)}),messaging.onMessage(e=>{}),messaging.onTokenRefresh(()=>{messaging.getToken().then(e=>(sendTokenToServer(e),e)).catch(e=>{console.log("Unable to retrieve refreshed token ",e)})})});';
    }

    /**
     * Firebase messaging SW
     *
     * @param [type] $args
     * @return void
     */
    public static function firebase_msg_sw( $args ){
        if( ! \is_object($args) || empty( $args ) ) {
            return;
        }
        return 'importScripts("https://www.gstatic.com/firebasejs/7.15.5/firebase-app.js"),importScripts("https://www.gstatic.com/firebasejs/7.15.5/firebase-messaging.js");const firebaseConfig={apiKey:"'.$args->apiKey.'",authDomain:"'.$args->authDomain.'",databaseURL:"'.$args->databaseURL.'",projectId:"'.$args->projectId.'",storageBucket:"'.$args->storageBucket.'",messagingSenderId:"'.$args->messagingSenderId.'",appId:"'.$args->appId.'",measurementId:"'.$args->measurementId.'"};firebase.initializeApp(firebaseConfig);const messaging=firebase.messaging();messaging.setBackgroundMessageHandler(function(e){const a=e.data.title,s={body:e.data.body,icon:e.data.icon,click_action:e.data.click_action,data:e.data.click_action};return self.registration.showNotification(a,s)});';
    }


}