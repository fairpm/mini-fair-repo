<?php

namespace MiniFAIR\Keys;

use Elliptic\EC;
use Elliptic\EC\KeyPair;
use Elliptic\EC\Signature;
use Elliptic\Utils;
use Exception;
use YOCLIB\Multiformats\Multibase\Multibase;

class ECKey implements Key {
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
		return $this->keypair->getPrivate() !== null;
	}

	/**
	 * Convert a keypair object to a multibase public key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase public key string (starts with z).
	 */
	public function encode_public() : string {
		$pub = $this->keypair->getPublic( true, 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_K256 => bin2hex( PREFIX_CURVE_K256 ),
			CURVE_P256 => bin2hex( PREFIX_CURVE_P256 ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $pub ) );
		return $encoded;
	}

	/**
	 * Convert a keypair object to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_private() : string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot encode private key for a public key' );
		}

		$priv = $this->keypair->getPrivate( 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_K256 => bin2hex( PREFIX_CURVE_K256_PRIVATE ),
			CURVE_P256 => bin2hex( PREFIX_CURVE_P256_PRIVATE ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $priv ));
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

		$priv = $this->keypair->getPrivate( 'hex' );
		$prefix = match ( $this->curve ) {
			CURVE_K256 => bin2hex( PREFIX_CURVE_K256 ),
			CURVE_P256 => bin2hex( PREFIX_CURVE_P256 ),
			default => throw new Exception( 'Unsupported curve' ),
		};
		$encoded = Multibase::encode( Multibase::BASE58BTC, hex2bin( $prefix . $priv ));
		return $encoded;
	}

	/**
	 * Convert a signature to compact (IEEE-P1363) representation.
	 *
	 * (Equivalent to secp256k1_ecdsa_sign_compact().)
	 *
	 * @internal Elliptic does not support compact signatures, only DER-encoded, so
	 *           we need to do it ourselves. Compact signatures are just the r and
	 *           s bytes concatenated, but must be padded to 32 bytes each.
	 *
	 * @param EC $ec The elliptic curve object.
	 * @param Signature $signature The signature object.
	 * @return string The compact signature.
	 */
	protected function signature_to_compact( EC $ec, Signature $signature ) : string {
		$byte_length = ceil( $ec->curve->n->bitLength() / 8 );
		$compact = Utils::toHex( $signature->r->toArray( 'be', $byte_length ) ) . Utils::toHex( $signature->s->toArray( 'be', $byte_length ) );
		return $compact;
	}

	/**
	 * Sign data using the private key.
	 *
	 * @param string $data The data to sign, as a hex-encoded string.
	 * @return string The signature encoded as a binary string.
	 */
	public function sign( string $data ) : string {
		if ( ! $this->is_private() ) {
			throw new Exception( 'Cannot sign with a public key' );
		}

		/**
		 * Hash with SHA-256, then sign, using canonical (low-S) form.
		 *
		 * @var \Elliptic\EC\Signature
		 */
		$signature = $this->keypair->sign( $data, 'hex', [
			'canonical' => true
		] );

		// Convert to compact (IEEE-P1363) form.
		// todo: do we need to do this for p256 too?
		if ( $this->curve === CURVE_K256 ) {
			return $this->signature_to_compact( $this->keypair->ec, $signature );
		}
		return $signature->toDER( 'hex' );
	}

	/**
	 * Generate a new keypair.
	 *
	 * We use NIST K-256 as the default to match ATProto.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @return static The generated keypair object.
	 */
	public static function generate( string $curve ) : static {
		$ec = new EC( $curve );
		return new static( $ec->genKeyPair(), $curve );
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
			PREFIX_CURVE_P256 => CURVE_P256,
			PREFIX_CURVE_K256 => CURVE_K256,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$ec = new EC( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair = $ec->keyFromPublic( $stripped, 'hex' );
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
			PREFIX_CURVE_P256_PRIVATE => CURVE_P256,
			PREFIX_CURVE_K256_PRIVATE => CURVE_K256,

			// todo: Legacy, remove this later.
			PREFIX_CURVE_P256 => CURVE_P256,
			PREFIX_CURVE_K256 => CURVE_K256,
			default => throw new Exception( 'Unsupported curve' ),
		};

		$ec = new EC( $curve );

		$stripped = bin2hex( substr( $decoded, 2 ) );
		$keypair = $ec->keyFromPrivate( $stripped, 'hex' );
		return new static( $keypair, $curve );
	}
}
