<?php
/**
 * Operation.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use Exception;
use JsonSerializable;
use MiniFAIR\Keys;
use MiniFAIR\Keys\Key;

/**
 * Operation class.
 */
class Operation implements JsonSerializable {
	/**
	 * Constructor.
	 *
	 * @param string                 $type                Operation type (plc_operation or plc_tombstone).
	 * @param KeyPair[]              $rotationKeys        Rotation keys.
	 * @param array<string, KeyPair> $verificationMethods Verification keys.
	 * @param string[]               $alsoKnownAs         Public key.
	 * @param array<string, string>  $services            Services.
	 * @param ?string                $prev                Previous operation.
	 * @return void
	 */
	public function __construct(
		public string $type,
		public array $rotationKeys,
		public array $verificationMethods,
		public array $alsoKnownAs,
		public array $services,
		public ?string $prev = null,
	) {
	}

	/**
	 * Validate the operation.
	 *
	 * @throws Exception If the operation type is empty.
	 * @throws Exception If the operation type is invalid.
	 * @throws Exception If the rotation keys are empty.
	 * @throws Exception If a rotation key is not an instance of Key.
	 * @throws Exception If the verification methods are empty.
	 * @throws Exception If a verification method is invalid.
	 * @return true True if valid.
	 */
	public function validate() : bool {
		if ( empty( $this->type ) ) {
			throw new Exception( 'Operation type is empty' );
		}
		if ( ! in_array( $this->type, [ 'plc_operation', 'plc_tombstone' ], true ) ) {
			throw new Exception( 'Invalid operation type' );
		}

		if ( empty( $this->rotationKeys ) ) {
			throw new Exception( 'Rotation keys are empty' );
		}
		foreach ( $this->rotationKeys as $key ) {
			if ( ! $key instanceof Key ) {
				throw new Exception( 'Rotation key is not a Key object' );
			}
		}

		if ( empty( $this->verificationMethods ) ) {
			throw new Exception( 'Verification methods are empty' );
		}
		foreach ( $this->verificationMethods as $id => $key ) {
			if ( ! str_starts_with( $id, VERIFICATION_METHOD_PREFIX ) ) {
				throw new Exception( sprintf( 'Invalid verification method ID: %s', $id ) );
			}
			if ( ! $key instanceof Key ) {
				throw new Exception( 'Rotation key is not a Key object' );
			}
		}

		if ( empty( $this->prev ) ) {
			// Genesis operation, require rotationKeys and verificationMethods.
			if ( empty( $this->rotationKeys ) || empty( $this->verificationMethods ) ) {
				throw new Exception( 'Missing rotationKeys or verificationMethods' );
			}
			if ( empty( $this->verificationMethods ) ) {
				throw new Exception( 'Missing verification method for FAIR' );
			}
		}

		return true;
	}

	/**
	 * Sign the operation.
	 *
	 * @param Key $rotation_key Rotation key for signing.
	 * @return SignedOperation
	 */
	public function sign( Key $rotation_key ) : SignedOperation {
		return sign_operation( $this, $rotation_key );
	}

	/**
	 * Return data that should be serialized to JSON.
	 *
	 * @return array
	 */
	public function jsonSerialize() : array {
		$methods = [];
		foreach ( $this->verificationMethods as $key => $keypair ) {
			$methods[ $key ] = Keys\encode_did_key( $keypair, Keys\CURVE_K256 );
		}

		return [
			'type' => $this->type,
			'rotationKeys' => array_map( fn ( $key ) => Keys\encode_did_key( $key, Keys\CURVE_K256 ), $this->rotationKeys ),
			'verificationMethods' => $methods,
			'alsoKnownAs' => $this->alsoKnownAs,
			'services' => (object) $this->services,
			'prev' => $this->prev,
		];
	}
}
