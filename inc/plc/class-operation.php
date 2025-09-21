<?php

namespace MiniFAIR\PLC;

use MiniFAIR\Keys;
use MiniFAIR\Keys\Key;
use Exception;
use JsonSerializable;

class Operation implements JsonSerializable {
	public function __construct(
		/**
		 * Operation type (plc_operation or plc_tombstone)
		 */
		public string $type,

		/**
		 * Rotation keys.
		 *
		 * @var KeyPair[]
		 */
		public array $rotationKeys,

		/**
		 * Verification keys.
		 *
		 * @var array<string, KeyPair>
		 */
		public array $verificationMethods,

		/**
		 * Public key.
		 *
		 * @var string[]
		 */
		public array $alsoKnownAs,

		/**
		 * Services.
		 *
		 * @var array<string, string>
		 */
		public array $services,

		/**
		 * Previous operation.
		 *
		 * @var string|null
		 */
		public ?string $prev = null,
	) {
	}

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

	public function sign( Key $rotation_key ) : SignedOperation {
		return sign_operation( $this, $rotation_key );
	}

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
