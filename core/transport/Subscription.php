<?php namespace UltimatePushNotifications\transport;

/**
 * One registered device.
 *
 * Two shapes live in the same table. A Web Push subscription is an endpoint URL
 * plus the two keys the browser generated (p256dh, auth). A legacy FCM device is
 * a single opaque registration token. Rather than branch on that everywhere,
 * every caller receives a Subscription and asks it which transport it belongs
 * to.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Subscription {

	/**
	 * Web Push (RFC 8030) — the endpoint the browser handed us.
	 *
	 * @var string
	 */
	const TRANSPORT_WEBPUSH = 'webpush';

	/**
	 * Legacy Firebase Cloud Messaging registration token.
	 *
	 * @var string
	 */
	const TRANSPORT_FCM_LEGACY = 'fcm_legacy';

	/** @var int */
	public $id = 0;

	/** @var int */
	public $user_id = 0;

	/** @var string */
	public $transport = self::TRANSPORT_FCM_LEGACY;

	/** @var string Web Push endpoint URL. */
	public $endpoint = '';

	/** @var string Base64url p256dh key. */
	public $p256dh = '';

	/** @var string Base64url auth secret. */
	public $auth = '';

	/** @var string Legacy FCM registration token. */
	public $token = '';

	/** @var string */
	public $device_id = '';

	/** @var string IANA zone the browser reported at subscription, or "". */
	public $timezone = '';

	/**
	 * Build from a database row.
	 *
	 * @param object|array $row
	 * @return self
	 */
	public static function from_row( $row ) {
		$row = (object) $row;
		$sub = new self();

		$sub->id        = isset( $row->id ) ? (int) $row->id : 0;
		$sub->user_id   = isset( $row->user_id ) ? (int) $row->user_id : 0;
		$sub->endpoint  = isset( $row->endpoint ) ? (string) $row->endpoint : '';
		$sub->p256dh    = isset( $row->p256dh ) ? (string) $row->p256dh : '';
		$sub->auth      = isset( $row->auth_secret ) ? (string) $row->auth_secret : '';
		$sub->token     = isset( $row->token ) ? (string) $row->token : '';
		$sub->timezone  = isset( $row->timezone ) ? (string) $row->timezone : '';
		$sub->device_id = isset( $row->device_id ) ? (string) $row->device_id : '';

		/*
		 * Rows written before 1.5.0 have no transport column. Infer it from the
		 * shape of the row rather than trusting a default, so an upgraded site
		 * keeps working before any backfill runs.
		 */
		if ( ! empty( $row->transport ) ) {
			$sub->transport = (string) $row->transport;
		} elseif ( '' !== $sub->endpoint ) {
			$sub->transport = self::TRANSPORT_WEBPUSH;
		} else {
			$sub->transport = self::TRANSPORT_FCM_LEGACY;
		}

		return $sub;
	}

	/**
	 * Is this subscription complete enough to send to?
	 *
	 * @return bool
	 */
	public function is_valid() {
		if ( self::TRANSPORT_WEBPUSH === $this->transport ) {
			return '' !== $this->endpoint && '' !== $this->p256dh && '' !== $this->auth;
		}

		return '' !== $this->token;
	}

	/**
	 * A stable, non-secret identifier for logs and admin screens.
	 *
	 * Endpoints and tokens are send credentials: anyone holding one can push to
	 * that device. They should never be written to a log or rendered in full.
	 *
	 * @return string
	 */
	public function fingerprint() {
		$material = self::TRANSPORT_WEBPUSH === $this->transport ? $this->endpoint : $this->token;
		return \substr( \hash( 'sha256', $material ), 0, 12 );
	}

	/**
	 * The push service this subscription belongs to, for grouping in reports.
	 *
	 * @return string e.g. "fcm.googleapis.com", or "" when unknown.
	 */
	public function service_host() {
		if ( self::TRANSPORT_WEBPUSH !== $this->transport || '' === $this->endpoint ) {
			return '';
		}

		$parts = \wp_parse_url( $this->endpoint );
		return isset( $parts['host'] ) ? $parts['host'] : '';
	}

}
