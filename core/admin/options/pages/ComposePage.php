<?php namespace UltimatePushNotifications\admin\options\pages;

use UltimatePushNotifications\admin\functions\Compose;
use UltimatePushNotifications\messaging\Composer;
use UltimatePushNotifications\transport\SubscriptionStore;
use UltimatePushNotifications\transport\Vapid;
use UltimatePushNotifications\pro\Locked;

/**
 * The Compose screen.
 *
 * Write once, see who will get it, send it to yourself first, then send.
 *
 * @package Options
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

class ComposePage {

	public static function render() {
		$roles     = \wp_roles()->get_names();
		$total     = SubscriptionStore::count( 'webpush' );
		$has_keys  = Vapid::has_keys();
		$nonce     = \wp_create_nonce( SECURE_AUTH_SALT );
		$tags      = Compose::tag_reference();
		?>
		<?php \UltimatePushNotifications\admin\builders\Layout::open( \__( 'Compose a Notification', 'ultimate-push-notifications' ), \__( 'Write a notification, choose who receives it, try it on your own device, and send.', 'ultimate-push-notifications' ), '', 'upn-compose' ); ?>

			<?php if ( ! $has_keys ) : ?>
				<div class="notice notice-error"><p>
					<?php
					printf(
						/* translators: %s: link */
						\esc_html__( 'Web Push is not set up yet. Generate a key pair on %s first.', 'ultimate-push-notifications' ),
						'<a href="' . \esc_url( \admin_url( 'admin.php?page=cs-upn-app-configuration' ) ) . '">' . \esc_html__( 'App Config', 'ultimate-push-notifications' ) . '</a>'
					);
					?>
				</p></div>
			<?php elseif ( 0 === $total ) : ?>
				<div class="notice notice-warning"><p>
					<?php \esc_html_e( 'No devices are registered over Web Push yet. Nothing will be sent until someone subscribes.', 'ultimate-push-notifications' ); ?>
				</p></div>
			<?php endif; ?>

			<div class="upn-compose-grid">
				<div>
					<div class="upn-form">
						<div class="form-group">
						<div class="label"><label for="upn-c-title"><?php \esc_html_e( 'Title', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-c-title" class="large-text" maxlength="<?php echo (int) Composer::MAX_TITLE; ?>" placeholder="<?php \esc_attr_e( 'New post: {post_title}', 'ultimate-push-notifications' ); ?>" />
								<p class="description"><span id="upn-c-title-count">0</span>/<?php echo (int) Composer::MAX_TITLE; ?> · <?php \esc_html_e( 'Most platforms show about 40 characters before truncating.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
						<div class="form-group">
						<div class="label"><label for="upn-c-body"><?php \esc_html_e( 'Message', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><textarea id="upn-c-body" class="large-text" rows="3" maxlength="<?php echo (int) Composer::MAX_BODY; ?>"></textarea>
								<p class="description"><span id="upn-c-body-count">0</span>/<?php echo (int) Composer::MAX_BODY; ?> · <?php \esc_html_e( 'About 120 characters is the safe visible length on mobile.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
						<div class="form-group">
						<div class="label"><label for="upn-c-url"><?php \esc_html_e( 'Open this URL', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="url" id="upn-c-url" class="large-text" placeholder="<?php echo \esc_attr( \home_url( '/' ) ); ?>" /></div>
					</div>
						<div class="form-group">
						<div class="label"><label for="upn-c-icon"><?php \esc_html_e( 'Icon URL', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="url" id="upn-c-icon" class="large-text" placeholder="https://" /><p class="description"><?php \esc_html_e( 'Square, at least 192×192. Defaults to nothing.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
						<div class="form-group">
						<div class="label"><label for="upn-c-image"><?php \esc_html_e( 'Image URL', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="url" id="upn-c-image" class="large-text" placeholder="https://" /><p class="description"><?php \esc_html_e( 'A large image shown inside the notification on Chrome and Android. Optional.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
						<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Options', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><?php \esc_html_e( 'Urgency', 'ultimate-push-notifications' ); ?>
									<select id="upn-c-urgency">
										<option value="normal"><?php \esc_html_e( 'Normal', 'ultimate-push-notifications' ); ?></option>
										<option value="high"><?php \esc_html_e( 'High — wake the device', 'ultimate-push-notifications' ); ?></option>
										<option value="low"><?php \esc_html_e( 'Low — can wait for a good moment', 'ultimate-push-notifications' ); ?></option>
									</select>
								</label>
								&nbsp;&nbsp;
								<label><?php \esc_html_e( 'Expires after', 'ultimate-push-notifications' ); ?>
									<select id="upn-c-ttl">
										<option value="3600"><?php \esc_html_e( '1 hour', 'ultimate-push-notifications' ); ?></option>
										<option value="86400" selected><?php \esc_html_e( '1 day', 'ultimate-push-notifications' ); ?></option>
										<option value="604800"><?php \esc_html_e( '1 week', 'ultimate-push-notifications' ); ?></option>
									</select>
								</label>
								<p class="description"><?php \esc_html_e( 'A notification that cannot be delivered before it expires is dropped rather than shown late.', 'ultimate-push-notifications' ); ?></p>
								<label style="display:block;margin-top:.5em"><?php \esc_html_e( 'Replace tag', 'ultimate-push-notifications' ); ?> <input type="text" id="upn-c-tag" class="regular-text" placeholder="<?php \esc_attr_e( 'e.g. daily-digest', 'ultimate-push-notifications' ); ?>" /></label>
								<p class="description"><?php \esc_html_e( 'Notifications with the same tag replace each other instead of stacking up.', 'ultimate-push-notifications' ); ?></p></div>
					</div>
					</div>

					<?php if ( ! Locked::unlocked( 'compose.actions' ) ) : ?>
						<p class="description"><?php echo Locked::label( \__( 'Action buttons', 'ultimate-push-notifications' ), 'compose.actions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'up to two buttons on the notification, each with its own link ("Track order", "Reply").', 'ultimate-push-notifications' ); ?> <?php echo Locked::label( \__( 'Templates', 'ultimate-push-notifications' ), 'templates' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'save this form and load it next time.', 'ultimate-push-notifications' ); ?> <?php echo Locked::label( \__( 'AI drafts', 'ultimate-push-notifications' ), 'ai.copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'three title/message variants from a brief or a post, with your own OpenAI, Anthropic, Gemini or Ollama key.', 'ultimate-push-notifications' ); ?> <?php echo Locked::label( \__( 'Quiet hours and caps', 'ultimate-push-notifications' ), 'cadence' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'hold sends overnight in each subscriber\'s own time zone, and never send anyone more than N a day.', 'ultimate-push-notifications' ); ?> <?php echo Locked::label( \__( 'A/B test', 'ultimate-push-notifications' ), 'ab' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'try two versions on a slice each, then send the one with the better click-through to everyone else.', 'ultimate-push-notifications' ); ?> <?php echo Locked::label( \__( 'Best time', 'ultimate-push-notifications' ), 'sto' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'deliver to each subscriber at the hour they usually click, learned from their own history.', 'ultimate-push-notifications' ); ?></p>
					<?php endif; ?>
					<?php
					/**
					 * After the message fields, before the audience: Pro adds action buttons and templates.
					 */
					\do_action( 'upn_compose_after_fields' );
					?>

					<div class="section-title"><?php \esc_html_e( 'Audience', 'ultimate-push-notifications' ); ?></div>
					<div class="upn-form">
						<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Send to', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><label><input type="radio" name="upn-c-who" value="any" checked /> <?php \esc_html_e( 'Everyone', 'ultimate-push-notifications' ); ?></label><br/>
								<label><input type="radio" name="upn-c-who" value="anonymous" /> <?php \esc_html_e( 'Visitors who are not logged in', 'ultimate-push-notifications' ); ?></label><br/>
								<label><input type="radio" name="upn-c-who" value="logged_in" /> <?php \esc_html_e( 'Logged-in users', 'ultimate-push-notifications' ); ?></label><br/>
								<label><input type="radio" name="upn-c-who" value="roles" /> <?php \esc_html_e( 'Specific roles:', 'ultimate-push-notifications' ); ?></label>
								<div id="upn-c-roles" style="margin:.4em 0 0 1.6em;display:none">
									<?php foreach ( $roles as $slug => $label ) : ?>
										<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="upn-c-role" value="<?php echo \esc_attr( $slug ); ?>" /> <?php echo \esc_html( $label ); ?></label>
									<?php endforeach; ?>
								</div></div>
					</div>
						<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Narrow by device', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><select id="upn-c-device">
									<option value=""><?php \esc_html_e( 'Any device', 'ultimate-push-notifications' ); ?></option>
									<option value="desktop"><?php \esc_html_e( 'Desktop', 'ultimate-push-notifications' ); ?></option>
									<option value="mobile"><?php \esc_html_e( 'Mobile', 'ultimate-push-notifications' ); ?></option>
									<option value="tablet"><?php \esc_html_e( 'Tablet', 'ultimate-push-notifications' ); ?></option>
								</select>
								<select id="upn-c-seen">
									<option value=""><?php \esc_html_e( 'Seen any time', 'ultimate-push-notifications' ); ?></option>
									<option value="7"><?php \esc_html_e( 'Active in the last 7 days', 'ultimate-push-notifications' ); ?></option>
									<option value="30"><?php \esc_html_e( 'Active in the last 30 days', 'ultimate-push-notifications' ); ?></option>
									<option value="90"><?php \esc_html_e( 'Active in the last 90 days', 'ultimate-push-notifications' ); ?></option>
								</select></div>
					</div>
					</div>

					<?php if ( ! Locked::unlocked( 'segments' ) ) : ?>
						<?php
						$anon = SubscriptionStore::count_audience( array( 'who' => 'anonymous', 'transport' => 'webpush' ) );
						echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
							'title'   => \__( 'Segments', 'ultimate-push-notifications' ),
							'measure' => \sprintf(
								/* translators: 1: total, 2: visitors, 3: logged-in */
								\__( '%1$s subscribers on this site — %2$s visitors, %3$s logged in — reachable all at once, by role, or by device.', 'ultimate-push-notifications' ),
								\number_format_i18n( $total ),
								\number_format_i18n( $anon ),
								\number_format_i18n( \max( 0, $total - $anon ) )
							),
							'body'    => \__( 'Targeted sends get about twice the click rate of broadcasts. Segments are saved filters over everything the subscriber list already shows — plus what only your database knows.', 'ultimate-push-notifications' ),
							'points'  => array(
								\__( 'Locale, browser, OS, last seen, clicked recently', 'ultimate-push-notifications' ),
								\__( 'WooCommerce: bought a category, spent over an amount, ordered in the last 90 days', 'ultimate-push-notifications' ),
								\__( 'Membership level, BuddyPress group, subscribed from a page', 'ultimate-push-notifications' ),
								\__( 'Schedule for later, or every week — in each subscriber\'s own time zone', 'ultimate-push-notifications' ),
							),
						) );
						if ( ! Locked::unlocked( 'ai.segments' ) ) {
							echo '<p class="description">' . Locked::label( \__( 'Segment discovery', 'ultimate-push-notifications' ), 'ai.segments' ) . ' — ' . \esc_html__( 'with your own AI key, the model sees counts about your subscribers (never a person) and proposes cohorts as rules; you save the ones you want.', 'ultimate-push-notifications' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
						}
						?>
					<?php else : ?>
						<?php
						/**
						 * Pro renders its segment picker and scheduler here.
						 */
						\do_action( 'upn_compose_after_audience' );
						?>
					<?php endif; ?>

					<p style="font-size:1.1em;margin:1em 0">
						<strong><?php \esc_html_e( 'Recipients:', 'ultimate-push-notifications' ); ?></strong>
						<span id="upn-c-count">…</span>
					</p>
					<?php if ( ! Locked::unlocked( 'risk' ) ) : ?>
						<p class="description"><?php echo Locked::label( \__( 'Opt-out risk', 'ultimate-push-notifications' ), 'risk' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'before you send: how tired or dormant this audience is, whether subscribers are already leaving faster than usual, and about how many this send will cost — from this site\'s own numbers, no model.', 'ultimate-push-notifications' ); ?></p>
					<?php endif; ?>

					<p>
						<button type="button" class="button" id="upn-c-preview"><?php \esc_html_e( 'Send to my device first', 'ultimate-push-notifications' ); ?></button>
						<button type="button" class="button button-primary" id="upn-c-send"><?php \esc_html_e( 'Send now', 'ultimate-push-notifications' ); ?></button>
					</p>
					<div id="upn-c-msg"></div>
				</div>

				<div>
					<h3 style="margin-top:0"><?php \esc_html_e( 'Merge tags', 'ultimate-push-notifications' ); ?></h3>
					<p class="description"><?php \esc_html_e( 'Click to insert into the last field you edited.', 'ultimate-push-notifications' ); ?></p>
					<?php foreach ( $tags as $group => $items ) : ?>
						<p style="margin:.6em 0 .2em"><strong><?php echo \esc_html( $group ); ?></strong></p>
						<?php foreach ( $items as $tag => $label ) : ?>
							<button type="button" class="button button-small upn-c-tagbtn" data-tag="{<?php echo \esc_attr( $tag ); ?>}" title="<?php echo \esc_attr( $label ); ?>" style="margin:0 .3em .3em 0"><code>{<?php echo \esc_html( $tag ); ?>}</code></button>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<p class="description" style="margin-top:1em"><?php \esc_html_e( 'Post and user tags are empty in a broadcast; they fill in when an automation sends about a specific post or user.', 'ultimate-push-notifications' ); ?></p>
				</div>
			</div>
		<?php \UltimatePushNotifications\admin\builders\Layout::close(); ?>

		<script>
		(function () {
			var ajaxUrl = <?php echo \wp_json_encode( \admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo \wp_json_encode( $nonce ); ?>;
			var $ = function (id) { return document.getElementById(id); };
			var lastField = $('upn-c-title');
			var msg = $('upn-c-msg');

			function post(method, fields) {
				var data = new FormData();
				data.append('action', 'upn_ajax'); data.append('cs_token', nonce); data.append('method', method);
				Object.keys(fields || {}).forEach(function (k) {
					var v = fields[k];
					if (Array.isArray(v)) { v.forEach(function (x) { data.append(k + '[]', x); }); }
					else if (v !== null && typeof v === 'object') { Object.keys(v).forEach(function (kk) { var vv = v[kk]; if (Array.isArray(vv)) { vv.forEach(function (x) { data.append(k + '[' + kk + '][]', x); }); } else { data.append(k + '[' + kk + ']', vv); } }); }
					else { data.append(k, v); }
				});
				return fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data }).then(function (r) { return r.json(); });
			}

			function show(json) {
				msg.innerHTML = '';
				var p = document.createElement('p');
				p.className = 'notice ' + (json.status ? 'notice-success' : 'notice-error');
				p.style.padding = '8px 12px';
				p.textContent = (json.title ? json.title + ' — ' : '') + (json.text || '');
				msg.appendChild(p);
			}

			function fields() {
				return {
					title: $('upn-c-title').value, body: $('upn-c-body').value, click_action: $('upn-c-url').value,
					icon: $('upn-c-icon').value, image: $('upn-c-image').value, tag: $('upn-c-tag').value,
					urgency: $('upn-c-urgency').value, ttl: $('upn-c-ttl').value
				};
			}

			function audience() {
				var who = document.querySelector('input[name="upn-c-who"]:checked').value;
				var a = { who: who, device: $('upn-c-device').value, seen_within_days: $('upn-c-seen').value };
				if (who === 'roles') { a.roles = Array.prototype.map.call(document.querySelectorAll('input[name="upn-c-role"]:checked'), function (c) { return c.value; }); }
				var seg = $('upn-c-segment'); if (seg && seg.value) { a.segment = seg.value; }
				return a;
			}

			var countTimer = null;
			function refreshCount() {
				window.clearTimeout(countTimer);
				countTimer = window.setTimeout(function () {
					$('upn-c-count').textContent = '…';
					post('admin\\functions\\Compose@count', { audience: audience() }).then(function (j) {
						$('upn-c-count').textContent = j.status ? j.count : '?';
					}).catch(function () { $('upn-c-count').textContent = '?'; });
				}, 250);
			}

			document.querySelectorAll('input[name="upn-c-who"]').forEach(function (r) {
				r.addEventListener('change', function () { $('upn-c-roles').style.display = r.value === 'roles' && r.checked ? 'block' : 'none'; refreshCount(); });
			});
			document.querySelectorAll('input[name="upn-c-role"], #upn-c-device, #upn-c-seen, #upn-c-segment').forEach(function (el) { el.addEventListener('change', refreshCount); });

			['upn-c-title', 'upn-c-body', 'upn-c-url'].forEach(function (id) {
				$(id).addEventListener('focus', function () { lastField = $(id); });
				$(id).addEventListener('input', function () {
					var c = $(id + '-count'); if (c) { c.textContent = $(id).value.length; }
				});
			});

			document.querySelectorAll('.upn-c-tagbtn').forEach(function (b) {
				b.addEventListener('click', function () {
					var el = lastField, t = b.getAttribute('data-tag');
					var s = el.selectionStart || el.value.length, e = el.selectionEnd || s;
					el.value = el.value.slice(0, s) + t + el.value.slice(e);
					el.focus(); el.setSelectionRange(s + t.length, s + t.length);
					el.dispatchEvent(new Event('input'));
				});
			});

			$('upn-c-preview').addEventListener('click', function () {
				post('admin\\functions\\Compose@preview', { fields: fields() }).then(show);
			});

			/*
			 * Extension point. Pro sets sendMethod (an allow-listed AJAX handler),
			 * extra() (more fields to post), and confirm(count) (its own question)
			 * to turn "Send now" into "Schedule" without replacing this screen.
			 */
			var ext = window.UPN_Compose = window.UPN_Compose || {};
			ext.extras = ext.extras || [];
			ext.fields = fields; ext.audience = audience; ext.refreshCount = refreshCount; ext.post = post; ext.show = show;
			ext.setFields = function (f) {
				var map = { title: 'upn-c-title', body: 'upn-c-body', click_action: 'upn-c-url', icon: 'upn-c-icon', image: 'upn-c-image', tag: 'upn-c-tag', urgency: 'upn-c-urgency', ttl: 'upn-c-ttl' };
				Object.keys(map).forEach(function (k) { if (f && typeof f[k] !== 'undefined' && $(map[k])) { $(map[k]).value = f[k]; } });
				if (ext.onFieldsSet) { ext.onFieldsSet(f); }
			};

			$('upn-c-send').addEventListener('click', function () {
				var n = $('upn-c-count').textContent;
				var question = ext.confirm ? ext.confirm(n) : (<?php echo \wp_json_encode( \__( 'Send this notification to', 'ultimate-push-notifications' ) ); ?> + ' ' + n + ' ' + <?php echo \wp_json_encode( \__( 'device(s)?', 'ultimate-push-notifications' ) ); ?>);
				if (question && !window.confirm(question)) { return; }
				$('upn-c-send').disabled = true;
				var data = { fields: fields(), audience: audience() };
				// Every registered provider contributes; a provider may add to `fields` too (e.g. action buttons).
				var providers = (ext.extras || []).slice(); if (ext.extra) { providers.push(ext.extra); }
				providers.forEach(function (fn) {
					var more = fn() || {};
					Object.keys(more).forEach(function (k) {
						if (k === 'fields' && more.fields && typeof more.fields === 'object') { Object.keys(more.fields).forEach(function (fk) { data.fields[fk] = more.fields[fk]; }); }
						else { data[k] = more[k]; }
					});
				});
				post(ext.sendMethod || 'admin\\functions\\Compose@send', data).then(function (j) {
					show(j); $('upn-c-send').disabled = false;
					if (ext.afterSend) { ext.afterSend(j); }
				}).catch(function () { $('upn-c-send').disabled = false; });
			});

			refreshCount();
		})();
		</script>
		<?php
	}

}
