<?php namespace UltimatePushNotifications\transport;

/**
 * Voluntary Application Server Identification (VAPID, RFC 8292).
 *
 * Owns the site's application server key pair and mints the signed JWT that
 * authorises a push request. The key pair is the site's identity to every push
 * service: rotating it invalidates every existing subscription, so it is
 * generated once and then left alone.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Vapid {

	/**
	 * Option holding the application server key pair.
	 *
	 * @var string
	 */
	const OPTION_KEYS = 'cs_upn_vapid_keys';

	/**
	 * Option holding the contact subject sent with each JWT.
	 *
	 * @var string
	 */
	const OPTION_SUBJECT = 'cs_upn_vapid_subject';

	/**
	 * Counter mixed into token cache keys, so a flush can invalidate them all
	 * without depending on where transients are stored.
	 *
	 * @var string
	 */
	const OPTION_TOKEN_GENERATION = 'cs_upn_vapid_token_gen';

	/**
	 * JWT lifetime. The spec caps this at 24 hours; half that leaves room for
	 * clock skew on either side without re-signing on every send.
	 *
	 * @var int
	 */
	const TOKEN_TTL = 43200;

	/**
	 * Do we have a usable key pair?
	 *
	 * @return bool
	 */
	public static function has_keys() {
		$keys = self::get_keys();
		return ! empty( $keys['public'] ) && ! empty( $keys['private'] );
	}

	/**
	 * Read the stored key pair.
	 *
	 * @return array{public: string, private: string, created: int}|array Empty when unset.
	 */
	public static function get_keys() {
		$keys = \get_option( self::OPTION_KEYS );
		return \is_array( $keys ) ? $keys : array();
	}

	/**
	 * The public key, base64url encoded — this is what the browser needs in
	 * applicationServerKey when subscribing.
	 *
	 * @return string
	 */
	public static function get_public_key() {
		$keys = self::get_keys();
		return isset( $keys['public'] ) ? $keys['public'] : '';
	}

	/**
	 * Generate and store a key pair.
	 *
	 * Refuses to overwrite an existing pair unless explicitly told to: replacing
	 * the key silently would break every subscriber on the site with no
	 * indication of why notifications stopped arriving.
	 *
	 * @param bool $force Replace an existing pair.
	 * @return array|\WP_Error array{public: string, private: string}
	 */
	public static function generate_keys( $force = false ) {

		if ( self::has_keys() && ! $force ) {
			return new \WP_Error(
				'upn_vapid_keys_exist',
				\__( 'A VAPID key pair already exists. Replacing it will invalidate every existing subscription on this site.', 'ultimate-push-notifications' )
			);
		}

		$supported = Ec::keygen_supported();
		if ( true !== $supported ) {
			return new \WP_Error( 'upn_vapid_keygen_unsupported', $supported );
		}

		$pair = Ec::generate_key_pair();
		if ( ! $pair ) {
			return new \WP_Error(
				'upn_vapid_keygen_failed',
				\__( 'Could not generate a VAPID key pair on this server.', 'ultimate-push-notifications' )
			);
		}

		return self::save_keys(
			Ec::b64_encode( $pair['public'] ),
			Ec::b64_encode( $pair['private'] )
		);
	}

	/**
	 * Store a key pair, validating it first.
	 *
	 * Also accepts keys generated elsewhere, which is the fallback for hosts
	 * where OpenSSL cannot generate them locally.
	 *
	 * @param string $public_key  Base64url, 65 bytes decoded.
	 * @param string $private_key Base64url, 32 bytes decoded.
	 * @return array|\WP_Error
	 */
	public static function save_keys( $public_key, $private_key ) {

		$public_raw  = Ec::b64_decode( $public_key );
		$private_raw = Ec::b64_decode( $private_key );

		if ( ! Ec::is_valid_point( $public_raw ) ) {
			return new \WP_Error(
				'upn_vapid_bad_public',
				\__( 'The VAPID public key must be a base64url-encoded 65-byte P-256 point.', 'ultimate-push-notifications' )
			);
		}

		if ( ! \is_string( $private_raw ) || Ec::FIELD_SIZE !== \strlen( $private_raw ) ) {
			return new \WP_Error(
				'upn_vapid_bad_private',
				\__( 'The VAPID private key must be a base64url-encoded 32-byte value.', 'ultimate-push-notifications' )
			);
		}

		/*
		 * A mismatched pair signs tokens every push service rejects, and the
		 * symptom — "notifications silently stopped" — is expensive to diagnose.
		 * Derive the point from the private scalar and compare, rather than
		 * reading back the point we were given.
		 */
		$derived = Ec::derive_public_point( $private_raw );

		if ( false === $derived ) {
			return new \WP_Error(
				'upn_vapid_pair_invalid',
				\__( 'The VAPID private key could not be loaded.', 'ultimate-push-notifications' )
			);
		}

		if ( ! \hash_equals( $derived, $public_raw ) ) {
			return new \WP_Error(
				'upn_vapid_pair_mismatch',
				\__( 'The VAPID private key does not match the public key.', 'ultimate-push-notifications' )
			);
		}

		$stored = array(
			'public'  => Ec::b64_encode( $public_raw ),
			'private' => Ec::b64_encode( $private_raw ),
			'created' => \time(),
		);

		\update_option( self::OPTION_KEYS, $stored, false );
		self::flush_token_cache();

		return $stored;
	}

	/**
	 * The contact address push services can use to reach the site operator.
	 *
	 * @return string A mailto: or https: URI.
	 */
	public static function get_subject() {
		$subject = \get_option( self::OPTION_SUBJECT );

		if ( ! empty( $subject ) ) {
			return $subject;
		}

		$email = \get_option( 'admin_email' );
		if ( $email && \is_email( $email ) ) {
			return 'mailto:' . $email;
		}

		return \home_url( '/' );
	}

	/**
	 * Build the Authorization header value for a push request.
	 *
	 * @param string $endpoint The subscription endpoint URL.
	 * @return string|\WP_Error e.g. "vapid t=<jwt>, k=<public key>"
	 */
	public static function get_authorization_header( $endpoint ) {

		$audience = self::audience_for( $endpoint );
		if ( \is_wp_error( $audience ) ) {
			return $audience;
		}

		$token = self::get_token( $audience );
		if ( \is_wp_error( $token ) ) {
			return $token;
		}

		return 'vapid t=' . $token . ', k=' . self::get_public_key();
	}

	/**
	 * The origin of a push endpoint, which is what the JWT audience must be.
	 *
	 * @param string $endpoint
	 * @return string|\WP_Error
	 */
	public static function audience_for( $endpoint ) {
		$parts = \wp_parse_url( $endpoint );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error(
				'upn_bad_endpoint',
				\__( 'The subscription endpoint is not a valid URL.', 'ultimate-push-notifications' )
			);
		}

		$audience = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$audience .= ':' . $parts['port'];
		}

		return $audience;
	}

	/**
	 * Get a signed token for an audience, reusing a cached one while it lasts.
	 *
	 * A broadcast to a few thousand subscribers hits at most a handful of
	 * distinct push services, so caching per audience turns thousands of ECDSA
	 * signatures into a few.
	 *
	 * @param string $audience
	 * @return string|\WP_Error
	 */
	public static function get_token( $audience ) {

		$cache_key = 'upn_vapid_' . \md5(
			$audience . '|' . self::get_public_key() . '|' . self::get_subject() . '|' . self::token_generation()
		);
		$cached    = \get_transient( $cache_key );

		if ( \is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$token = self::sign_token( $audience );
		if ( \is_wp_error( $token ) ) {
			return $token;
		}

		// Expire the cache before the token does, so a send never presents one
		// that is about to lapse mid-flight.
		\set_transient( $cache_key, $token, self::TOKEN_TTL - 3600 );

		return $token;
	}

	/**
	 * Mint and sign a fresh ES256 JWT.
	 *
	 * @param string $audience
	 * @return string|\WP_Error
	 */
	private static function sign_token( $audience ) {

		$keys = self::get_keys();
		if ( empty( $keys['private'] ) || empty( $keys['public'] ) ) {
			return new \WP_Error(
				'upn_vapid_no_keys',
				\__( 'No VAPID key pair has been generated yet.', 'ultimate-push-notifications' )
			);
		}

		$header = array(
			'typ' => 'JWT',
			'alg' => 'ES256',
		);

		$payload = array(
			'aud' => $audience,
			'exp' => \time() + self::TOKEN_TTL,
			'sub' => self::get_subject(),
		);

		$signing_input = Ec::b64_encode( \wp_json_encode( $header ) )
			. '.' . Ec::b64_encode( \wp_json_encode( $payload ) );

		$private = Ec::private_key_from_scalar(
			Ec::b64_decode( $keys['private'] ),
			Ec::b64_decode( $keys['public'] )
		);

		if ( ! $private ) {
			return new \WP_Error(
				'upn_vapid_key_load_failed',
				\__( 'The stored VAPID private key could not be loaded.', 'ultimate-push-notifications' )
			);
		}

		$der_signature = '';
		if ( ! @\openssl_sign( $signing_input, $der_signature, $private, OPENSSL_ALGO_SHA256 ) ) {
			return new \WP_Error(
				'upn_vapid_sign_failed',
				\__( 'Signing the VAPID token failed.', 'ultimate-push-notifications' )
			);
		}

		// OpenSSL emits DER; JWS ES256 requires fixed-width R || S.
		$raw_signature = Ec::der_signature_to_raw( $der_signature );
		if ( false === $raw_signature ) {
			return new \WP_Error(
				'upn_vapid_sign_encoding',
				\__( 'The VAPID signature could not be converted to JOSE format.', 'ultimate-push-notifications' )
			);
		}

		return $signing_input . '.' . Ec::b64_encode( $raw_signature );
	}

	/**
	 * Drop every cached token.
	 *
	 * Bumps a generation counter that is mixed into every cache key rather than
	 * deleting transient rows. Transients do not necessarily live in the options
	 * table — under an external object cache a DELETE there removes nothing —
	 * and a counter invalidates correctly on every backend.
	 *
	 * @return void
	 */
	public static function flush_token_cache() {
		\update_option( self::OPTION_TOKEN_GENERATION, self::token_generation() + 1, false );
	}

	/**
	 * Current cache generation.
	 *
	 * @return int
	 */
	private static function token_generation() {
		return (int) \get_option( self::OPTION_TOKEN_GENERATION, 0 );
	}

}
