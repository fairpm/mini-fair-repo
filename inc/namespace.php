<?php
/**
 * Plugin namespace.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR;

use MiniFAIR\PLC\DID;

const CACHE_PREFIX = 'minifair-';
const CACHE_LIFETIME = 12 * HOUR_IN_SECONDS;

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap() : void {
	Admin\bootstrap();
	API\bootstrap();
	Git_Updater\bootstrap();
	PLC\bootstrap();
}

/**
 * Get providers.
 *
 * @return Provider[]
 */
function get_providers() : array {
	static $providers = [];
	if ( ! empty( $providers ) ) {
		return $providers;
	}

	return apply_filters( 'minifair.providers', [] );
}

/**
 * Get available packages.
 *
 * @return string[] Package IDs.
 */
function get_available_packages() : array {
	$packages = [];
	foreach ( get_providers() as $provider ) {
		$packages = array_merge( $packages, $provider->get_active_ids() );
	}
	return array_unique( $packages );
}

/**
 * Get a package's metadata.
 *
 * @param DID $did The DID object.
 * @return API\MetadataDocument|null
 */
function get_package_metadata( DID $did ) {
	foreach ( get_providers() as $provider ) {
		if ( $provider->is_authoritative( $did ) ) {
			return $provider->get_package_metadata( $did );
		}
	}

	return null;
}

/**
 * Update a package's metadata.
 *
 * @param DID  $did              The DID object.
 * @param bool $force_regenerate Optional. Whether to forcibly regenerate the metadata. True to skip cache. Default false.
 * @return bool|null True on a successful update, false on a failed update, or null if no providers were authoritative for the DID.
 */
function update_metadata( DID $did, bool $force_regenerate = false ) {
	foreach ( get_providers() as $provider ) {
		if ( $provider->is_authoritative( $did ) ) {
			return $provider->update_metadata( $did, $force_regenerate );
		}
	}

	return null;
}

/**
 * Get a remote URL's response.
 *
 * @param string $url URL.
 * @param array  $opt wp_remote_get options.
 * @return array|WP_Error The response, or a WP_Error object on failure.
 */
function get_remote_url( $url, $opt = null ) {
	$opt = $opt ?? [ 'headers' => [ 'Accept' => 'application/did+ld+json' ] ];
	$cache_key = CACHE_PREFIX . sha1( $url ) . sha1( serialize( $opt ) );
	$response = wp_cache_get( $cache_key );
	if ( ! $response ) {
		$response = wp_remote_get( $url, $opt );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		wp_cache_set( $cache_key, $response, '', CACHE_LIFETIME );
	}

	return $response;
}
