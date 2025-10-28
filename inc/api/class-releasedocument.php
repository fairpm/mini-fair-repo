<?php
/**
 * Release Document.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\API;

use JsonSerializable;

/**
 * ReleaseDocument class.
 */
class ReleaseDocument implements JsonSerializable {
	/**
	 * Version.
	 *
	 * @var string
	 */
	public string $version;

	/**
	 * Requirements.
	 *
	 * @var array
	 */
	public array $requires;

	/**
	 * Suggested additional packages.
	 *
	 * @var array
	 */
	public array $suggests;

	/**
	 * Provided capabilities.
	 *
	 * @var array
	 */
	public array $provides;

	/**
	 * Artifacts.
	 *
	 * @var array
	 */
	public array $artifacts;

	/**
	 * Add an artifact to the release document.
	 *
	 * @param string $type The type of artifact.
	 * @param array $data The artifact's data.
	 * @return void
	 */
	protected function add_artifact( string $type, array $data ) : void {
		if ( ! isset( $this->artifacts[ $type ] ) ) {
			$this->artifacts[ $type ] = [];
		}
		$this->artifacts[ $type ][] = $data;
	}

	/**
	 * Return data that should be serialized to JSON.
	 *
	 * @return array
	 */
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
