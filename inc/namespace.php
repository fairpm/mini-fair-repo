<?php

namespace MiniFAIR;

const CACHE_PREFIX = 'minifair-';
const CACHE_LIFETIME = 12 * HOUR_IN_SECONDS;

use Exception;
use MiniFAIR\PLC\DID;
use WP_Error;

function bootstrap() {
	Admin\bootstrap();
	API\bootstrap();
	Git_Updater\bootstrap();
	PLC\bootstrap();
}

/**
 * @return Provider[]
 */
function get_providers() : array {
	static $providers = [];
	if ( ! empty( $providers ) ) {
		return $providers;
	}

	$providers = [
		Git_Updater\Provider::TYPE => new Git_Updater\Provider(),
	];
	$providers = apply_filters( 'minifair.providers', $providers );
	return $providers;
}

function get_available_packages() : array {
	$packages = [];
	foreach ( get_providers() as $provider ) {
		$packages = array_merge( $packages, $provider->get_active_ids() );
	}
	return array_unique( $packages );
}

/**
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
 * @param DID $did
 * @param bool $force_regenerate
 * @return bool|null
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
 * @param string $url URL.
 * @param array $opt wp_remote_get options.
 * @return array|WP_Error
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
