<?php namespace UltimatePushNotifications\admin\options\functions;

/**
 * The Web Push setup panel on the App Config screen.
 *
 * One button. That is the entire onboarding for the default transport, and
 * the contrast with the nine Firebase fields beneath it is deliberate — the
 * Firebase walkthrough is where most installs of this plugin died.
 *
 * @package Functions
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

use UltimatePushNotifications\transport\Ec;
use UltimatePushNotifications\transport\Subscription;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\Vapid;

class VapidPanel {

	/**
	 * Render the panel.
	 *
	 * @return string HTML.
	 */
	public static function render() {
		$has_keys   = Vapid::has_keys();
		$keys       = Vapid::get_keys();
		$supported  = Ec::keygen_supported();
		$subject    = Vapid::get_subject();
		$webpush    = SubscriptionStore::count( Subscription::TRANSPORT_WEBPUSH );
		$legacy     = SubscriptionStore::count( Subscription::TRANSPORT_FCM_LEGACY );
		$nonce      = \wp_create_nonce( SECURE_AUTH_SALT );
		$ajax_url   = \admin_url( 'admin-ajax.php' );

		\ob_start();
		?>
		<div class="upn-vapid-panel" id="upn-vapid-panel">
			<h3 style="margin-top:0"><?php \esc_html_e( 'Web Push (recommended)', 'ultimate-push-notifications' ); ?></h3>

			<?php if ( $has_keys ) : ?>
				<p class="upn-vapid-status upn-vapid-ok">
					<strong>✓ <?php \esc_html_e( 'Web Push is configured.', 'ultimate-push-notifications' ); ?></strong>
					<?php
					if ( ! empty( $keys['created'] ) ) {
						printf(
							/* translators: %s: date */
							\esc_html__( 'Key pair generated %s.', 'ultimate-push-notifications' ),
							\esc_html( \date_i18n( \get_option( 'date_format' ), (int) $keys['created'] ) )
						);
					}
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: 1: web push count, 2: legacy count */
						\esc_html__( '%1$d device(s) registered over Web Push, %2$d over legacy Firebase.', 'ultimate-push-notifications' ),
						(int) $webpush,
						(int) $legacy
					);
					?>
				</p>
				<p>
					<label for="upn-vapid-public"><strong><?php \esc_html_e( 'Public key', 'ultimate-push-notifications' ); ?></strong></label><br/>
					<input type="text" id="upn-vapid-public" class="large-text code" readonly value="<?php echo \esc_attr( $keys['public'] ); ?>" onclick="this.select()" />
					<span class="description"><?php \esc_html_e( 'Browsers use this to subscribe. The private key never leaves the database.', 'ultimate-push-notifications' ); ?></span>
				</p>
			<?php else : ?>
				<p class="upn-vapid-status upn-vapid-warn">
					<strong><?php \esc_html_e( 'Web Push is not set up yet.', 'ultimate-push-notifications' ); ?></strong>
					<?php \esc_html_e( 'Generate a key pair to start registering devices. No Firebase account is required.', 'ultimate-push-notifications' ); ?>
				</p>
			<?php endif; ?>

			<p>
				<label for="upn-vapid-subject"><strong><?php \esc_html_e( 'Contact address', 'ultimate-push-notifications' ); ?></strong></label><br/>
				<input type="text" id="upn-vapid-subject" class="regular-text" value="<?php echo \esc_attr( $subject ); ?>" placeholder="mailto:you@example.com" />
				<span class="description"><?php \esc_html_e( 'Sent to push services so they can reach you about abuse. A mailto: address or your site URL.', 'ultimate-push-notifications' ); ?></span>
			</p>

			<p>
				<?php if ( true === $supported ) : ?>
					<button type="button" class="button button-primary" id="upn-vapid-generate" data-has-keys="<?php echo $has_keys ? '1' : '0'; ?>">
						<?php echo $has_keys ? \esc_html__( 'Regenerate key pair', 'ultimate-push-notifications' ) : \esc_html__( 'Generate key pair', 'ultimate-push-notifications' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button" disabled><?php \esc_html_e( 'Generate key pair', 'ultimate-push-notifications' ); ?></button>
					<span class="description" style="color:#b32d2e"><?php echo \esc_html( $supported ); ?></span>
				<?php endif; ?>
				<button type="button" class="button" id="upn-vapid-toggle-paste"><?php \esc_html_e( 'Use an existing key pair', 'ultimate-push-notifications' ); ?></button>
			</p>

			<?php if ( $has_keys ) : ?>
				<p class="description" style="color:#b32d2e">
					<?php \esc_html_e( 'Regenerating replaces the site\'s identity with every push service. Every currently registered device will stop receiving notifications until it re-subscribes.', 'ultimate-push-notifications' ); ?>
				</p>
			<?php endif; ?>

			<div id="upn-vapid-paste" style="display:none; margin-top:1em; padding:1em; background:#f6f7f7; border-left:4px solid #72aee6">
				<p><?php \esc_html_e( 'Paste a key pair generated elsewhere (for example on a machine where key generation works, or from another site you are migrating from).', 'ultimate-push-notifications' ); ?></p>
				<p>
					<label for="upn-vapid-paste-public"><?php \esc_html_e( 'Public key (base64url, 87 characters)', 'ultimate-push-notifications' ); ?></label><br/>
					<input type="text" id="upn-vapid-paste-public" class="large-text code" />
				</p>
				<p>
					<label for="upn-vapid-paste-private"><?php \esc_html_e( 'Private key (base64url, 43 characters)', 'ultimate-push-notifications' ); ?></label><br/>
					<input type="password" id="upn-vapid-paste-private" class="large-text code" autocomplete="off" />
				</p>
				<p><button type="button" class="button button-primary" id="upn-vapid-save-pasted"><?php \esc_html_e( 'Save key pair', 'ultimate-push-notifications' ); ?></button></p>
			</div>

			<div id="upn-vapid-message" style="margin-top:.5em"></div>
		</div>

		<script>
		(function () {
			var ajaxUrl = <?php echo \wp_json_encode( $ajax_url ); ?>;
			var nonce   = <?php echo \wp_json_encode( $nonce ); ?>;
			var msg     = document.getElementById('upn-vapid-message');

			function post(method, fields) {
				var data = new FormData();
				data.append('action', 'upn_ajax');
				data.append('cs_token', nonce);
				data.append('method', method);
				Object.keys(fields || {}).forEach(function (k) { data.append(k, fields[k]); });
				return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data }).then(function (r) { return r.json(); });
			}

			function show(json) {
				msg.innerHTML = '';
				var p = document.createElement('p');
				p.className = json.status ? 'notice notice-success' : 'notice notice-error';
				p.style.padding = '8px 12px';
				p.textContent = (json.title ? json.title + ' — ' : '') + (json.text || '');
				msg.appendChild(p);
				if (json.status) { window.setTimeout(function () { window.location.reload(); }, 900); }
			}

			var gen = document.getElementById('upn-vapid-generate');
			if (gen) {
				gen.addEventListener('click', function () {
					var force = gen.getAttribute('data-has-keys') === '1';
					if (force && !window.confirm(<?php echo \wp_json_encode( \__( 'Replace the existing key pair? Every registered device will stop receiving notifications until it re-subscribes.', 'ultimate-push-notifications' ) ); ?>)) { return; }
					gen.disabled = true;
					post('admin\\options\\functions\\AppConfig@generate_vapid_keys', {
						force: force ? '1' : '0',
						subject: document.getElementById('upn-vapid-subject').value
					}).then(show).catch(function () { gen.disabled = false; });
				});
			}

			document.getElementById('upn-vapid-toggle-paste').addEventListener('click', function () {
				var box = document.getElementById('upn-vapid-paste');
				box.style.display = box.style.display === 'none' ? 'block' : 'none';
			});

			document.getElementById('upn-vapid-save-pasted').addEventListener('click', function () {
				post('admin\\options\\functions\\AppConfig@save_vapid_keys', {
					public_key:  document.getElementById('upn-vapid-paste-public').value,
					private_key: document.getElementById('upn-vapid-paste-private').value,
					subject:     document.getElementById('upn-vapid-subject').value
				}).then(show);
			});
		})();
		</script>
		<?php
		return \ob_get_clean();
	}

}
