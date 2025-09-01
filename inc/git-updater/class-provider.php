<?php

namespace MiniFAIR\Git_Updater;

use Fragen\Singleton;
use MiniFAIR\API\MetadataDocument;
use MiniFAIR\Provider as ProviderInterface;
use MiniFAIR\PLC\DID;
use stdClass;
use WP_Error;
use WP_Http;

class Provider implements ProviderInterface {
	const TYPE = 'git-updater';

	/**
	 * Get the active package IDs for this provider.
	 *
	 * @return array An array of active package IDs.
	 */
	public function get_active_ids() : array {
		$dummy = (object) [];
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $dummy )->get_plugin_configs();
		$gu_themes = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $dummy )->get_theme_configs();
		$gu_packages = array_merge( $gu_plugins, $gu_themes );

		return array_filter( array_map( fn ( $pkg ) => $pkg->did ?? null, $gu_packages ) );
	}

	public function get_invalid() : array {
		$dummy = (object) [];
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $dummy )->get_plugin_configs();
		$gu_themes = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $dummy )->get_theme_configs();
		$gu_packages = array_merge( $gu_plugins, $gu_themes );

		$problems = [];
		foreach ( $gu_packages as $pkg ) {
			if ( empty( $pkg->did ) ) {
				$problems[ $pkg->file ] = new WP_Error(
					'minifair.git_updater.missing_did',
					sprintf( __( 'Package %s is missing a DID. Specify it in the Plugin ID/Theme ID header.', 'minifair' ), $pkg->name ),
					[ 'status' => WP_Http::NOT_FOUND ]
				);
				continue;
			}

			$did = DID::get( $pkg->did );
			if ( empty( $did ) ) {
				$problems[ $pkg->file ] = new WP_Error(
					'minifair.git_updater.invalid_did',
					sprintf( __( "Package %s has a DID (%s), but the DID's keys are not registered on this site.", 'minifair' ), $pkg->name, $pkg->did ),
					[ 'status' => WP_Http::NOT_FOUND ]
				);
				continue;
			}
		}

		return $problems;
	}

	protected function get_package( string $did ) : ?stdClass {
		$dummy = (object) [];
		$gu_plugins = Singleton::get_instance( 'Fragen\Git_Updater\Plugin', $dummy )->get_plugin_configs();
		$gu_themes = Singleton::get_instance( 'Fragen\Git_Updater\Theme', $dummy )->get_theme_configs();
		$gu_packages = array_merge( $gu_plugins, $gu_themes );

		return array_find( $gu_packages, fn ( $pkg ) => $pkg->did === $did );
	}

	/**
	 * Check if this provider is authoritative for the given DID.
	 *
	 * @param DID $did The DID to check.
	 * @return bool True if this provider is authoritative for the DID, false otherwise.
	 */
	public function is_authoritative( DID $did ) : bool {
		$item = $this->get_package( $did->id );
		return ! empty( $item );
	}

	public function get_package_metadata( DID $did ) {
		$package = $this->get_package( $did->id );
		if ( ! $package ) {
			return new WP_Error(
				'minifair.get_package.not_found',
				__( 'Package not found.', 'minifair' ),
				[ 'status' => WP_Http::NOT_FOUND ]
			);
		}

		$data = new MetadataDocument();
		$data->id = $package->did;
		$data->type = 'wp-' . $package->type;
		$data->name = $package->name;
		$data->slug = $package->slug;
		$data->filename = $package->file;
		$data->description = substr( strip_tags( trim( $package->sections['description'] ) ), 0, 139 ) . '…';
		$data->license = $package->license ?? 'GPL-2.0-or-later';
		$data->keywords = $package->readme_tags ? array_values( $package->readme_tags ) : [];
		$data->sections = $package->sections;

		// Parse link back out of author string.
		$data->authors[] = [
			'name' => $package->author,
			'url' => $package->author_uri ??  '',
		];
		//foreach ( $package->contributors as $contributor ) {
		//	$data->authors[] = [
		//		'name' => $contributor['display_name'],
		//		'url' => $contributor['profile'],
		//	];
		//}

		// Releases.
		$data->releases = $this->get_release_data( $did, $package );

		return $data;
	}

	protected function get_release_data( DID $did, stdClass $package ) : array {
		// Requirements.
		$requires = [];
		$suggests = [];
		$provides = [];
		if ( ! empty( $package->requires_plugins ) ) {
			foreach ( $package->requires_plugins as $slug ) {
				if ( ! str_starts_with( $slug, 'did:' ) ) {
					continue;
				}

				$requires[ $slug ] = '*';
			}
		}
		if ( ! empty( $package->requires_php ) ) {
			$requires['env:php'] = '>=' . $package->requires_php;
		}
		if ( ! empty( $package->requires ) ) {
			$requires['env:wp'] = '>=' . $package->requires;
		}

		// Suggestions.
		if ( ! empty( $package->tested ) ) {
			$suggests['env:wp'] = '>=' . $package->tested;
		}

		// Releases.
		$needs_auth = $package->is_private;
		$releases = [];
		$images = [];
		$versions = $package->release_asset ? $package->release_assets : $package->tags;

		// Banners and icons.
		$other_assets = [
			'banner' => $package->banners,
			'icon' => $package->icons,
		];
		foreach( $other_assets as $key => $asset ) {
			foreach ( $asset as $asset_id => $url ) {
				if ( $key === 'icon' && $asset_id === 'default' && count( $asset ) > 1 ) {
					continue;
				}
				$image = getimagesize( $url );
				list( $width, $height ) = $image;
				$images[ $key ][] = [
					'url' => $url,
					'content-type' => str_ends_with( $url, '.svg' ) ? 'image/svg+xml' : $image['mime'],
					'height' => $height ?? null,
					'width' => $width ?? null,
				];
			}
		}

		foreach ( $versions as $tag => $artifact_url ) {
			$tag_ver = ltrim( $tag, 'v' );
			$release = [
				'version' => $tag_ver,

				// todo: load from this specific version.
				'requires' => $requires,
				'suggests' => $suggests,
				'provides' => $provides,

				'artifacts' => [
					'package' => [],
				],
			];
			$release['artifacts'] = $images;
			if ( $needs_auth ) {
				$release['auth'] = [];
			}

			$artifact_metadata = get_artifact_metadata( $did, $artifact_url );
			$release['artifacts']['package'][] = [
				'url' => $artifact_url,
				'content-type' => $package->release_asset ? 'application/octet-stream' : 'application/zip',
				'signature' => $artifact_metadata['signature'] ?? null,
				'checksum' => $artifact_metadata['sha256'] ?? null,
			];

			$releases[] = $release;
		}

		return $releases;
	}

	/**
	 * Get the release document for a given package ID and version.
	 *
	 * @return API\ReleaseDocument|WP_Error
	 */
	public function get_release( DID $did, string $version ) {
		$package = $this->get_package( $did->id );
		if ( ! $package ) {
			return new WP_Error(
				'minifair.get_package.not_found',
				__( 'Package not found.', 'minifair' ),
				[ 'status' => WP_Http::NOT_FOUND ]
			);
		}

		$releases = $this->get_release_data( $did, $package );
		$release = array_find( $releases, fn ( $r ) => $r['version'] === $version );
		return $release;
	}
}
