<?php
/**
 * PLC namespace.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use CBOR\OtherObject\NullObject;
use CBOR\{
	ListObject,
	MapItem,
	TextStringObject,
};
use MiniFAIR\Admin;
use MiniFAIR\Keys;
use MiniFAIR\Keys\Key;
use WP_CLI;
use YOCLIB\Multiformats\Multibase\Multibase;

const VERIFICATION_METHOD_PREFIX = 'fair_';

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap() : void {
	add_action( 'init', __NAMESPACE__ . '\\register_types' );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'plc', Command::class );
	}
}

/**
 * Register DID post types.
 *
 * @return void
 */
function register_types() : void {
	register_post_type( DID::POST_TYPE, [
		'public' => true,
		'show_ui' => true,
		'show_in_menu' => Admin\PAGE_SLUG,
		'show_in_rest' => false,
		// 'can_export' => false,
		'supports' => [ 'title', 'editor', 'custom-fields' ],
		'label' => __( 'PLC DIDs', 'mini-fair' ),
		'labels' => [
			'menu_name' => __( 'PLC DIDs', 'mini-fair' ),
			'singular_name' => __( 'PLC DID', 'mini-fair' ),
			'add_new_item' => __( 'Add New PLC DID', 'mini-fair' ),
			'edit_item' => __( 'Edit PLC DID', 'mini-fair' ),
			'all_items' => __( 'All PLC DIDs', 'mini-fair' ),
			'search_items' => __( 'Search PLC DIDs', 'mini-fair' ),
			'not_found' => __( 'No PLC DIDs found', 'mini-fair' ),
			'not_found_in_trash' => __( 'No PLC DIDs found in Trash', 'mini-fair' ),
		],
	] );
}

/**
 * Sign an operation.
 *
 * @param Operation $data The operation to sign.
 * @param Key       $key  The signing key.
 * @return SignedOperation The signed operation.
 */
function sign_operation( Operation $data, Key $key ) : SignedOperation {
	// Validate the operation.
	$data->validate();

	// Encode the operation into DAG-CBOR.
	$encoded = encode_operation( $data );

	// Sign the hash of the data.
	$signature = $key->sign( hash( 'sha256', $encoded, false ) );

	// Convert to base64url.
	$sig_string = Util\base64url_encode( hex2bin( $signature ) );

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
			ListObject::create( array_map( fn ( Key $key ) => TextStringObject::create( Keys\encode_did_key( $key ) ), $data->rotationKeys ) )
		)
		->add(
			TextStringObject::create( 'verificationMethods' ),
			CanonicalMapObject::create( array_map( fn ( string $key, Key $value ) => MapItem::create(
				TextStringObject::create( $key ),
				TextStringObject::create( Keys\encode_did_key( $value ) )
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
	// Version (CIDv1 = 0x01).
	$cid = "\x01";

	// Type (dag-cbor = 0x71).
	$cid .= "\x71";

	// Multihash type (sha-256 = 0x12).
	$cid .= "\x12"; // sha-256.

	// Multihash length.
	$cid .= pack( 'C', strlen( $hash ) );

	// Hash digest.
	$cid .= $hash;

	// Then, encode to base32 multibase.
	$cid = Multibase::encode( Multibase::BASE32, $cid );
	return $cid;
}

/**
 * Convert an operation to an expected DID Document.
 *
 * Used to predict the output DID document after an operation is performed.
 * This should only be used for informational purposes, such as displaying an
 * indicative diff to a user.
 *
 * @param string $id DID ID.
 * @param Operation $op The operation to convert.
 * @return array The DID Document representation of the operation.
 */
function operation_to_did_document( string $id, Operation $op ) : array {
	$doc = [
		'id' => $id,
		'alsoKnownAs' => $op->alsoKnownAs,
		'verificationMethod' => [],
		'service' => [],
	];

	foreach ( $op->verificationMethods as $key => $value ) {
		if ( ! empty( $value ) ) {
			$doc['verificationMethod'][] = [
				'id' => sprintf( '%s#%s', $id, $key ),
				'type' => 'Multikey',
				'controller' => $id,
				'publicKeyMultibase' => $value->encode_public(),
			];
		}
	}

	foreach ( $op->services as $key => $service ) {
		if ( ! empty( $service['endpoint'] ) && ! empty( $service['type'] ) ) {
			$doc['service'][] = [
				'id' => sprintf( '#%s', $key ),
				'type' => $service['type'],
				'serviceEndpoint' => $service['endpoint'],
			];
		}
	}

	return $doc;
}
