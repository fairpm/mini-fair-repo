<?php
/**
 * Metadata Document.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\API;

use JsonSerializable;

/**
 * MetadataDocument class.
 */
class MetadataDocument implements JsonSerializable {
	/**
	 * DID.
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * Package type.
	 *
	 * Example: 'wp-plugins' or 'wp-themes'.
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * The filename, relative to
	 * the plugins directory.
	 *
	 * @var string
	 */
	public string $filename;

	/**
	 * Description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Author data.
	 *
	 * @var array
	 */
	public array $authors = [];

	/**
	 * Software license.
	 *
	 * @var string
	 */
	public string $license;

	/**
	 * Security contact data.
	 *
	 * @var array
	 */
	public array $security = [];

	/**
	 * Search keywords.
	 *
	 * @var string
	 */
	public array $keywords = [];

	/**
	 * Last updated timestamp.
	 *
	 * @var string
	 */
	public string $last_updated;

	/**
	 * Information sections.
	 *
	 * @var string
	 */
	public array $sections = [];

	/**
	 * Releases.
	 *
	 * @var ReleaseDocument[]
	 */
	public array $releases = [];

	/**
	 * Return data that should be serialized to JSON.
	 *
	 * @return array
	 */
	public function jsonSerialize() : array {
		return [
			'@context' => 'https://fair.pm/ns/metadata/v1',
			'id' => $this->id,
			'type' => $this->type,
			'name' => $this->name,
			'slug' => $this->slug,
			'filename' => $this->filename,
			'description' => $this->description,
			'authors' => $this->authors,
			'license' => $this->license,
			'security' => $this->security,
			'keywords' => $this->keywords,
			'sections' => $this->sections,
			'last_updated' => $this->last_updated,
			'releases' => $this->releases,
		];
	}
}
