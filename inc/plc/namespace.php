<?php

namespace MiniFAIR\PLC;

use CBOR\{
	ListObject,
	MapItem,
	TextStringObject,
};
use CBOR\OtherObject\NullObject;
use Elliptic\EC;
use Elliptic\EC\KeyPair;
use Elliptic\EC\Signature;
use Elliptic\Utils;
use MiniFAIR\Admin;
use MiniFAIR\Keys;
use Exception;
use WP_CLI;
use YOCLIB\Multiformats\Multibase\Multibase;

const VERIFICATION_METHOD_ID = 'fairpm';

function bootstrap() {
	add_action( 'init', __NAMESPACE__ . '\\register_types' );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'plc', Command::class );
	}
}

function register_types() {
	register_post_type( DID::POST_TYPE, [
		'public' => true,
		'show_ui' => true,
		'show_in_menu' => Admin\PAGE_SLUG,
		'supports' => [ 'title', 'editor', 'custom-fields' ],
		'label' => __( 'PLC DIDs', 'minifair' ),
		'labels' => [
			'menu_name' => __( 'PLC DIDs', 'minifair' ),
			'singular_name' => __( 'PLC DID', 'minifair' ),
			'add_new_item' => __( 'Add New PLC DID', 'minifair' ),
			'edit_item' => __( 'Edit PLC DID', 'minifair' ),
			'all_items' => __( 'All PLC DIDs', 'minifair' ),
			'search_items' => __( 'Search PLC DIDs', 'minifair' ),
			'not_found' => __( 'No PLC DIDs found', 'minifair' ),
			'not_found_in_trash' => __( 'No PLC DIDs found in Trash', 'minifair' ),
		],
	] );
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
function signature_to_compact( EC $ec, Signature $signature ) : string {
	$byte_length = ceil( $ec->curve->n->bitLength() / 8);
	$compact = Utils::toHex( $signature->r->toArray( 'be', $byte_length ) ) . Utils::toHex( $signature->s->toArray( 'be', $byte_length ) );
	return $compact;
}

/**
 * Sign an operation.
 *
 * @param Operation $data The operation to sign.
 * @param KeyPair $signing_key The signing key.
 * @return SignedOperation The signed operation.
 */
function sign_operation( Operation $data, KeyPair $signing_key ) : SignedOperation {
	// Validate the operation.
	$data->validate();

	// Encode the operation into DAG-CBOR.
	$encoded = encode_operation( $data );

	/**
	 * Hash with SHA-256, then sign, using canonical (low-S) form.
	 *
	 * @var \Elliptic\EC\Signature
	 */
	$signature = $signing_key->sign( hash( 'sha256', $encoded, false ), 'hex', [
		'canonical' => true
	] );

	// Convert to compact (IEEE-P1363) form, then to base64url.
	$sig_string = Util\base64url_encode( hex2bin( signature_to_compact( $signing_key->ec, $signature ) ) );

	return new SignedOperation( $data, $sig_string );
}

/**
 * Get the (unprefixed) PLC ID for the genesis operation.
 *
 * This is the first 24 characters of the encoded hash.
 *
 * @param SignedOperation $data The signed operation.
 * @return string The PLC ID.
 */
function genesis_to_plc( SignedOperation $data ) : string {
	$encoded = encode_operation( $data );
	$hash = hash( 'sha256', $encoded, true );
	$encoded = Util\base32_encode( $hash );
	return substr( $encoded, 0, 24 );
}

/**
 * Encode a PLC operation.
 *
 * Encodes the operation using the DAG-CBOR format.
 *
 * @see https://web.plc.directory/spec/v0.1/did-plc
 *
 * @param Operation $data The operation to encode.
 * @return string The encoded operation.
 */
function encode_operation( Operation $data ) : string {
	$operation = CanonicalMapObject::create()
		->add(
			TextStringObject::create( 'type' ),
			TextStringObject::create( $data->type )
		)
		->add(
			TextStringObject::create( 'rotationKeys' ),
			ListObject::create( array_map( fn ( KeyPair $key ) => TextStringObject::create( Keys\encode_did_key( $key, Keys\CURVE_K256 ) ), $data->rotationKeys ) )
		)
		->add(
			TextStringObject::create( 'verificationMethods' ),
			CanonicalMapObject::create( array_map( fn ( string $key, KeyPair $value ) => MapItem::create(
				TextStringObject::create( $key ),
				TextStringObject::create( Keys\encode_did_key( $value, Keys\CURVE_K256 ) )
			), array_keys( $data->verificationMethods ), $data->verificationMethods ) )
		);

	$operation->add(
		TextStringObject::create( 'alsoKnownAs' ),
		ListObject::create( array_map( fn ( $key ) => TextStringObject::create( $key ), $data->alsoKnownAs ) )
	);

	$operation->add(
		TextStringObject::create( 'services' ),
		CanonicalMapObject::create( array_map( fn ( $key, $service ) => MapItem::create(
			TextStringObject::create( $key ),
			CanonicalMapObject::create()
				->add( TextStringObject::create( 'endpoint' ), TextStringObject::create( $service['endpoint'] ) )
				->add( TextStringObject::create( 'type' ), TextStringObject::create( $service['type'] ) )
		), array_keys( $data->services ), $data->services ) )
	);

	if ( ! empty( $data->prev ) ) {
		$operation->add(
			TextStringObject::create( 'prev' ),
			TextStringObject::create( $data->prev )
		);
	} else {
		$operation->add(
			TextStringObject::create( 'prev' ),
			NullObject::create()
		);
	}

	if ( $data instanceof SignedOperation ) {
		$operation->add(
			TextStringObject::create( 'sig' ),
			TextStringObject::create( $data->sig )
		);
	}

	return (string) $operation;
}

/**
 * Generate a CID for a signed operation object.
 *
 * CIDs are used for referencing prior operations, which are always signed.
 *
 * Per the PLC spec, we encode with the following parameters:
 * - CIDv1 (code: 0x01)
 * - dag-cbor multibase type (code: 0x71)
 * - sha-256 multihash (code: 0x12)
 *
 * @see https://web.plc.directory/spec/v0.1/did-plc
 * @see https://cid.ipfs.tech/
 * @param SignedOperation $op The signed operation to encode.
 * @return string The CID for the operation.
 */
function cid_for_operation( SignedOperation $op ) : string {
	$cbor = encode_operation( $op );
	$hash = hash( 'sha256', $cbor, true );

	// The bit layout for CIDs is:
	// Version (CIDv1 = 0x01)
	$cid = "\x01";

	// Type (dag-cbor = 0x71)
	$cid .= "\x71";

	// Multihash type (sha-256 = 0x12)
	$cid .= "\x12"; // sha-256

	// Multihash length
	$cid .= pack( 'C', strlen( $hash ) );

	// Hash digest
	$cid .= $hash;

	// Then, encode to base32 multibase.
	$cid = Multibase::encode( Multibase::BASE32, $cid );
	return $cid;
}
