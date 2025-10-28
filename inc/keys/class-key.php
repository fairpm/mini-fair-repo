<?php
/**
 * Key.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\Keys;

/**
 * Key interface.
 */
interface Key {
	/**
	 * Does this key represent a private key?
	 *
	 * @return bool True if the key is a private keypair, false if it is a public key.
	 */
	public function is_private() : bool;

	/**
	 * Sign data using the private key.
	 *
	 * @param string $data The data to sign, as a hex-encoded string.
	 * @return string The signature encoded as a hex-encoded string.
	 */
	public function sign( string $data ) : string;

	/**
	 * Convert a key to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_public() : string;

	/**
	 * Convert a key to a multibase private key string.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @return string The multibase private key string (starts with z).
	 */
	public function encode_private() : string;

	/**
	 * Convert a multibase public key string to a key.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @param string $key The multibase public key string (starts with z).
	 * @return Key The key object.
	 */
	public static function from_public( string $key ) : static;

	/**
	 * Convert a multibase private key string to a key.
	 *
	 * @see https://atproto.com/specs/cryptography
	 *
	 * @throws Exception If the curve is not supported.
	 * @param string $key The multibase public key string (starts with z).
	 * @return Key The key object.
	 */
	public static function from_private( string $key ) : static;
}
