<?php namespace UltimatePushNotifications\transport;

/**
 * P-256 key encoding helpers.
 *
 * Web Push works in raw key material: a subscription carries a 65-byte
 * uncompressed public point, and VAPID keys are exchanged as base64url of the
 * same. OpenSSL wants DER/PEM. This class is the translation layer between the
 * two, and nothing else in the transport should need to know about ASN.1.
 *
 * @package Transport
 * @since 1.5.0
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}

class Ec {

	/**
	 * Curve used by Web Push. RFC 8291 permits nothing else.
	 *
	 * @var string
	 */
	const CURVE = 'prime256v1';

	/**
	 * Length in bytes of a P-256 field element.
	 *
	 * @var int
	 */
	const FIELD_SIZE = 32;

	/**
	 * DER prefix for a SubjectPublicKeyInfo wrapping an uncompressed P-256 point.
	 *
	 * SEQUENCE {
	 *   SEQUENCE { OID id-ecPublicKey, OID prime256v1 },
	 *   BIT STRING (0 unused bits) { 0x04 || X || Y }
	 * }
	 *
	 * Fixed for this curve and point format, so it can be a constant rather than
	 * a general-purpose DER encoder.
	 *
	 * @var string
	 */
	const SPKI_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

	/**
	 * Base64url encode without padding, as used throughout the Web Push specs.
	 *
	 * @param string $binary
	 * @return string
	 */
	public static function b64_encode( $binary ) {
		return \rtrim( \strtr( \base64_encode( $binary ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode, tolerating missing padding and standard-alphabet input.
	 *
	 * @param string $encoded
	 * @return string|false Binary string, or false when the input is not valid base64.
	 */
	public static function b64_decode( $encoded ) {
		$encoded = \strtr( \trim( (string) $encoded ), '-_', '+/' );
		$pad     = \strlen( $encoded ) % 4;
		if ( $pad ) {
			$encoded .= \str_repeat( '=', 4 - $pad );
		}
		return \base64_decode( $encoded, true );
	}

	/**
	 * Order of the P-256 base point, big-endian.
	 *
	 * A private scalar must lie in [1, n-1].
	 *
	 * @var string
	 */
	const ORDER_HEX = 'FFFFFFFF00000000FFFFFFFFFFFFFFFFBCE6FAADA7179E84F3B9CAC2FC632551';

	/**
	 * Is EC key generation usable on this host?
	 *
	 * Deliberately does NOT depend on openssl_pkey_new(). That function needs a
	 * readable openssl.cnf, which plenty of hosts (and every default XAMPP
	 * install) lack, and the failure is fixed at library load — neither the
	 * per-call config option nor putenv() can route around it. Because every
	 * send needs a fresh ephemeral key, that would mean "cannot send at all".
	 *
	 * Instead a scalar comes from random_bytes() and OpenSSL derives its point
	 * from a key structure that omits the public field. Those two calls work
	 * without any configuration file, so this checks exactly what will be used.
	 *
	 * @return true|string True when supported, otherwise a human-readable reason.
	 */
	public static function keygen_supported() {
		if ( ! \function_exists( 'openssl_pkey_get_private' ) || ! \function_exists( 'openssl_pkey_get_details' ) ) {
			return \__( 'The OpenSSL PHP extension is not available.', 'ultimate-push-notifications' );
		}

		if ( ! \function_exists( 'openssl_pkey_derive' ) ) {
			return \__( 'This server\'s PHP build does not provide openssl_pkey_derive(), which Web Push requires. PHP 7.3 or newer is needed.', 'ultimate-push-notifications' );
		}

		if ( ! \function_exists( 'random_bytes' ) ) {
			return \__( 'This server\'s PHP build does not provide random_bytes().', 'ultimate-push-notifications' );
		}

		if ( \function_exists( 'openssl_get_curve_names' ) && ! \in_array( self::CURVE, \openssl_get_curve_names(), true ) ) {
			return \__( 'This server\'s OpenSSL build does not support the P-256 (prime256v1) curve.', 'ultimate-push-notifications' );
		}

		// A trial run is the only check that means anything.
		$pair = self::generate_key_pair();
		if ( ! $pair ) {
			return \__( 'OpenSSL on this server was unable to derive an EC public key.', 'ultimate-push-notifications' );
		}

		return true;
	}

	/**
	 * Generate a P-256 key pair.
	 *
	 * The scalar is drawn from the CSPRNG and rejected if it falls outside
	 * [1, n-1] — a vanishingly rare event for a 256-bit draw against P-256's
	 * order, but a cheap comparison keeps the key valid by construction rather
	 * than by probability. See keygen_supported() for why openssl_pkey_new() is
	 * not used.
	 *
	 * @return array|false array{ public: string, private: string } as raw binary, or false.
	 */
	public static function generate_key_pair() {
		$order = \hex2bin( self::ORDER_HEX );
		$zero  = \str_repeat( "\0", self::FIELD_SIZE );

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			try {
				$scalar = \random_bytes( self::FIELD_SIZE );
			} catch ( \Exception $e ) {
				return false;
			}

			// Raw big-endian bytes compare in numeric order.
			if ( \strcmp( $scalar, $order ) >= 0 || $scalar === $zero ) {
				continue;
			}

			$point = self::derive_public_point( $scalar );
			if ( false === $point ) {
				return false;
			}

			return array(
				'public'  => $point,
				'private' => $scalar,
			);
		}

		return false;
	}

	/**
	 * Assemble an uncompressed point (0x04 || X || Y) from its coordinates.
	 *
	 * OpenSSL returns coordinates with leading zero bytes stripped, so they must
	 * be re-padded to the field size or the point is malformed.
	 *
	 * @param string $x
	 * @param string $y
	 * @return string 65 raw bytes.
	 */
	public static function point_from_xy( $x, $y ) {
		return "\x04" . self::pad_field( $x ) . self::pad_field( $y );
	}

	/**
	 * Left-pad a field element to 32 bytes.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function pad_field( $value ) {
		return \str_pad( $value, self::FIELD_SIZE, "\0", STR_PAD_LEFT );
	}

	/**
	 * Is this a well-formed uncompressed P-256 point?
	 *
	 * @param string $point
	 * @return bool
	 */
	public static function is_valid_point( $point ) {
		return \is_string( $point ) && 65 === \strlen( $point ) && "\x04" === $point[0];
	}

	/**
	 * Wrap a raw uncompressed point as an OpenSSL public key.
	 *
	 * @param string $point 65 raw bytes.
	 * @return resource|\OpenSSLAsymmetricKey|false
	 */
	public static function public_key_from_point( $point ) {
		if ( ! self::is_valid_point( $point ) ) {
			return false;
		}

		$der = \hex2bin( self::SPKI_PREFIX_HEX ) . $point;

		return @\openssl_pkey_get_public( self::pem( 'PUBLIC KEY', $der ) );
	}

	/**
	 * Build an OpenSSL private key from a raw 32-byte scalar.
	 *
	 * Encodes SEC1 ECPrivateKey (RFC 5915):
	 *
	 *   SEQUENCE {
	 *     INTEGER 1,
	 *     OCTET STRING (32) privateKey,
	 *     [0] { OID prime256v1 },
	 *     [1] { BIT STRING publicKey }
	 *   }
	 *
	 * The public point is required by the structure, so it is derived by the
	 * caller and passed in rather than recomputed here.
	 *
	 * @param string $scalar 32 raw bytes.
	 * @param string $point  65 raw bytes.
	 * @return resource|\OpenSSLAsymmetricKey|false
	 */
	public static function private_key_from_scalar( $scalar, $point ) {
		if ( ! \is_string( $scalar ) || self::FIELD_SIZE !== \strlen( $scalar ) ) {
			return false;
		}
		if ( ! self::is_valid_point( $point ) ) {
			return false;
		}

		$version    = "\x02\x01\x01";                                  // INTEGER 1
		$private    = "\x04" . \chr( self::FIELD_SIZE ) . $scalar;     // OCTET STRING
		$curve_oid  = \hex2bin( '06082a8648ce3d030107' );              // OID prime256v1
		$params     = "\xa0" . \chr( \strlen( $curve_oid ) ) . $curve_oid;

		$bit_string = "\x03" . \chr( \strlen( $point ) + 1 ) . "\x00" . $point;
		$public     = "\xa1" . \chr( \strlen( $bit_string ) ) . $bit_string;

		$body = $version . $private . $params . $public;
		$der  = "\x30" . self::der_length( \strlen( $body ) ) . $body;

		return @\openssl_pkey_get_private( self::pem( 'EC PRIVATE KEY', $der ) );
	}

	/**
	 * Derive the public point that belongs to a private scalar.
	 *
	 * The publicKey field of RFC 5915 ECPrivateKey is optional, and when it is
	 * omitted OpenSSL computes the point itself. That makes this an independent
	 * check: passing the claimed point in and comparing it back would only ever
	 * confirm what the caller already asserted.
	 *
	 * @param string $scalar 32 raw bytes.
	 * @return string|false 65 raw bytes, or false.
	 */
	public static function derive_public_point( $scalar ) {
		if ( ! \is_string( $scalar ) || self::FIELD_SIZE !== \strlen( $scalar ) ) {
			return false;
		}

		$curve_oid = \hex2bin( '06082a8648ce3d030107' );
		$body      = "\x02\x01\x01"                                       // INTEGER 1
			. "\x04" . \chr( self::FIELD_SIZE ) . $scalar                 // OCTET STRING privateKey
			. "\xa0" . \chr( \strlen( $curve_oid ) ) . $curve_oid;        // [0] parameters

		$der = "\x30" . self::der_length( \strlen( $body ) ) . $body;

		$key = @\openssl_pkey_get_private( self::pem( 'EC PRIVATE KEY', $der ) );
		if ( ! $key ) {
			return false;
		}

		$details = \openssl_pkey_get_details( $key );
		if ( ! $details || ! isset( $details['ec']['x'], $details['ec']['y'] ) ) {
			return false;
		}

		return self::point_from_xy( $details['ec']['x'], $details['ec']['y'] );
	}

	/**
	 * DER definite-length encoding.
	 *
	 * Only short form and one-byte long form are needed: every structure this
	 * class builds is well under 256 bytes.
	 *
	 * @param int $length
	 * @return string
	 */
	private static function der_length( $length ) {
		if ( $length < 128 ) {
			return \chr( $length );
		}
		return "\x81" . \chr( $length );
	}

	/**
	 * PEM-armour a DER blob.
	 *
	 * @param string $label
	 * @param string $der
	 * @return string
	 */
	private static function pem( $label, $der ) {
		return "-----BEGIN {$label}-----\n"
			. \chunk_split( \base64_encode( $der ), 64, "\n" )
			. "-----END {$label}-----\n";
	}

	/**
	 * Convert an ECDSA signature from OpenSSL's DER form to the fixed-width
	 * R || S form that JWS ES256 requires.
	 *
	 * @param string $der
	 * @return string|false 64 raw bytes, or false when the input is malformed.
	 */
	public static function der_signature_to_raw( $der ) {
		if ( ! \is_string( $der ) || \strlen( $der ) < 8 || "\x30" !== $der[0] ) {
			return false;
		}

		$offset = 2;
		// Skip the long-form length byte when present.
		if ( "\x81" === $der[1] ) {
			$offset = 3;
		}

		$read_integer = function ( $der, &$offset ) {
			if ( ! isset( $der[ $offset ] ) || "\x02" !== $der[ $offset ] ) {
				return false;
			}
			$offset++;
			$length = \ord( $der[ $offset ] );
			$offset++;
			$value = \substr( $der, $offset, $length );
			$offset += $length;

			// DER integers are signed, so a leading 0x00 may have been added.
			return \ltrim( $value, "\0" );
		};

		$r = $read_integer( $der, $offset );
		if ( false === $r ) {
			return false;
		}

		$s = $read_integer( $der, $offset );
		if ( false === $s ) {
			return false;
		}

		return self::pad_field( $r ) . self::pad_field( $s );
	}

}
