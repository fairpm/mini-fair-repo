<?php
/**
 * CID Tag.
 *
 * @package MiniFAIR
 */

// phpcs:disable HM.Files.NamespaceDirectoryName.NameMismatch -- Avoids a bug which detects strict_types as the namespace.

declare(strict_types=1);

namespace MiniFAIR\PLC;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Tag;
use InvalidArgumentException;
use YOCLIB\Multiformats\Multibase\Multibase;

/**
 * CIDTag class.
 */
final class CIDTag extends Tag {
	const TAG_CID = 42;

	/**
	 * Get the tag's ID.
	 *
	 * @return int
	 */
	public static function getTagId(): int {
		return self::TAG_CID;
	}

	/**
	 * Create a tag from loaded data.
	 *
	 * @param int        $additionalInformation Additional information.
	 * @param ?string    $data                  The tag data.
	 * @param CBORObject $object                The tag object.
	 * @return self
	 */
	public static function createFromLoadedData( int $additionalInformation, ?string $data, CBORObject $object ): Tag { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Inherited from parent.
		return new self( $additionalInformation, $data, $object ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Inherited from parent.
	}

	/**
	 * Create a tag.
	 *
	 * @throws InvalidArgumentException If the CID does not begin with 0x01 0x71 0x12.
	 * @param string $cid The ID.
	 * @return Tag
	 */
	public static function create( string $cid ): Tag {
		[ $ai, $data ] = self::determineComponents( self::TAG_CID );

		$decoded = Multibase::decode( $cid );

		// CID data begins with \x01\x71\x12.
		if ( ! str_starts_with( $decoded, "\x01\x71\x12" ) ) {
			throw new InvalidArgumentException( 'CID must start with 0x01 0x71 0x12' );
		}

		// Prefix with the "Multibase identity prefix" (0x00).
		$bytes = "\x00" . $decoded;
		return new self( $ai, $data, ByteStringObject::create( $bytes ) );
	}
}
