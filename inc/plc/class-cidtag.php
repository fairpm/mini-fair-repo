<?php
/**
 * CID Tag.
 *
 * @package MiniFAIR
 */

declare(strict_types=1);

namespace MiniFAIR\PLC;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Tag;
use YOCLIB\Multiformats\Multibase\Multibase;

final class CIDTag extends Tag {
	const TAG_CID = 42;

	public static function getTagId(): int {
		return self::TAG_CID;
	}

	public static function createFromLoadedData( int $additionalInformation, ?string $data, CBORObject $object ): Tag
	{
		return new self( $additionalInformation, $data, $object );
	}

	public static function create( string $cid ): Tag
	{
		[ $ai, $data ] = self::determineComponents( self::TAG_CID );

		$decoded = Multibase::decode( $cid );

		// CID data begins with \x01\x71\x12
		if ( ! str_starts_with( $decoded, "\x01\x71\x12" ) ) {
			throw new \InvalidArgumentException( 'CID must start with 0x01 0x71 0x12' );
		}

		// Prefix with the "Multibase identity prefix" (0x00)
		$bytes = "\x00" . $decoded;
		return new self( $ai, $data, ByteStringObject::create( $bytes ) );
	}
}
