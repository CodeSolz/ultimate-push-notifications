<?php namespace UltimatePushNotifications\transport;

/**
 * Message Encryption for Web Push (RFC 8291) over the aes128gcm content
 * encoding (RFC 8188).
 *
 * Implemented directly rather than via a Composer package. A push plugin that
 * bundles a crypto library inherits that library's PHP floor and its version
 * conflicts with every other plugin that bundles the same thing; this is ~200
 * lines of well-specified work with published test vectors, so the trade favours
 * owning it. See tests/webpush-encryption.php, which runs the RFC 8291 §5
 * vector end to end.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Encryption {

	/**
	 * Record size advertised in the aes128gcm header.
	 *
	 * A single record is used per message. 4096 is what every browser push
	 * service accepts and comfortably exceeds the ~4KB payload ceiling they
	 * enforce anyway.
	 *
	 * @var int
	 */
	const RECORD_SIZE = 4096;

	/**
	 * Maximum plaintext that fits one record, allowing for the 0x02 delimiter
	 * and the 16-byte GCM tag.
	 *
	 * @var int
	 */
	const MAX_PAYLOAD = self::RECORD_SIZE - 17;

	/**
	 * Encrypt a payload for one subscription.
	 *
	 * @param string $payload      UTF-8 plaintext (typically JSON).
	 * @param string $ua_public    Subscriber p256dh key, 65 raw bytes.
	 * @param string $auth_secret  Subscriber auth secret, 16 raw bytes.
	 * @param string $salt         Optional 16-byte salt; generated when omitted.
	 * @param array  $as_keys      Optional fixed sender key pair, for test vectors.
	 * @return string|\WP_Error Encrypted body ready to POST, or an error.
	 */
	public static function encrypt( $payload, $ua_public, $auth_secret, $salt = null, $as_keys = null ) {

		if ( ! Ec::is_valid_point( $ua_public ) ) {
			return new \WP_Error(
				'upn_bad_subscription_key',
				\__( 'The subscription public key is not a valid P-256 point.', 'ultimate-push-notifications' )
			);
		}

		if ( ! \is_string( $auth_secret ) || 16 !== \strlen( $auth_secret ) ) {
			return new \WP_Error(
				'upn_bad_auth_secret',
				\__( 'The subscription auth secret must be 16 bytes.', 'ultimate-push-notifications' )
			);
		}

		if ( \strlen( $payload ) > self::MAX_PAYLOAD ) {
			return new \WP_Error(
				'upn_payload_too_large',
				\sprintf(
					/* translators: 1: payload size, 2: maximum size */
					\__( 'Notification payload is %1$d bytes; the maximum is %2$d.', 'ultimate-push-notifications' ),
					\strlen( $payload ),
					self::MAX_PAYLOAD
				)
			);
		}

		// Ephemeral sender key pair, unique per message unless one is supplied.
		if ( null === $as_keys ) {
			$as_keys = Ec::generate_key_pair();
			if ( ! $as_keys ) {
				return new \WP_Error(
					'upn_keygen_failed',
					\__( 'Could not generate an ephemeral key pair for encryption.', 'ultimate-push-notifications' )
				);
			}
		}

		if ( null === $salt ) {
			$salt = \random_bytes( 16 );
		}

		$shared = self::shared_secret( $as_keys['private'], $as_keys['public'], $ua_public );
		if ( \is_wp_error( $shared ) ) {
			return $shared;
		}

		/*
		 * RFC 8291 §3.4 — derive the input keying material from the ECDH secret,
		 * salted with the subscription's auth secret and bound to both public
		 * keys so the result cannot be replayed against a different subscriber.
		 */
		$key_info = 'WebPush: info' . "\x00" . $ua_public . $as_keys['public'];
		$ikm      = \hash_hkdf( 'sha256', $shared, 32, $key_info, $auth_secret );

		// RFC 8188 §2.2 — content encryption key and nonce.
		$cek   = \hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt );
		$nonce = \hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt );

		// 0x02 marks the final record. A single record is always the last one.
		$plaintext = $payload . "\x02";

		$tag        = '';
		$ciphertext = \openssl_encrypt(
			$plaintext,
			'aes-128-gcm',
			$cek,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			'',
			16
		);

		if ( false === $ciphertext ) {
			return new \WP_Error(
				'upn_encrypt_failed',
				\__( 'AES-128-GCM encryption failed.', 'ultimate-push-notifications' )
			);
		}

		/*
		 * RFC 8188 §2.1 header:
		 *   salt(16) | record size(4, big-endian) | key id length(1) | key id
		 * For Web Push the key id is the sender's public key.
		 */
		return $salt
			. \pack( 'N', self::RECORD_SIZE )
			. \chr( \strlen( $as_keys['public'] ) )
			. $as_keys['public']
			. $ciphertext . $tag;
	}

	/**
	 * Derive the ECDH shared secret between the sender's private key and the
	 * subscriber's public key.
	 *
	 * @param string $as_private 32 raw bytes.
	 * @param string $as_public  65 raw bytes.
	 * @param string $ua_public  65 raw bytes.
	 * @return string|\WP_Error 32 raw bytes.
	 */
	private static function shared_secret( $as_private, $as_public, $ua_public ) {

		$private = Ec::private_key_from_scalar( $as_private, $as_public );
		if ( ! $private ) {
			return new \WP_Error(
				'upn_bad_sender_key',
				\__( 'Could not load the sender private key.', 'ultimate-push-notifications' )
			);
		}

		$peer = Ec::public_key_from_point( $ua_public );
		if ( ! $peer ) {
			return new \WP_Error(
				'upn_bad_subscription_key',
				\__( 'Could not load the subscription public key.', 'ultimate-push-notifications' )
			);
		}

		if ( ! \function_exists( 'openssl_pkey_derive' ) ) {
			return new \WP_Error(
				'upn_no_pkey_derive',
				\__( 'This server\'s PHP build does not provide openssl_pkey_derive(), which is required to send Web Push notifications. PHP 7.3 or newer is needed.', 'ultimate-push-notifications' )
			);
		}

		$shared = @\openssl_pkey_derive( $peer, $private, 32 );

		if ( ! $shared ) {
			return new \WP_Error(
				'upn_ecdh_failed',
				\__( 'ECDH key agreement failed.', 'ultimate-push-notifications' )
			);
		}

		// openssl_pkey_derive strips leading zero bytes on some builds.
		return Ec::pad_field( $shared );
	}

}
