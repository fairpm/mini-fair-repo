<?php

namespace MiniFAIR\PLC;

use Elliptic\EC\KeyPair;
use Exception;
use MiniFAIR\API;
use MiniFAIR\Keys;
use WP_Post;

class DID {
	const DIRECTORY_API = 'https://plc.directory';

	const POST_TYPE = 'plc_did';
	const META_DID = 'plc_did';
	const META_ROTATION_KEYS = 'plc_did_rotation_keys';
	const META_VERIFICATION_KEYS = 'plc_did_verification_keys';

	public readonly string $id;
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

	protected ?string $prev = null;

	/**
	 * @return KeyPair[]
	 */
	public function get_rotation_keys() : array {
		return array_map( fn ( $key ) => Keys\decode_private_key( $key ), $this->rotation_keys );
	}

	/**
	 * @return KeyPair[]
	 */
	public function get_verification_keys() : array {
		return array_map( fn ( $key ) => Keys\decode_private_key( $key ), $this->verification_keys );
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

	public function save() {
		// If we don't have an internal ID, we need to create a new DID.
		if ( ! $this->internal_id ) {
			$this->create_post();
		}

		update_post_meta( $this->internal_id, self::META_DID, $this->id ?? null );

		update_post_meta( $this->internal_id, self::META_ROTATION_KEYS, $this->rotation_keys );
		update_post_meta( $this->internal_id, self::META_VERIFICATION_KEYS, $this->verification_keys );
	}

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
			throw new \Exception( 'Error performing operation: ' . $response->get_error_message() );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			var_dump( $response );
			throw new \Exception( 'Error performing operation: ' . wp_remote_retrieve_body( $response ) );
		}

		var_dump( $response );
	}

	public function update() {
		$op = $this->prepare_update_op();
		if ( ! $op ) {
			var_dump( 'No changes to update' );
			return;
		}

		// Perform the operation.
		return $this->perform_operation( $op );
	}

	protected function prepare_update_op() : ?SignedOperation {
		// Fetch the previous op.
		$last_op = $this->fetch_last_op();

		// Get it as a CID.
		$last_cid = cid_for_operation( $last_op );

		// Merge prior data with current data.
		$update_unsigned = new Operation(
			type: 'plc_operation',
			rotationKeys: $this->get_rotation_keys(),
			verificationMethods: [
				VERIFICATION_METHOD_ID => $this->get_verification_keys()[0],
			],
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
	 * @throws Exception
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

	public function refresh() {
		// $url = sprintf( '%s/%s', static::DIRECTORY_API, $this->id );
		// $response = wp_remote_get( $url, [
		// 	'headers' => [
		// 		'Accept' => 'application/did+ld+json',
		// 	],
		// ] );
		// if ( is_wp_error( $response ) ) {
		// 	return false;
		// }
	}

	public function is_published() {
		$this->refresh();
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
	 */
	protected bool $created = false;

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

	public static function from_post( WP_Post $post ) {
		$did = new self();
		$did->internal_id = $post->ID;
		$did->id = get_post_meta( $post->ID, self::META_DID, true );
		$did->rotation_keys = get_post_meta( $post->ID, self::META_ROTATION_KEYS, true );
		$did->verification_keys = get_post_meta( $post->ID, self::META_VERIFICATION_KEYS, true );

		return $did;
	}

	public static function from_internal_id( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return null;
		}

		return self::from_post( $post );
	}

	public static function create() {
		$did = new self();

		// Generate an initial keypair for rotation.
		$rotation_key = Keys\generate_keypair();
		$encoded_rotation_key = Keys\encode_private_key( $rotation_key, Keys\CURVE_K256 );
		$did->rotation_keys = [
			$encoded_rotation_key,
		];

		// Generate an initial keypair for verification.
		$verification_key = Keys\generate_keypair();
		$encoded_verification_key = Keys\encode_private_key( $verification_key, Keys\CURVE_K256 );
		$did->verification_keys = [
			$encoded_verification_key,
		];

		// Create the genesis operation.
		$genesis_unsigned = new Operation(
			type: 'plc_operation',
			rotationKeys: [
				$rotation_key,
			],
			verificationMethods: [
				VERIFICATION_METHOD_ID => $verification_key,
				// 'atproto' => $verification_key,
			],
			alsoKnownAs: [],
			services: [],
			// 'services' => [
			// 	'fairpm_repo' => [
			// 		'serviceEndpoint' => 'https://fairpm.example.com/repo',
			// 		'type' => 'FairPackageManagementRepo',
			// 	],
			// ],
			// services: [
			// 	'atproto_pds' => [
			// 		'endpoint' => 'https://example.com/pds',
			// 		'type' => 'AtprotoPersonalDataServer',
			// 	],
			// ],
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
