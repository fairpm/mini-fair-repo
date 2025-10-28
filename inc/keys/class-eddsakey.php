<?php
/**
 * EdDSA Key.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\Keys;

use Elliptic\EdDSA;
use Elliptic\EdDSA\KeyPair;
use Exception;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * EdDSAKey class.
 */
class EdDSAKey implements Key {
	/**
	 * Constructor.
	 *
	 * @param KeyPair $keypair The keypair.
	 * @param string  $curve   The curve.
	 * @return void
	 */
	public function __construct(
		protected KeyPair $keypair,
		protected string $curve
	) {
	}

	/**
	 * Does this key represent a private key?
	 *
	 * @return bool True if the key is a private keypair, false if it is a public key.
	 */
	public function is_private() : bool {
		return $this->keypair->secret() !== null;
	}

	/**
	 * Sign data using the private key.
	 *
	 * @throws Exception If the key is public.
	 * @param string $data The data to sign, as a hex-encoded string.
	 * @return string The signature encoded as a hex-encoded string.
	 */
	public function sign( string $data ) : string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot sign with a public key' );
		}

		return $this->keypair->sign( $data )->toHex();
	}

	/**
	 * Convert a key to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_public() : string {
		$pub = $this->keypair->getPublic( 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_ED25519 => bin2hex( PREFIX_CURVE_ED25519 ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $pub ) );
		return $encoded;
	}

	/**
	 * Convert a key to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_private() : string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot encode private key for a public key' );
		}

		$priv = $this->keypair->getSecret( 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_ED25519 => bin2hex( PREFIX_CURVE_ED25519_PRIVATE ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $priv ) );
		return $encoded;
	}

	/**
	 * Convert a key to an incorrectly-encoded key string.
	 *
	 * Used only for revocation.
	 *
	 * @throws Exception If the curve is not supported.
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_private_legacy_do_not_use_or_you_will_be_fired() : string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot encode private key for a public key' );
		}

		$priv = $this->keypair->getSecret( 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_ED25519 => bin2hex( PREFIX_CURVE_ED25519 ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $priv ) );
		return $encoded;
	}

	/**
	 * Generate a new key.
	 *
	 * @param string $curve The curve.
	 * @return static A new instance of the key.
	 */
	public static function generate( string $curve ) : static {
		// Generate a random keypair.
		$key = sodium_crypto_sign_keypair();
		$secret = sodium_crypto_sign_secretkey( $key );

		// Convert to KeyPair object.
		$ed = new EdDSA( $curve );
		$key = $ed->keyFromSecret( bin2hex( $secret ) );
		return new static( $key, $curve );
	}

	/**
	 * Convert a multibase public key string to a keypair object.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @param string $key The multibase public key string (starts with z).
	 * @return static The key object.
	 */
	public static function from_public( string $key ) : static {
		$decoded = Multibase::decode( $key );

		$curve = match ( substr( $decoded, 0, 2 ) ) {
			PREFIX_CURVE_ED25519 => CURVE_ED25519,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$eddsa = new EdDSA( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair = $eddsa->keyFromPublic( $stripped, 'hex' );
		return new static( $keypair, $curve );
	}

	/**
	 * Convert a multibase private key string to a keypair object.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @param string $key The multibase public key string (starts with z).
	 * @return static The key object.
	 */
	public static function from_private( string $key ) : static {
		$decoded = Multibase::decode( $key );

		$curve = match ( substr( $decoded, 0, 2 ) ) {
			PREFIX_CURVE_ED25519_PRIVATE => CURVE_ED25519,

			// todo: Legacy, remove this later.
			PREFIX_CURVE_ED25519 => CURVE_ED25519,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$eddsa = new EdDSA( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair = $eddsa->keyFromSecret( $stripped, 'hex' );
		return new static( $keypair, $curve );
	}
}
