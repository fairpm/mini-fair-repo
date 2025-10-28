<?php
/**
 * Signed operation.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use Exception;
use JsonSerializable;

class SignedOperation extends Operation implements JsonSerializable {
	public readonly string $sig;

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

	public function validate() : bool {
		if ( empty( $this->sig ) ) {
			throw new Exception( 'Signature is empty' );
		}

		return parent::validate();
	}

	public function jsonSerialize() : array {
		$data = parent::jsonSerialize();
		$data['sig'] = $this->sig;
		return $data;
	}
}
