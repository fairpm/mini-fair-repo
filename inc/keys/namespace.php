<?php

namespace MiniFAIR\Keys;

use Exception;
use Elliptic\EC;
use Elliptic\EC\KeyPair;
use YOCLIB\Multiformats\Multibase\Multibase;

const CURVE_K256 = 'secp256k1';
const CURVE_P256 = 'p256';
const CURVE_ED25519 = 'ed25519';

// From https://github.com/multiformats/multicodec/blob/master/table.csv:
// 0xe7 0x01 = varint( 0xe7 ) = 231 = secp256k1-pub
// 0x80 0x24 = varint( 0x80 ) = 128 = p256-pub
// 0xed 0x01 = varint( 0xed ) = 237 = ed25519-pub
// 0x81 0x26 = varint( 0x1301 ) = 4865 = secp256k1-priv
// 0x86 0x26 = varint( 0x1306 ) = 4870 = p256-priv
// 0x80 0x26 = varint( 0x1300 ) = 4864 = ed25519-priv
const PREFIX_CURVE_K256 = "\xe7\x01";
const PREFIX_CURVE_K256_PRIVATE = "\x81\x26";
const PREFIX_CURVE_P256 = "\x80\x24";
const PREFIX_CURVE_P256_PRIVATE = "\x06\x26";

// https://www.w3.org/TR/vc-di-eddsa/#ed25519verificationkey2020
const PREFIX_CURVE_ED25519 = "\xed\x01";
const PREFIX_CURVE_ED25519_PRIVATE = "\x80\x26"; // 0x1300

/**
 * Convert a multibase public key string to a keypair object.
 *
 * @see https://atproto.com/specs/cryptography
 *
 * @throws Exception If the curve is not supported.
 * @param string $key The multibase public key string (starts with z).
 * @return Key The key object.
 */
function decode_public_key( string $key ) : Key {
	static $cache = [];
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$decoded = Multibase::decode( $key );

	$keypair = match ( substr( $decoded, 0, 2 ) ) {
		PREFIX_CURVE_P256    => ECKey::from_public( $key ),
		PREFIX_CURVE_K256    => ECKey::from_public( $key ),
		PREFIX_CURVE_ED25519 => EdDSAKey::from_public( $key ),
		default => throw new Exception( 'Unsupported curve' ),
	};
	$cache[ $key ] = $keypair;
	return $keypair;
}

/**
 * Convert a multibase private key string to a keypair object.
 *
 * @see https://atproto.com/specs/cryptography
 *
 * @throws Exception If the curve is not supported.
 * @param string $key The multibase public key string (starts with z).
 * @return Key The key object.
 */
function decode_private_key( string $key ) : Key {
	static $cache = [];
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$decoded = Multibase::decode( $key );

	$keypair = match ( substr( $decoded, 0, 2 ) ) {
		PREFIX_CURVE_P256_PRIVATE => ECKey::from_private( $key ),
		PREFIX_CURVE_K256_PRIVATE => ECKey::from_private( $key ),
		PREFIX_CURVE_ED25519_PRIVATE => EdDSAKey::from_private( $key ),

		// todo: Legacy, remove this later.
		PREFIX_CURVE_P256    => ECKey::from_private( $key ),
		PREFIX_CURVE_K256    => ECKey::from_private( $key ),
		PREFIX_CURVE_ED25519 => EdDSAKey::from_private( $key ),
		default => throw new Exception( 'Unsupported curve' ),
	};
	$cache[ $key ] = $keypair;
	return $keypair;
}

/**
 * Decode a did:key: string to a keypair object.
 *
 * @throws Exception If the did:key: string is invalid.
 * @param string $did The did:key: string.
 * @return Key The key object.
 */
function decode_did_key( string $did ) : Key {
	if ( ! str_starts_with( $did, 'did:key:' ) ) {
		throw new Exception( 'Invalid DID format' );
	}
	$did = substr( $did, 8 );
	if ( ! str_starts_with( $did, 'z' ) ) {
		throw new Exception( 'Invalid DID format' );
	}

	return decode_public_key( $did );
}

/**
 * Convert a keypair object to a did:key: string.
 *
 * @throws Exception If the curve is not supported.
 * @param Key $key The keypair object.
 * @param string $curve The curve to use (CURVE_K256 or CURVE_P256).
 * @return string The did:key: string.
 */
function encode_did_key( Key $key ) : string {
	return 'did:key:' . $key->encode_public();
}
