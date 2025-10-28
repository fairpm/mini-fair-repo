<?php
/**
 * DID.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use Exception;
use MiniFAIR\API;
use MiniFAIR\Keys;
use MiniFAIR\Keys\Key;
use WP_Post;

/**
 * DID class.
 */
class DID {
	const DIRECTORY_API = 'https://plc.directory';

	const POST_TYPE = 'plc_did';
	const META_DID = 'plc_did';
	const META_ROTATION_KEYS = 'plc_did_rotation_keys';
	const META_VERIFICATION_KEYS = 'plc_did_verification_keys';

	/**
	 * The ID.
	 *
	 * @var string
	 */
	public readonly string $id;

	/**
	 * The internal ID.
	 *
	 * @var ?int
	 */
	protected ?int $internal_id = null;

	/**
	 * Rotation keys.
	 *
	 * These keys are used to manage the PLC entry itself.
	 *
	 * @var string[]
	 */
	protected array $rotation_keys = [];

	/**
	 * Verification keys.
	 *
	 * These keys are used to verify content belonging to the DID.
	 *
	 * @var string[]
	 */
	protected array $verification_keys = [];

	/**
	 * Hash of previous operation, in CID format.
	 *
	 * @var ?string
	 */
	protected ?string $prev = null;

	/**
	 * Get the rotation keys.
	 *
	 * @return Keys\ECKey[]
	 */
	public function get_rotation_keys() : array {
		return array_map( fn ( $key ) => Keys\decode_private_key( $key ), $this->rotation_keys );
	}

	/**
	 * Get the verification keys.
	 *
	 * @return Keys\EdDSAKey[]
	 */
	public function get_verification_keys() : array {
		return array_map( fn ( $key ) => Keys\decode_private_key( $key ), $this->verification_keys );
	}

	/**
	 * Invalidate a verification key.
	 *
	 * Note that update() must be called to persist the change.
	 *
	 * @param Key $key The key to invalidate.
	 * @return bool True if the key was invalidated, false if it was not found.
	 */
	public function invalidate_verification_key( Key $key ) {
		$encoded = $key->encode_private();

		if ( ! in_array( $encoded, $this->verification_keys, true ) ) {
			// Check for legacy-encoded keys too.
			if ( method_exists( $key, 'encode_private_legacy_do_not_use_or_you_will_be_fired' ) ) {
				$legacy_encoded = $key->encode_private_legacy_do_not_use_or_you_will_be_fired();
				if ( ! in_array( $legacy_encoded, $this->verification_keys, true ) ) {
					return false;
				}
				$encoded = $legacy_encoded;
			} else {
				return false;
			}
		}

		$this->verification_keys = array_values( array_filter( $this->verification_keys, fn ( $k ) => $k !== $encoded ) );
		return true;
	}

	/**
	 * Generate a new verification key.
	 *
	 * Creates a new EdDSA (Ed25519) keypair, and adds it to key list.
	 *
	 * Note that update() must be called to persist the change.
	 *
	 * @return Key The generated key.
	 */
	public function generate_verification_key() : Key {
		$key = Keys\EdDSAKey::generate( Keys\CURVE_ED25519 );
		$this->verification_keys[] = $key->encode_private();
		return $key;
	}

	/**
	 * Get the internal post ID for this DID.
	 *
	 * Only use this if you absolutely need it.
	 *
	 * @return int|null
	 */
	public function get_internal_post_id() : ?int {
		return $this->internal_id;
	}

	/**
	 * Save the DID.
	 *
	 * @return void
	 */
	public function save() {
		// If we don't have an internal ID, we need to create a new DID.
		if ( ! $this->internal_id ) {
			$this->create_post();
		}

		update_post_meta( $this->internal_id, self::META_DID, $this->id ?? null );

		update_post_meta( $this->internal_id, self::META_ROTATION_KEYS, $this->rotation_keys );
		update_post_meta( $this->internal_id, self::META_VERIFICATION_KEYS, $this->verification_keys );
	}

	/**
	 * Create a post for the DID.
	 *
	 * @return int
	 */
	protected function create_post() {
		$id = wp_insert_post( [
			'post_type' => self::POST_TYPE,
			'post_title' => $this->id ?? 'unknown',
			'post_name' => str_replace( 'did:plc:', '', $this->id ),
			'post_status' => 'publish',
		] );
		$this->internal_id = $id;
		return $id;
	}

	/**
	 * Perform an operation.
	 *
	 * @throws Exception If the operation's response is a WP_Error.
	 * @throws Exception If the operation's response code is not 200.
	 * @param SignedOperation $op The operation to perform.
	 * @return true
	 */
	protected function perform_operation( SignedOperation $op ) {
		// Ensure the operation is valid.
		$op->validate();

		$url = sprintf( '%s/%s', static::DIRECTORY_API, $this->id );
		$opts = [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( $op ),
		];

		$response = wp_remote_post( $url, $opts );
		if ( is_wp_error( $response ) ) {
			var_dump( $response );
			throw new Exception( 'Error performing operation: ' . $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			var_dump( $response );
			throw new Exception( 'Error performing operation: ' . wp_remote_retrieve_body( $response ) );
		}

		return true;
	}

	/**
	 * Update a DID.
	 *
	 * @return ?true True if the operation was performed, otherwise null.
	 */
	public function update() {
		$op = $this->prepare_update_op();
		if ( ! $op ) {
			var_dump( 'No changes to update' );
			return;
		}

		// Perform the operation.
		return $this->perform_operation( $op );
	}

	/**
	 * Get the expected changes to a DID document.
	 *
	 * @return array
	 */
	public function get_expected_document() : array {
		$op = $this->prepare_update_op();
		if ( ! $op ) {
			$op = $this->fetch_last_op();
		}

		// Convert the operation to a document.
		return operation_to_did_document( $this->id, $op );
	}

	/**
	 * Prepare the verification keys for the operation.
	 *
	 * Generates a unique ID for each key, using its hash.
	 *
	 * @return array
	 */
	protected function get_verification_keys_for_op() : array {
		$verification_keys = [];
		foreach ( $this->get_verification_keys() as $key ) {
			$key_id = substr( hash( 'sha256', $key->encode_public() ), 0, 6 );
			$verification_keys[ 'fair_' . $key_id ] = $key;
		}
		return $verification_keys;
	}

	/**
	 * Prepare the update operation.
	 *
	 * @return ?SignedOperation
	 */
	protected function prepare_update_op() : ?SignedOperation {
		// Fetch the previous op.
		$last_op = $this->fetch_last_op();

		// Get it as a CID.
		$last_cid = cid_for_operation( $last_op );

		// Merge prior data with current data.
		$update_unsigned = new Operation(
			type: 'plc_operation',
			rotationKeys: $this->get_rotation_keys(),
			verificationMethods: $this->get_verification_keys_for_op(),
			alsoKnownAs: $last_op->alsoKnownAs,
			services: [
				'fairpm_repo' => [
					'endpoint' => rest_url( API\REST_NAMESPACE . '/packages/' . $this->id ),
					'type' => 'FairPackageManagementRepo',
				],
			],
			prev: $last_cid,
		);

		// Check if we have any differences.
		if (
			$update_unsigned->rotationKeys === $last_op->rotationKeys
			&& $update_unsigned->verificationMethods === $last_op->verificationMethods
			&& $update_unsigned->alsoKnownAs === $last_op->alsoKnownAs
			&& $update_unsigned->services === $last_op->services
		) {
			// No changes, no need to update.
			return null;
		}

		// Sign it using our key.
		$update_signed = $update_unsigned->sign( $this->get_rotation_keys()[0] );

		return $update_signed;
	}

	/**
	 * Fetch the last operation on the DID.
	 *
	 * This is used to build the `prev` when we're running updates.
	 *
	 * @internal This is intentionally uncached, as need the latest data for the DID.
	 * @throws Exception If the response is a WP_Error.
	 * @throws Exception If the response's body is invalid JSON.
	 * @return Operation
	 */
	public function fetch_last_op() : Operation {
		$url = sprintf( '%s/%s/log/last', static::DIRECTORY_API, $this->id );
		$response = wp_remote_get( $url, [
			'headers' => [
				'Accept' => 'application/did+ld+json',
			],
		] );
		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Error fetching last op: ' . $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Error decoding last op: ' . json_last_error_msg() );
		}

		// Convert the last op into an Operation.
		$last_op = new Operation(
			type: $data['type'],
			rotationKeys: array_map( fn ( $key ) => Keys\decode_did_key( $key ), $data['rotationKeys'] ),
			verificationMethods: array_map( fn ( $key ) => Keys\decode_did_key( $key ), $data['verificationMethods'] ),
			alsoKnownAs: $data['alsoKnownAs'],
			services: $data['services'],
			prev: $data['prev'],
		);
		$last_op_signed = new SignedOperation(
			$last_op,
			$data['sig'],
		);
		return $last_op_signed;
	}

	/**
	 * Fetch the audit log for this DID.
	 *
	 * @internal This is intentionally uncached, as need the latest data for the DID.
	 * @return array|WP_Error
	 */
	public function fetch_audit_log() {
		$url = sprintf( '%s/%s/log/audit', static::DIRECTORY_API, $this->id );
		$response = wp_remote_get( $url, [
			'headers' => [
				'Accept' => 'application/did+ld+json',
			],
		] );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		return $data;
	}

	/**
	 * Check if the DID has been published.
	 *
	 * @internal This is intentionally uncached, as need the latest data for the DID.
	 * @return bool|WP_Error
	 */
	public function is_published() {
		$url = sprintf( 'https://plc.directory/%s', $this->id );
		$response = wp_remote_get( $url, [
			'headers' => [
				'Accept' => 'application/did+ld+json',
			],
		] );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// 404 = not found
		// 410 = gone (tombstone)
		$status = wp_remote_retrieve_response_code( $response );
		return $status === 200;
	}

	/**
	 * Has this DID been registered?
	 *
	 * @var bool
	 */
	protected bool $created = false;

	/**
	 * Get a DID document.
	 *
	 * @param string $id The DID.
	 * @return ?self
	 */
	public static function get( string $id ) {
		$did = new self();
		$did->id = $id;

		// Check if the DID exists in the database.
		$post = get_page_by_path( str_replace( 'did:plc:', '', $id ), OBJECT, self::POST_TYPE );
		if ( ! $post ) {
			return null;
		}

		return self::from_post( $post );
	}

	/**
	 * Get a DID from a post object.
	 *
	 * @param WP_Post $post The post object.
	 * @return self
	 */
	public static function from_post( WP_Post $post ) {
		$did = new self();
		$did->internal_id = $post->ID;
		$did->id = get_post_meta( $post->ID, self::META_DID, true );
		$did->rotation_keys = get_post_meta( $post->ID, self::META_ROTATION_KEYS, true );
		$did->verification_keys = get_post_meta( $post->ID, self::META_VERIFICATION_KEYS, true );

		return $did;
	}

	/**
	 * Get a DID from its internal ID.
	 *
	 * @param int|WP_Post|null $id The internal ID.
	 * @return ?self
	 */
	public static function from_internal_id( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return null;
		}

		return self::from_post( $post );
	}

	/**
	 * Create a DID instance.
	 *
	 * @return self
	 */
	public static function create() {
		$did = new self();

		// Generate an initial keypair for rotation.
		$rotation_key = Keys\ECKey::generate( Keys\CURVE_K256 );
		$did->rotation_keys = [
			$rotation_key->encode_private(),
		];

		// Generate an initial keypair for verification.
		$did->generate_verification_key();

		// Create the genesis operation.
		$genesis_unsigned = new Operation(
			type: 'plc_operation',
			rotationKeys: [
				$rotation_key,
			],
			verificationMethods: $did->get_verification_keys_for_op(),
			alsoKnownAs: [],
			services: [],
		);

		// Sign the op, then generate the DID from it.
		$genesis_signed = $genesis_unsigned->sign( $rotation_key );
		$did_chars = genesis_to_plc( $genesis_signed );
		$did_id = sprintf( 'did:plc:%s', $did_chars );

		$did->id = $did_id;
		$did->perform_operation( $genesis_signed );
		$did->save();
		return $did;
	}
}
