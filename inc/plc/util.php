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
function base32_encode($data, $padRight = false) {
	$dataSize = strlen($data);
	$res = '';
	$remainder = 0;
	$remainderSize = 0;

	for ($i = 0; $i < $dataSize; $i++)
	{
		$b = ord($data[$i]);
		$remainder = ($remainder << 8) | $b;
		$remainderSize += 8;
		while ($remainderSize > 4)
		{
			$remainderSize -= 5;
			$c = $remainder & (BASE32_BITS_5_RIGHT << $remainderSize);
			$c >>= $remainderSize;
			$res .= BASE32_CHARS[$c];
		}
	}
	if ($remainderSize > 0)
	{
		// remainderSize < 5:
		$remainder <<= (5 - $remainderSize);
		$c = $remainder & BASE32_BITS_5_RIGHT;
		$res .= BASE32_CHARS[$c];
	}
	if ($padRight)
	{
		$padSize = (8 - ceil(($dataSize % 5) * 8 / 5)) % 8;
		$res .= str_repeat('=', $padSize);
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
function base32_decode($data) {
	$data = rtrim($data, "=\x20\t\n\r\0\x0B");
	$dataSize = strlen($data);
	$buf = 0;
	$bufSize = 0;
	$res = '';
	$charMap = array_flip(str_split(BASE32_CHARS)); // char=>value map
	$charMap += array_flip(str_split(strtoupper(BASE32_CHARS))); // add upper-case alternatives

				continue; // ignore these safe characters.
		}
		$b = $charMap[$c];
		$buf = ($buf << 5) | $b;
		$bufSize += 5;
		if ($bufSize > 7)
		{
			$bufSize -= 8;
			$b = ($buf & (0xff << $bufSize)) >> $bufSize;
			$res .= chr($b);
		}
	}

	return $res;
}
