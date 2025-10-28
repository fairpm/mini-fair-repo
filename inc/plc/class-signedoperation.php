<?php
/**
 * Signed operation.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use Exception;
use JsonSerializable;

/**
 * SignedOperation class.
 */
class SignedOperation extends Operation implements JsonSerializable {
	/**
	 * Signature.
	 *
	 * @var string
	 */
	public readonly string $sig;

	/**
	 * Constructor.
	 *
	 * @param Operation $operation The operation.
	 * @param string    $sig       The signature.
	 * @return void
	 */
	public function __construct(
		Operation $operation,
		string $sig,
	) {
		parent::__construct(
			$operation->type,
			$operation->rotationKeys,
			$operation->verificationMethods,
			$operation->alsoKnownAs,
			$operation->services,
			$operation->prev,
		);

		$this->sig = $sig;
	}

	/**
	 * Validate the operation.
	 *
	 * @throws Exception If the signature is empty.
	 * @return bool Whether the operation is valid.
	 */
	public function validate() : bool {
		if ( empty( $this->sig ) ) {
			throw new Exception( 'Signature is empty' );
		}

		return parent::validate();
	}

	/**
	 * Return data that should be serialized to JSON.
	 *
	 * @return array
	 */
	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['sig'] = $this->sig;
		return $data;
	}
}
