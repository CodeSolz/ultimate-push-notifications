<?php namespace UltimatePushNotifications\admin\options;

/**
 * Class: Admin Menu Scripts
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\lib\Util;
use UltimatePushNotifications\admin\options\functions\AppConfig;

if ( ! \class_exists( 'Scripts_Settings' ) ) {

	class Scripts_Settings {

		/**
		 * load admin settings scripts
		 */
		public static function load_admin_settings_scripts( $page_id, $upn_menus ) {
			wp_enqueue_style( 'sweetalert', CS_UPN_PLUGIN_ASSET_URI . 'plugins/sweetalert/dist/sweetalert.css', false );
			wp_enqueue_script( 'sweetalert', CS_UPN_PLUGIN_ASSET_URI . 'plugins/sweetalert/dist/sweetalert.min.js', false );
			
			
			if( $upn_menus[ 'menu_add_my_device' ] == $page_id ){
				$AppConfig = AppConfig::get_config();
				
				if( ! empty( $AppConfig ) ) {
					
					global $current_user;
					wp_get_current_user();

					wp_enqueue_script( 'firebase-app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-app.js', array(), '1.0', true );
					wp_enqueue_script( 'firebase-messaging', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebase-messaging.js',  array(), '1.0', true  );
					wp_enqueue_script( 'init_firebase_app', CS_UPN_PLUGIN_ASSET_URI . 'plugins/firebase/js/firebaseInit.min.js',  array(), '1.0', true  );
					wp_enqueue_script( 'init_upn_app', CS_UPN_PLUGIN_ASSET_URI . 'js/app-upn.js',  array(), '1.0', false  );
	
					//localize scripts
					wp_localize_script( 'init_upn_app', 'UPN_Notifier', array(
						'asset_url' => CS_UPN_PLUGIN_ASSET_URI,
						'ajax_url' => esc_url( admin_url('admin-ajax.php?action=upn_ajax&cs_token=' . wp_create_nonce( SECURE_AUTH_SALT ) ) ),
						'current_user' => array('user_id' => $current_user->ID, 'user_name' => $current_user->user_login ),
					) + (array) $AppConfig );

				}

			}

			wp_enqueue_style( 'wapg', CS_UPN_PLUGIN_ASSET_URI . 'css/upn-admin-style.min.css', false );

			return;
		}

		/**
		 * admin footer script processor
		 *
		 * @global array $rtafr_menu
		 * @param string $page_id
		 */
		public static function load_admin_footer_script( $page_id, $rtafr_menu ) {

			Util::markup_tag( __( 'admin footer script start', 'ultimate-push-notifications' ) );

			// load form submit script on footer
			if ( $page_id == $rtafr_menu['menu_app_config'] ||
				$page_id == $rtafr_menu['menu_set_notifications']
			) {
				self::form_submitter();
			}

			
			// send test notifications
			if ( $page_id == $rtafr_menu['menu_add_my_device'] ) {
				self::send_test_notifications();
			}


			Util::markup_tag( __( 'admin footer script end', 'ultimate-push-notifications' ) );

			return;
		}

		/**
		 * load admin scripts to footer
		 */
		public static function form_submitter() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function( $ ){
					$("form").submit(function(e){
						e.preventDefault();
						var $this = $(this);
						var formData = new FormData( $this[0] );
						formData.append( "action", "upn_ajax" );
						formData.append( "method", $this.find('#cs_field_method').val() );
						swal({ title: $this.find('#cs_field_swal_title').val(), text: '<?php _e( "Please wait a while...", 'ultimate-push-notifications' ); ?>', timer: 200000, imageUrl: '<?php echo CS_UPN_PLUGIN_ASSET_URI . 'img/loading-timer.gif'; ?>', showConfirmButton: false, html :true });
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: formData,
							contentType: false,
							cache: false,
							processData: false
						})
						.done(function( data ) {
							console.log( data );
							if( true === data.status ){
								swal( { title: data.title, text: data.text, type : "success", html: true, timer: 5000 });
								if( typeof data.redirect_url !== 'undefined' ){
									window.location.href = data.redirect_url;
								}
							}else if( false === data.status ){
								swal({ title: data.title, text: data.text, type : "error", html: true, timer: 5000 });
							}else{
								swal( { title: 'OOPS!', text: '<?php _e( "Something went wrong! Please try again by refreshing the page.", 'ultimate-push-notifications' ); ?>', type : "error", html: true, timer: 5000 });
							}
						})
						.fail(function( errorThrown ) {
							console.log( 'Error: ' + errorThrown.responseText );
							swal( '<?php _e( "Response Error", 'ultimate-push-notifications' ); ?>', errorThrown.responseText + '('+errorThrown.statusText +') ' , "error" );
						});
						return false;
					});
					
				});
			</script>
			<?php
		}

		/**
		 * Send test notifications
		 *
		 * @return void
		 */
		private static function send_test_notifications(){
			?>
			<script type="text/javascript">
				jQuery(document).ready(function( $ ){
					$("body").on( 'click', '.send-test-notifications', function(e){
						e.preventDefault();
						var getToken = $(this).data('token');
						var formData = new FormData();
						formData.append( "action", "upn_ajax" );
						formData.append( "method", "admin\\functions\\SendNotifications@send_test_notifications" );
						formData.append( "device_token", getToken );
						swal({ title: '<?php _e( "Sending", 'ultimate-push-notifications' ); ?>', text: '<?php _e( "Please wait a while...", 'ultimate-push-notifications' ); ?>', timer: 200000, imageUrl: '<?php echo CS_UPN_PLUGIN_ASSET_URI . 'img/loading-timer.gif'; ?>', showConfirmButton: false, html :true });
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: formData,
							contentType: false,
							cache: false,
							processData: false
						})
						.done(function( data ) {
							console.log( data );
							if( true === data.status ){
								swal( { title: data.title, text: data.text, type : "success", html: true, timer: 5000 });
								if( typeof data.redirect_url !== 'undefined' ){
									window.location.href = data.redirect_url;
								}
							}else if( false === data.status ){
								swal({ title: data.title, text: data.text, type : "error", html: true, timer: 5000 });
							}else{
								swal( { title: 'OOPS!', text: '<?php _e( "Something went wrong! Please try again by refreshing the page.", 'ultimate-push-notifications' ); ?>', type : "error", html: true, timer: 5000 });
							}
						})
						.fail(function( errorThrown ) {
							console.log( 'Error: ' + errorThrown.responseText );
							swal( '<?php _e( "Response Error", 'ultimate-push-notifications' ); ?>', errorThrown.responseText + '('+errorThrown.statusText +') ' , "error" );
						});
						return false;
					});
					
				});
			</script>
			<?php
		}



	}

}
