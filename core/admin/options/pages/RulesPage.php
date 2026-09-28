<?php namespace UltimatePushNotifications\admin\options\pages;

use UltimatePushNotifications\automation\Rules;
use UltimatePushNotifications\automation\TriggerRegistry;
use UltimatePushNotifications\automation\Trigger;
use UltimatePushNotifications\messaging\MergeTags;
use UltimatePushNotifications\pro\Locked;

/**
 * Event automation rules: the list and the editor, embedded in the
 * Automations screen.
 *
 * Server-rendered. The only script is a few lines that show the param, tag
 * and audience blocks for the trigger currently chosen and prefill a new
 * rule with the trigger's suggested copy.
 *
 * @package Options
 * @since 1.6.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	die();
}

class RulesPage {

	const PAGE   = 'cs-upn-automations';
	const NONCE  = 'upn_rule';
	const CAP    = 'manage_options';

	/**
	 * The capability needed to manage automations.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability needed to manage automations.
		 *
		 * @param string $cap
		 */
		return (string) \apply_filters( 'upn_automations_capability', self::CAP );
	}

	/**
	 * Handle save / enable / disable / delete, then redirect back to the list.
	 *
	 * @return void
	 */
	public static function handle_request() {
		if ( ! isset( $_REQUEST['page'] ) || self::PAGE !== $_REQUEST['page'] || empty( $_REQUEST['upn_rule_action'] ) ) {
			return;
		}
		if ( ! \current_user_can( self::capability() ) ) {
			\wp_die( \esc_html__( 'You do not have permission to change automations.', 'ultimate-push-notifications' ) );
		}

		$action = \sanitize_key( \wp_unslash( $_REQUEST['upn_rule_action'] ) );
		$nonce  = isset( $_REQUEST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! \wp_verify_nonce( $nonce, self::NONCE ) ) {
			\wp_die( \esc_html__( 'Security check failed. Please go back and try again.', 'ultimate-push-notifications' ) );
		}

		$back = \admin_url( 'admin.php?page=' . self::PAGE );

		switch ( $action ) {
			case 'save':
				$input = isset( $_POST['rule'] ) && \is_array( $_POST['rule'] ) ? \wp_unslash( $_POST['rule'] ) : array();
				// Params are posted per trigger so every trigger's fields can share the form; keep the chosen trigger's.
				$trigger = isset( $input['trigger'] ) ? \sanitize_text_field( $input['trigger'] ) : '';
				$input['params'] = isset( $input['params'][ $trigger ] ) && \is_array( $input['params'][ $trigger ] ) ? $input['params'][ $trigger ] : array();
				$input['enabled'] = ! empty( $input['enabled'] );

				$saved = Rules::save( $input );
				if ( \is_wp_error( $saved ) ) {
					$back = \add_query_arg(
						array(
							'rule'  => ! empty( $input['id'] ) ? (int) $input['id'] : 'new',
							'error' => \rawurlencode( $saved->get_error_message() ),
						),
						$back
					);
				} else {
					$back = \add_query_arg( 'saved', (int) $saved['id'], $back );
				}
				break;

			case 'enable':
			case 'disable':
				$id = isset( $_REQUEST['rule'] ) ? (int) $_REQUEST['rule'] : 0;
				Rules::set_enabled( $id, 'enable' === $action );
				$back = \add_query_arg( 'saved', $id, $back );
				break;

			case 'delete':
				$id = isset( $_REQUEST['rule'] ) ? (int) $_REQUEST['rule'] : 0;
				Rules::delete( $id );
				$back = \add_query_arg( 'deleted', 1, $back );
				break;
		}

		\wp_safe_redirect( $back );
		exit;
	}

	/**
	 * The "Event automations" section: editor when ?rule= is set, else the list.
	 *
	 * @return void
	 */
	public static function render_section() {
		echo '<div class="section-title">' . \esc_html__( 'Event automations', 'ultimate-push-notifications' ) . '</div>';

		if ( isset( $_GET['rule'] ) ) {
			$id = \sanitize_key( \wp_unslash( $_GET['rule'] ) );
			self::render_editor( 'new' === $id ? null : Rules::get( (int) $id ) );
			return;
		}

		self::render_list();
	}

	/**
	 * @return void
	 */
	private static function render_notices() {
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Automation saved.', 'ultimate-push-notifications' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Automation deleted.', 'ultimate-push-notifications' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . \esc_html( \sanitize_text_field( \wp_unslash( $_GET['error'] ) ) ) . '</p></div>';
		}
	}

	/**
	 * @return void
	 */
	private static function render_list() {
		$rules      = Rules::all();
		$live       = Rules::live();
		$over       = Rules::over_limit();
		$limit      = Rules::active_limit();
		$new_url    = \add_query_arg( 'rule', 'new', \admin_url( 'admin.php?page=' . self::PAGE ) );
		$base       = \admin_url( 'admin.php?page=' . self::PAGE );

		self::render_notices();
		?>
		<div class="well"><p>
			<?php \esc_html_e( 'When something happens — an order, a comment, a form, a message — tell the right people. Each automation is a trigger, an audience and a message. Members can turn any automation off for themselves under Set Notifications.', 'ultimate-push-notifications' ); ?>
		</p></div>

		<?php if ( $limit > 0 && $over && ! Locked::unlocked( 'automations.unlimited' ) ) : ?>
			<?php
			echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
				'title'   => \__( 'Unlimited automations', 'ultimate-push-notifications' ),
				'measure' => \sprintf(
					/* translators: 1: total rules, 2: live, 3: paused */
					\__( '%1$d automations saved on this site — %2$d running, %3$d paused by the free limit.', 'ultimate-push-notifications' ),
					\count( $rules ),
					\count( $live ),
					\count( $over )
				),
				'body'    => \sprintf(
					/* translators: %d: limit */
					\__( 'The free version runs %d automations at a time. Turn one off to run another, or run them all with Pro.', 'ultimate-push-notifications' ),
					$limit
				),
			) );
			?>
		<?php endif; ?>

		<?php if ( \class_exists( 'WooCommerce' ) && ! Locked::unlocked( 'commerce' ) ) : ?>
			<?php
			echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
				'title'   => \__( 'Store automations', 'ultimate-push-notifications' ),
				'measure' => \__( 'Seven in ten carts on a WooCommerce store are abandoned; a push reminder recovers 5–12% of them.', 'ultimate-push-notifications' ),
				'body'    => \__( 'Abandoned-cart and browsed-but-not-bought reminders at your delays, a "tell me when it is back" button on out-of-stock products and a "tell me if the price drops" button on the rest — all through the same log and delivery rules, with the order credited to the notification.', 'ultimate-push-notifications' ),
			) );
			?>
		<?php endif; ?>

		<p><a href="<?php echo \esc_url( $new_url ); ?>" class="button button-primary"><?php \esc_html_e( 'Add automation', 'ultimate-push-notifications' ); ?></a></p>

		<?php if ( ! $rules ) : ?>
			<p><em><?php \esc_html_e( 'No automations yet.', 'ultimate-push-notifications' ); ?></em></p>
			<?php
			return;
		endif;
		?>

		<table class="wp-list-table widefat striped upn-table">
			<thead>
				<tr>
					<th style="width:26%"><?php \esc_html_e( 'Automation', 'ultimate-push-notifications' ); ?></th>
					<th style="width:22%"><?php \esc_html_e( 'When', 'ultimate-push-notifications' ); ?></th>
					<th style="width:22%"><?php \esc_html_e( 'Who', 'ultimate-push-notifications' ); ?></th>
					<th style="width:14%"><?php \esc_html_e( 'Status', 'ultimate-push-notifications' ); ?></th>
					<th><?php \esc_html_e( 'Actions', 'ultimate-push-notifications' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rules as $id => $rule ) : ?>
				<?php
				$trigger  = TriggerRegistry::get( $rule['trigger'] );
				$edit_url = \add_query_arg( 'rule', $id, $base );
				$toggle   = \wp_nonce_url( \add_query_arg( array( 'upn_rule_action' => $rule['enabled'] ? 'disable' : 'enable', 'rule' => $id ), $base ), self::NONCE );
				$delete   = \wp_nonce_url( \add_query_arg( array( 'upn_rule_action' => 'delete', 'rule' => $id ), $base ), self::NONCE );

				if ( ! $rule['enabled'] ) {
					$status = array( 'off', \__( 'Off', 'ultimate-push-notifications' ) );
				} elseif ( ! $trigger || ! $trigger->available() ) {
					$status = array( 'warn', \__( 'Plugin not active', 'ultimate-push-notifications' ) );
				} elseif ( isset( $live[ $id ] ) ) {
					$status = array( 'on', \__( 'Live', 'ultimate-push-notifications' ) );
				} else {
					$status = array( 'warn', \__( 'Paused — over limit', 'ultimate-push-notifications' ) );
				}
				?>
				<tr>
					<td>
						<strong><a href="<?php echo \esc_url( $edit_url ); ?>"><?php echo \esc_html( $rule['name'] ); ?></a></strong><br/>
						<span class="description"><?php echo \esc_html( \mb_strimwidth( $rule['title'], 0, 60, '…' ) ); ?></span>
					</td>
					<td>
						<?php echo \esc_html( $trigger ? $trigger->label() : $rule['trigger'] ); ?>
						<?php
						$filters = array();
						foreach ( $rule['params'] as $k => $v ) {
							if ( '' !== $v && $trigger ) {
								$p = $trigger->params();
								$filters[] = ( isset( $p[ $k ]['label'] ) ? $p[ $k ]['label'] : $k ) . ': ' . ( isset( $p[ $k ]['options'][ $v ] ) ? $p[ $k ]['options'][ $v ] : $v );
							}
						}
						if ( $filters ) {
							echo '<br/><span class="description">' . \esc_html( \implode( ' · ', $filters ) ) . '</span>';
						}
						?>
					</td>
					<td><?php echo \esc_html( Rules::describe_audience( $rule ) ); ?></td>
					<td><span class="upn-status upn-status--<?php echo \esc_attr( $status[0] ); ?>"><?php echo \esc_html( $status[1] ); ?></span></td>
					<td>
						<a href="<?php echo \esc_url( $edit_url ); ?>"><?php \esc_html_e( 'Edit', 'ultimate-push-notifications' ); ?></a> |
						<a href="<?php echo \esc_url( $toggle ); ?>"><?php echo $rule['enabled'] ? \esc_html__( 'Turn off', 'ultimate-push-notifications' ) : \esc_html__( 'Turn on', 'ultimate-push-notifications' ); ?></a> |
						<a href="<?php echo \esc_url( $delete ); ?>" class="submitdelete" onclick="return confirm('<?php echo \esc_js( \__( 'Delete this automation?', 'ultimate-push-notifications' ) ); ?>')"><?php \esc_html_e( 'Delete', 'ultimate-push-notifications' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<style>
			.upn-status{display:inline-block;padding:1px 8px;border-radius:3px;font-size:12px;background:#f0f0f1;color:#3c434a}
			.upn-status--on{background:#edfaef;color:#007017}
			.upn-status--warn{background:#fcf0e4;color:#8a4b00}
		</style>
		<?php
	}

	/**
	 * @param array|null $rule null for a new rule.
	 * @return void
	 */
	private static function render_editor( $rule ) {
		$is_new = null === $rule;
		$rule   = $is_new ? Rules::defaults() : $rule;
		$roles  = \wp_roles()->get_names();
		$base   = \admin_url( 'admin.php?page=' . self::PAGE );
		$common = MergeTags::grouped();

		$defaults_json = array();
		foreach ( TriggerRegistry::all() as $t ) {
			$defaults_json[ $t->key() ] = $t->defaults();
		}

		self::render_notices();
		?>
		<p><a href="<?php echo \esc_url( $base ); ?>">&larr; <?php \esc_html_e( 'All automations', 'ultimate-push-notifications' ); ?></a></p>

		<form method="post" action="<?php echo \esc_url( $base ); ?>" id="upn-rule-form" data-upn-defaults="<?php echo \esc_attr( \wp_json_encode( $defaults_json ) ); ?>" data-upn-new="<?php echo $is_new ? '1' : '0'; ?>">
			<?php \wp_nonce_field( self::NONCE ); ?>
			<input type="hidden" name="upn_rule_action" value="save" />
			<input type="hidden" name="rule[id]" value="<?php echo (int) $rule['id']; ?>" />

			<div class="upn-form">
				<div class="form-group">
						<div class="label"><label for="upn-r-name"><?php \esc_html_e( 'Name', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-r-name" class="regular-text" name="rule[name]" value="<?php echo \esc_attr( $rule['name'] ); ?>" maxlength="80" placeholder="<?php \esc_attr_e( 'Optional — defaults to the trigger name', 'ultimate-push-notifications' ); ?>" />
						&nbsp; <label><input type="checkbox" name="rule[enabled]" value="1" <?php \checked( $rule['enabled'] ); ?> /> <?php \esc_html_e( 'Enabled', 'ultimate-push-notifications' ); ?></label></div>
					</div>

				<div class="form-group">
						<div class="label"><label for="upn-r-trigger"><?php \esc_html_e( 'When', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><select id="upn-r-trigger" name="rule[trigger]">
							<option value=""><?php \esc_html_e( '— Choose a trigger —', 'ultimate-push-notifications' ); ?></option>
							<?php foreach ( TriggerRegistry::grouped() as $group => $triggers ) : ?>
								<optgroup label="<?php echo \esc_attr( $group ); ?>">
									<?php foreach ( $triggers as $t ) : ?>
										<option value="<?php echo \esc_attr( $t->key() ); ?>" <?php \selected( $t->key(), $rule['trigger'] ); ?> <?php \disabled( ! $t->available() && $t->key() !== $rule['trigger'] ); ?>>
											<?php echo \esc_html( $t->label() ); ?><?php echo $t->available() ? '' : ' (' . \esc_html__( 'plugin not active', 'ultimate-push-notifications' ) . ')'; ?>
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>

						<?php foreach ( TriggerRegistry::all() as $t ) : ?>
							<?php $params = $t->params(); ?>
							<div class="upn-trigger-block" data-upn-trigger="<?php echo \esc_attr( $t->key() ); ?>" hidden>
								<?php if ( '' !== $t->description() ) : ?>
									<p class="description"><?php echo \esc_html( $t->description() ); ?></p>
								<?php endif; ?>
								<?php $next = $t->next(); if ( ! empty( $next['key'] ) && ! Locked::unlocked( $next['key'] ) ) : ?>
									<p class="description"><?php echo Locked::label( $next['label'], $next['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php echo \esc_html( $next['text'] ); ?></p>
								<?php endif; ?>
								<?php foreach ( $params as $key => $p ) : ?>
									<?php $current = $t->key() === $rule['trigger'] && isset( $rule['params'][ $key ] ) ? $rule['params'][ $key ] : ''; ?>
									<p>
										<label><?php echo \esc_html( $p['label'] ); ?>
											<select name="rule[params][<?php echo \esc_attr( $t->key() ); ?>][<?php echo \esc_attr( $key ); ?>]">
												<option value=""><?php \esc_html_e( 'Any', 'ultimate-push-notifications' ); ?></option>
												<?php foreach ( $p['options'] as $value => $label ) : ?>
													<option value="<?php echo \esc_attr( $value ); ?>" <?php \selected( (string) $value, (string) $current ); ?>><?php echo \esc_html( $label ); ?></option>
												<?php endforeach; ?>
												<?php if ( '' !== $current && ! isset( $p['options'][ $current ] ) ) : ?>
													<option value="<?php echo \esc_attr( $current ); ?>" selected><?php echo \esc_html( $current ); ?></option>
												<?php endif; ?>
											</select>
										</label>
									</p>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?></div>
					</div>

				<div class="form-group">
						<div class="label"><label><?php echo Locked::label( \__( 'Only when', 'ultimate-push-notifications' ), 'automations.conditions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?></label></div>
						<div class="input-group"><?php if ( Locked::unlocked( 'automations.conditions' ) ) : ?>
							<?php
							/**
							 * Pro renders its condition builder here; conditions are stored on the rule
							 * through the upn_automation_sanitize_conditions filter and evaluated on
							 * upn_automation_rule_matches.
							 *
							 * @param array $rule
							 */
							\do_action( 'upn_rule_editor_conditions', $rule );
							?>
						<?php else : ?>
							<?php
							echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
								'title'  => \__( 'Conditions', 'ultimate-push-notifications' ),
								'body'   => \__( 'Run this automation only when the event matches extra conditions, with AND/OR groups.', 'ultimate-push-notifications' ),
								'points' => array(
									\__( 'Order total above an amount, or containing a product category', 'ultimate-push-notifications' ),
									\__( 'Customer\'s first order, or their fifth', 'ultimate-push-notifications' ),
									\__( 'Form field equals, contains, or is empty', 'ultimate-push-notifications' ),
								),
							) );
							?>
						<?php endif; ?></div>
					</div>

				<div class="form-group">
						<div class="label"><label for="upn-r-audience"><?php \esc_html_e( 'Who', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><select id="upn-r-audience" name="rule[audience]">
							<?php foreach ( TriggerRegistry::all() as $t ) : ?>
								<?php foreach ( $t->people() as $name => $label ) : ?>
									<option value="<?php echo \esc_attr( $name ); ?>" data-upn-trigger="<?php echo \esc_attr( $t->key() ); ?>" <?php \selected( $t->key() === $rule['trigger'] && $name === $rule['audience'] ); ?>><?php echo \esc_html( $label ); ?></option>
								<?php endforeach; ?>
							<?php endforeach; ?>
							<option value="roles" <?php \selected( 'roles', $rule['audience'] ); ?>><?php \esc_html_e( 'Users with a role…', 'ultimate-push-notifications' ); ?></option>
							<option value="users" <?php \selected( 'users', $rule['audience'] ); ?>><?php \esc_html_e( 'Specific users…', 'ultimate-push-notifications' ); ?></option>
							<option value="members" <?php \selected( 'members', $rule['audience'] ); ?>><?php \esc_html_e( 'All logged-in subscribers', 'ultimate-push-notifications' ); ?></option>
							<option value="visitors" <?php \selected( 'visitors', $rule['audience'] ); ?>><?php \esc_html_e( 'All visitor subscribers', 'ultimate-push-notifications' ); ?></option>
							<option value="everyone" <?php \selected( 'everyone', $rule['audience'] ); ?>><?php \esc_html_e( 'Every subscriber', 'ultimate-push-notifications' ); ?></option>
						</select>

						<div data-upn-audience="roles" style="margin-top:.5em" hidden>
							<?php foreach ( $roles as $slug => $label ) : ?>
								<label style="display:inline-block;margin-right:1em"><input type="checkbox" name="rule[roles][]" value="<?php echo \esc_attr( $slug ); ?>" <?php \checked( \in_array( $slug, $rule['roles'], true ) ); ?> /> <?php echo \esc_html( \translate_user_role( $label ) ); ?></label>
							<?php endforeach; ?>
						</div>
						<div data-upn-audience="users" style="margin-top:.5em" hidden>
							<input type="text" class="regular-text" name="rule[user_ids]" value="<?php echo \esc_attr( \implode( ', ', $rule['user_ids'] ) ); ?>" placeholder="<?php \esc_attr_e( 'User IDs, comma-separated', 'ultimate-push-notifications' ); ?>" />
						</div>
						<p style="margin-top:.5em"><label><input type="checkbox" name="rule[notify_actor]" value="1" <?php \checked( $rule['notify_actor'] ); ?> /> <?php \esc_html_e( 'Also notify the person who caused the event', 'ultimate-push-notifications' ); ?></label></p>
						<p class="description"><?php \esc_html_e( 'Only people with a registered device receive anything. Each member can turn an automation off for themselves.', 'ultimate-push-notifications' ); ?></p></div>
					</div>

				<div class="form-group">
						<div class="label"><label for="upn-r-title"><?php \esc_html_e( 'Title', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-r-title" class="large-text" name="rule[title]" value="<?php echo \esc_attr( $rule['title'] ); ?>" maxlength="100" required /></div>
					</div>
				<div class="form-group">
						<div class="label"><label for="upn-r-body"><?php \esc_html_e( 'Message', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><textarea id="upn-r-body" class="large-text" rows="2" name="rule[body]" maxlength="300"><?php echo \esc_textarea( $rule['body'] ); ?></textarea>
						<div class="upn-tags">
							<?php foreach ( TriggerRegistry::all() as $t ) : ?>
								<?php if ( ! $t->tags() ) { continue; } ?>
								<p class="description" data-upn-trigger="<?php echo \esc_attr( $t->key() ); ?>" hidden>
									<?php \esc_html_e( 'Event tags:', 'ultimate-push-notifications' ); ?>
									<?php foreach ( $t->tags() as $tag => $label ) : ?>
										<code title="<?php echo \esc_attr( $label ); ?>">{<?php echo \esc_html( $tag ); ?>}</code>
									<?php endforeach; ?>
								</p>
							<?php endforeach; ?>
							<p class="description">
								<?php \esc_html_e( 'Always available:', 'ultimate-push-notifications' ); ?>
								<?php foreach ( $common as $group => $items ) : ?>
									<?php foreach ( $items as $tag => $label ) : ?>
										<code title="<?php echo \esc_attr( $label ); ?>">{<?php echo \esc_html( $tag ); ?>}</code>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</p>
						</div></div>
					</div>
				<div class="form-group">
						<div class="label"><label for="upn-r-url"><?php \esc_html_e( 'Opens', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" id="upn-r-url" class="large-text" name="rule[url]" value="<?php echo \esc_attr( $rule['url'] ); ?>" placeholder="<?php \esc_attr_e( 'Leave blank for the event\'s own link', 'ultimate-push-notifications' ); ?>" /></div>
					</div>
				<div class="form-group">
						<div class="label"><label><?php \esc_html_e( 'Images', 'ultimate-push-notifications' ); ?></label></div>
						<div class="input-group"><input type="text" class="regular-text" name="rule[icon]" value="<?php echo \esc_attr( $rule['icon'] ); ?>" placeholder="<?php \esc_attr_e( 'Icon URL (defaults to the site icon)', 'ultimate-push-notifications' ); ?>" /><br/>
						<input type="text" class="regular-text" style="margin-top:.4em" name="rule[image]" value="<?php echo \esc_attr( $rule['image'] ); ?>" placeholder="<?php \esc_attr_e( 'Large image URL (optional)', 'ultimate-push-notifications' ); ?>" /></div>
					</div>
				<div class="form-group">
						<div class="label"><label><?php echo Locked::label( \__( 'Delivery', 'ultimate-push-notifications' ), 'automations.digest' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?></label></div>
						<div class="input-group"><?php if ( Locked::unlocked( 'automations.digest' ) ) : ?>
							<?php
							/**
							 * Pro renders per-rule delivery settings here (digest window and wording);
							 * they are stored on the rule through upn_automation_sanitize_extra.
							 *
							 * @param array $rule
							 */
							\do_action( 'upn_rule_editor_extras', $rule );
							?>
						<?php else : ?>
							<?php
							echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
								'title'  => \__( 'Digest', 'ultimate-push-notifications' ),
								'body'   => \__( 'Batch this automation: one "5 new comments" instead of five notifications, every N minutes.', 'ultimate-push-notifications' ),
								'points' => array(
									\__( 'Per rule: every 15 minutes, hourly, or daily', 'ultimate-push-notifications' ),
									\__( 'A single event still goes out on its own', 'ultimate-push-notifications' ),
									\__( 'Quiet hours and per-subscriber caps site-wide', 'ultimate-push-notifications' ),
								),
							) );
							?>
							<p class="description"><?php echo Locked::label( \__( 'Also by email, Slack, Discord, Telegram, SMS or WhatsApp', 'ultimate-push-notifications' ), 'carriers' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'always, or only when push has nobody to reach or fails — the floor an order alert needs.', 'ultimate-push-notifications' ); ?></p>
						<?php endif; ?></div>
					</div>
			</div>

			<?php echo \UltimatePushNotifications\admin\builders\Layout::submit_bar( \get_submit_button( $is_new ? \__( 'Add automation', 'ultimate-push-notifications' ) : \__( 'Save automation', 'ultimate-push-notifications' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
		</form>

		<script>
		( function () {
			var form = document.getElementById( 'upn-rule-form' );
			if ( ! form ) { return; }
			var trig = document.getElementById( 'upn-r-trigger' ), aud = document.getElementById( 'upn-r-audience' );
			var defaults = {}; try { defaults = JSON.parse( form.getAttribute( 'data-upn-defaults' ) || '{}' ); } catch ( e ) {}
			var isNew = '1' === form.getAttribute( 'data-upn-new' );

			function sync( prefill ) {
				var key = trig.value;
				form.querySelectorAll( '[data-upn-trigger]' ).forEach( function ( el ) {
					if ( 'OPTION' === el.tagName ) { el.hidden = el.getAttribute( 'data-upn-trigger' ) !== key; el.disabled = el.hidden; }
					else { el.hidden = el.getAttribute( 'data-upn-trigger' ) !== key; }
				} );
				if ( aud.options[ aud.selectedIndex ] && aud.options[ aud.selectedIndex ].hidden ) {
					var first = Array.prototype.find.call( aud.options, function ( o ) { return ! o.hidden; } );
					if ( first ) { aud.value = first.value; }
				}
				if ( prefill && defaults[ key ] ) {
					var d = defaults[ key ];
					[ 'title', 'body', 'url' ].forEach( function ( f ) {
						var el = form.querySelector( '[name="rule[' + f + ']"]' );
						if ( el && '' === el.value && d[ f ] ) { el.value = d[ f ]; }
					} );
					if ( d.audience ) { aud.value = d.audience; }
					if ( d.roles ) { form.querySelectorAll( '[name="rule[roles][]"]' ).forEach( function ( cb ) { cb.checked = d.roles.indexOf( cb.value ) !== -1; } ); }
				}
				syncAudience();
			}
			function syncAudience() {
				form.querySelectorAll( '[data-upn-audience]' ).forEach( function ( el ) { el.hidden = el.getAttribute( 'data-upn-audience' ) !== aud.value; } );
			}
			trig.addEventListener( 'change', function () { sync( isNew ); } );
			aud.addEventListener( 'change', syncAudience );
			sync( false );
		} )();
		</script>
		<?php
	}

}
