<?php
/**
 * PLC utilities.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC\Util;

use Exception;

const BASE32_BITS_5_RIGHT = 31;
const BASE32_CHARS = 'abcdefghijklmnopqrstuvwxyz234567';

/**
 * Encode a binary string into a base64url string.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc4648#section-5
 *
 * @param string $data The binary string to encode.
 * @return string The base64url encoded string.
 */
function base64url_encode( string $data ) : string {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Decode a base64url string into a binary string.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc4648#section-5
 *
 * @param string $data The base64url string to decode.
 * @return string The decoded binary string.
 */
function base64url_decode( string $data ) : string {
	$translated = strtr( $data, '-_', '+/' );
	$padded = str_pad( $translated, strlen( $data ) % 4, '=', STR_PAD_RIGHT );
	return base64_decode( $padded );
}

/**
 * Encode a binary string into a base32 string.
 *
 * @copyright 2016 Denis Borzenko
 * @license https://github.com/bbars/utils/blob/master/LICENSE MIT
 * @see https://github.com/bbars/utils
 *
 * @param string $data The data to encode.
 * @param bool   $pad_right whether to pad the encoded string with equals (=) characters.
 * @return string The encoded string.
 */
function base32_encode( $data, $pad_right = false ) {
	$data_size = strlen( $data );
	$res = '';
	$remainder = 0;
	$remainder_size = 0;

	for ( $i = 0; $i < $data_size; $i++ ) {
		$b = ord( $data[ $i ] );
		$remainder = ( $remainder << 8 ) | $b;
		$remainder_size += 8;
		while ( $remainder_size > 4 ) {
			$remainder_size -= 5;
			$c = $remainder & ( BASE32_BITS_5_RIGHT << $remainder_size );
			$c >>= $remainder_size;
			$res .= BASE32_CHARS[ $c ];
		}
	}
	if ( $remainder_size > 0 ) {
		// remainder_size < 5.
		$remainder <<= ( 5 - $remainder_size );
		$c = $remainder & BASE32_BITS_5_RIGHT;
		$res .= BASE32_CHARS[ $c ];
	}
	if ( $pad_right ) {
		$pad_size = ( 8 - ceil( ( $data_size % 5 ) * 8 / 5 ) ) % 8;
		$res .= str_repeat( '=', $pad_size );
	}
	return $res;
}

/**
 * Decode a binary string into a base32 string.
 *
 * @copyright 2016 Denis Borzenko
 * @license https://github.com/bbars/utils/blob/master/LICENSE MIT
 * @see https://github.com/bbars/utils
 *
 * @throws Exception If the encoded string contains an unsupported character.
 * @param string $data The encoded string.
 * @return string The decoded string.
 */
function base32_decode( $data ) {
	$data = rtrim( $data, "=\x20\t\n\r\0\x0B" );
	$data_size = strlen( $data );
	$buf = 0;
	$buf_size = 0;
	$res = '';
	$char_map = array_flip( str_split( BASE32_CHARS ) ); // char=>value map.
	$char_map += array_flip( str_split( strtoupper( BASE32_CHARS ) ) ); // add upper-case alternatives.

	for ( $i = 0; $i < $data_size; $i++ ) {
		$c = $data[ $i ];
		if ( ! isset( $char_map[ $c ] ) ) {
			if ( $c === ' ' || $c === "\r" || $c === "\n" || $c === "\t" ) {
				continue; // ignore these safe characters.
			}
			throw new Exception( 'Encoded string contains unexpected char #' . ord( $c ) . " at offset $i (using improper alphabet?)" );
		}
		$b = $char_map[ $c ];
		$buf = ( $buf << 5 ) | $b;
		$buf_size += 5;
		if ( $buf_size > 7 ) {
			$buf_size -= 8;
			$b = ( $buf & ( 0xff << $buf_size ) ) >> $buf_size;
			$res .= chr( $b );
		}
	}

	return $res;
}
