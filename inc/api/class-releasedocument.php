<?php
/**
 * Release Document.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\API;

use JsonSerializable;

class ReleaseDocument implements JsonSerializable {
	public string $version;
	public array $requires;
	public array $suggests;
	public array $provides;
	public array $artifacts;

	protected function add_artifact( string $type, array $data ) : void {
		if ( ! isset( $this->artifacts[ $type ] ) ) {
			$this->artifacts[ $type ] = [];
		}
		$this->artifacts[ $type ][] = $data;
	}

	public function jsonSerialize() : array {
		return [
			'@context' => 'https://fair.pm/ns/release/v1',
			'version' => $this->version,
			'requires' => $this->requires,
			'suggests' => $this->suggests,
			'provides' => $this->provides,
			'artifacts' => $this->artifacts,
		];
	}
}