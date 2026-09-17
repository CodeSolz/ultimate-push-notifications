<?php namespace UltimatePushNotifications\health;

use UltimatePushNotifications\transport\DeliveryLog;
use UltimatePushNotifications\pro\Locked;

/**
 * Renders the Health screen.
 *
 * Plain markup on purpose: this page is what someone opens when nothing is
 * arriving, possibly on a phone, possibly while a client waits. It has to
 * work with no JavaScript and read top to bottom as "here is what is wrong,
 * here is what to do".
 *
 * @package Health
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class HealthPage {

	/**
	 * Output the page.
	 *
	 * @return void
	 */
	public static function render() {
		$report = HealthCheck::run( true );
		$recent = DeliveryLog::recent( 10 );

		$band_label = array(
			'good' => \__( 'Healthy', 'ultimate-push-notifications' ),
			'fair' => \__( 'Needs attention', 'ultimate-push-notifications' ),
			'poor' => \__( 'Not delivering', 'ultimate-push-notifications' ),
		);
		$band_color = array(
			'good' => '#00a32a',
			'fair' => '#dba617',
			'poor' => '#d63638',
		);
		$status_icon = array(
			HealthCheck::PASS => '✓',
			HealthCheck::WARN => '!',
			HealthCheck::FAIL => '✕',
		);
		$status_color = array(
			HealthCheck::PASS => '#00a32a',
			HealthCheck::WARN => '#dba617',
			HealthCheck::FAIL => '#d63638',
		);
		?>
		<?php \UltimatePushNotifications\admin\builders\Layout::open( \__( 'Push Notification Health', 'ultimate-push-notifications' ), \__( 'Can this site deliver a push notification right now? Every check below runs live.', 'ultimate-push-notifications' ), '', 'upn-health' ); ?>
			<p class="description">
				<?php \esc_html_e( 'Can this site deliver a push notification right now? Each row below is a way that fails in practice, checked live.', 'ultimate-push-notifications' ); ?>
			</p>

			<div class="upn-score" style="border-left:6px solid <?php echo \esc_attr( $band_color[ $report['band'] ] ); ?>">
				<div style="font-size:3em;font-weight:600;line-height:1"><?php echo (int) $report['score']; ?></div>
				<div>
					<div style="font-size:1.2em;font-weight:600"><?php echo \esc_html( $band_label[ $report['band'] ] ); ?></div>
					<div class="description">
						<?php
						if ( $report['deductions'] ) {
							$parts = array();
							foreach ( $report['deductions'] as $d ) {
								$parts[] = \sprintf( '%s −%d', $d['id'], $d['points'] );
							}
							echo \esc_html( \__( 'Score: 100', 'ultimate-push-notifications' ) . ', ' . \implode( ', ', $parts ) );
						} else {
							\esc_html_e( 'Every check passed.', 'ultimate-push-notifications' );
						}
						?>
					</div>
				</div>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:2em"></th>
						<th style="width:14em"><?php \esc_html_e( 'Check', 'ultimate-push-notifications' ); ?></th>
						<th><?php \esc_html_e( 'Result', 'ultimate-push-notifications' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $report['checks'] as $check ) : ?>
					<tr>
						<td style="color:<?php echo \esc_attr( $status_color[ $check['status'] ] ); ?>;font-weight:700;font-size:1.2em;text-align:center">
							<?php echo \esc_html( $status_icon[ $check['status'] ] ); ?>
						</td>
						<td><strong><?php echo \esc_html( $check['label'] ); ?></strong></td>
						<td>
							<?php echo \esc_html( $check['message'] ); ?>
							<?php if ( ! empty( $check['fix'] ) ) : ?>
								<br/><span class="description">→ <?php echo \esc_html( $check['fix'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! Locked::unlocked( 'health.monitor' ) ) : ?>
				<?php
				echo Locked::panel( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
					'title'   => \__( 'Health Monitor', 'ultimate-push-notifications' ),
					'measure' => \sprintf(
						/* translators: %d: score */
						\__( 'This check scored %d — but it only runs when you open this page.', 'ultimate-push-notifications' ),
						(int) $report['score']
					),
					'body'    => \__( 'Push fails quietly: keys drift, cron stalls, a browser update changes the rules. The monitor runs these checks on a schedule, watches the trend, and tells you the day something changes.', 'ultimate-push-notifications' ),
					'points'  => array(
						\__( 'Daily checks with a history graph, not a one-off score', 'ultimate-push-notifications' ),
						\__( 'Email or Slack when the score drops or failures spike', 'ultimate-push-notifications' ),
						\__( 'One-click fixes: prune dead devices, re-invite lapsed subscribers, repair the service worker', 'ultimate-push-notifications' ),
						\__( 'Unlimited send history and click trends', 'ultimate-push-notifications' ),
					),
				) );
				if ( ! Locked::unlocked( 'ai.explain' ) ) {
					echo '<p class="description">' . Locked::label( \__( 'Plain-English explanation', 'ultimate-push-notifications' ), 'ai.explain' ) . ' — ' . \esc_html__( 'this report read back in plain words, with your own OpenAI, Anthropic, Gemini or Ollama key. The model explains; it never changes anything.', 'ultimate-push-notifications' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
				}
				?>
			<?php else : ?>
				<?php \do_action( 'upn_health_after_checks', $report ); ?>
			<?php endif; ?>

			<div class="section-title"><?php \esc_html_e( 'Recent sends', 'ultimate-push-notifications' ); ?></div>
			<?php if ( ! $recent ) : ?>
				<p class="description"><?php \esc_html_e( 'Nothing has been sent yet.', 'ultimate-push-notifications' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php \esc_html_e( 'When', 'ultimate-push-notifications' ); ?></th>
							<th><?php \esc_html_e( 'Event', 'ultimate-push-notifications' ); ?></th>
							<th><?php \esc_html_e( 'Title', 'ultimate-push-notifications' ); ?></th>
							<th style="text-align:right"><?php \esc_html_e( 'To', 'ultimate-push-notifications' ); ?></th>
							<th style="text-align:right"><?php \esc_html_e( 'Delivered', 'ultimate-push-notifications' ); ?></th>
							<th style="text-align:right"><?php \esc_html_e( 'Failed', 'ultimate-push-notifications' ); ?></th>
							<th style="text-align:right"><?php \esc_html_e( 'Clicked', 'ultimate-push-notifications' ); ?></th>
							<th><?php \esc_html_e( 'First error', 'ultimate-push-notifications' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $recent as $row ) : ?>
						<tr>
							<td><?php echo \esc_html( \mysql2date( 'M j, H:i', $row->sent_at ) ); ?></td>
							<td><code><?php echo \esc_html( $row->notification_type ); ?></code></td>
							<td><?php echo \esc_html( \wp_html_excerpt( $row->title, 50, '…' ) ); ?></td>
							<td style="text-align:right">
								<?php echo (int) $row->recipients; ?>
								<?php if ( ! empty( $row->skipped_count ) ) : ?>
									<span class="description">(<?php echo (int) $row->skipped_count; ?> <?php \esc_html_e( 'held back', 'ultimate-push-notifications' ); ?>)</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right;color:#00a32a"><?php echo (int) $row->success_count; ?></td>
							<td style="text-align:right;color:<?php echo (int) $row->fail_count ? '#d63638' : 'inherit'; ?>">
								<?php echo (int) $row->fail_count; ?>
								<?php if ( (int) $row->pruned_count ) : ?>
									<span class="description">(<?php echo (int) $row->pruned_count; ?> <?php \esc_html_e( 'expired', 'ultimate-push-notifications' ); ?>)</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right">
								<?php echo (int) $row->click_count; ?>
								<?php if ( (int) $row->success_count > 0 ) : ?>
									<span class="description">(<?php echo (int) \round( 100 * (int) $row->click_count / \max( 1, (int) $row->success_count ) ); ?>%)</span>
								<?php endif; ?>
							</td>
							<td class="description"><?php echo \esc_html( \wp_html_excerpt( (string) $row->first_error, 80, '…' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( \is_multisite() && ! Locked::unlocked( 'network' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'Network', 'ultimate-push-notifications' ), 'network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'one licence for every site, all of them on one network screen, and one site\'s configuration copied to the others.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'whitelabel' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'White label', 'ultimate-push-notifications' ), 'whitelabel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'the menu, the Plugins list and the footer under your agency\'s name; the licence and vendor links kept for your own users.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'reports' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'Weekly report', 'ultimate-push-notifications' ), 'reports' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'one page a week or a month, by email or printed to PDF under your own name: sends, clicks, subscribers gained, revenue and goals from push, the health score.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'roles' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'Who may do what', 'ultimate-push-notifications' ), 'roles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'give an editor Compose, a marketer segments and history, a developer automations — without making anyone an administrator.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'api' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'WP-CLI and REST API', 'ultimate-push-notifications' ), 'api' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'wp upn send / health / history / config, and the same over /wp-json/upn-pro/v1 with an application password — for staging → production, monitoring, and headless sites.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( ! Locked::unlocked( 'analytics.goals' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'Goals per send', 'ultimate-push-notifications' ), 'analytics.goals' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'a thank-you page reached, or an event your code raises, credited to the notification that was clicked.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>
			<?php if ( \class_exists( 'WooCommerce' ) && ! Locked::unlocked( 'analytics.attribution' ) ) : ?>
				<p class="description"><?php echo Locked::label( \__( 'Revenue per send', 'ultimate-push-notifications' ), 'analytics.attribution' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?> — <?php \esc_html_e( 'orders placed after a click credited to the notification, exactly: the click and the order are in this same database.', 'ultimate-push-notifications' ); ?></p>
			<?php endif; ?>

			<p class="description" style="margin-top:2em">
				<?php
				printf(
					/* translators: %s: link */
					\esc_html__( 'To prove the full path end to end, open %s and send a test notification to this browser.', 'ultimate-push-notifications' ),
					'<a href="' . \esc_url( \admin_url( 'admin.php?page=cs-upn-register-my-device' ) ) . '">' . \esc_html__( 'Register My Device', 'ultimate-push-notifications' ) . '</a>'
				);
				?>
			</p>
		<?php \UltimatePushNotifications\admin\builders\Layout::close(); ?>
		<?php
	}

}
