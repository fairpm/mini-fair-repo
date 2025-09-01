<?php

namespace MiniFAIR\API;

use JsonSerializable;

class MetadataDocument implements JsonSerializable {
	public string $id;
	public string $type;
	public string $name;
	public string $slug;
	public string $filename;
	public string $description;
	public array $authors = [];
	public string $license;
	public array $security = [];
	public array $keywords = [];

	public array $sections = [];

	/**
	 * @var ReleaseDocument[]
	 */
	public array $releases = [];

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
			'releases' => $this->releases,
		];
	}
}
