<?php namespace UltimatePushNotifications\transport;

/**
 * The outcome of one send attempt.
 *
 * Deliberately richer than a boolean. The queue needs to know whether to retry
 * and when; the subscription store needs to know whether the device is gone for
 * good; the health report needs to know why things failed. Collapsing all of
 * that into true/false is how a push channel decays silently — which is the
 * single most common complaint about every product in this category.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class SendResult {

	/** @var bool */
	public $success = false;

	/** @var int HTTP status, or 0 when the request never completed. */
	public $status_code = 0;

	/** @var string Machine-readable reason, empty on success. */
	public $error_code = '';

	/** @var string Human-readable reason, safe to show an administrator. */
	public $error_message = '';

	/**
	 * The push service says this subscription no longer exists. The row should
	 * be deleted — retrying will never succeed.
	 *
	 * @var bool
	 */
	public $subscription_gone = false;

	/**
	 * A later attempt could plausibly succeed (timeout, 5xx, rate limit).
	 *
	 * @var bool
	 */
	public $retryable = false;

	/**
	 * Seconds to wait before retrying, when the service asked for a delay.
	 *
	 * @var int|null
	 */
	public $retry_after = null;

	/**
	 * Which transport produced this result.
	 *
	 * @var string
	 */
	public $transport = '';

	/**
	 * A successful send.
	 *
	 * @param int    $status_code
	 * @param string $transport
	 * @return self
	 */
	public static function success( $status_code = 201, $transport = '' ) {
		$result              = new self();
		$result->success     = true;
		$result->status_code = $status_code;
		$result->transport   = $transport;
		return $result;
	}

	/**
	 * A failure.
	 *
	 * @param string $code
	 * @param string $message
	 * @param int    $status_code
	 * @param string $transport
	 * @return self
	 */
	public static function failure( $code, $message, $status_code = 0, $transport = '' ) {
		$result                = new self();
		$result->success       = false;
		$result->error_code    = $code;
		$result->error_message = $message;
		$result->status_code   = $status_code;
		$result->transport     = $transport;
		return $result;
	}

	/**
	 * A failure meaning "this device is gone".
	 *
	 * @param string $code
	 * @param string $message
	 * @param int    $status_code
	 * @param string $transport
	 * @return self
	 */
	public static function gone( $code, $message, $status_code = 410, $transport = '' ) {
		$result                    = self::failure( $code, $message, $status_code, $transport );
		$result->subscription_gone = true;
		return $result;
	}

	/**
	 * A failure worth retrying.
	 *
	 * @param string   $code
	 * @param string   $message
	 * @param int      $status_code
	 * @param int|null $retry_after
	 * @param string   $transport
	 * @return self
	 */
	public static function retry( $code, $message, $status_code = 0, $retry_after = null, $transport = '' ) {
		$result              = self::failure( $code, $message, $status_code, $transport );
		$result->retryable   = true;
		$result->retry_after = $retry_after;
		return $result;
	}

	/**
	 * Build from a WP_Error.
	 *
	 * @param \WP_Error $error
	 * @param string    $transport
	 * @return self
	 */
	public static function from_wp_error( $error, $transport = '' ) {
		$code = $error->get_error_code();

		/*
		 * A transport-level failure (DNS, connection refused, timeout) says
		 * nothing about the subscription, so it is always worth another attempt.
		 */
		$network_codes = array( 'http_request_failed' );

		if ( \in_array( $code, $network_codes, true ) ) {
			return self::retry( $code, $error->get_error_message(), 0, null, $transport );
		}

		return self::failure( $code, $error->get_error_message(), 0, $transport );
	}

	/**
	 * A one-line summary for the delivery log.
	 *
	 * @return string
	 */
	public function summary() {
		if ( $this->success ) {
			return \sprintf( 'ok (%d)', $this->status_code );
		}

		$parts = array( $this->error_code );

		if ( $this->status_code ) {
			$parts[] = 'HTTP ' . $this->status_code;
		}
		if ( $this->subscription_gone ) {
			$parts[] = 'gone';
		} elseif ( $this->retryable ) {
			$parts[] = 'retryable';
		}

		return \implode( ' · ', \array_filter( $parts ) );
	}

}
