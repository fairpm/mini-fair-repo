<?php

namespace MiniFAIR;

const CACHE_PREFIX = 'minifair-';
const CACHE_LIFETIME = 12 * HOUR_IN_SECONDS;

use Exception;
use MiniFAIR\PLC\DID;

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
 * @throws Exception
 * @param string $url URL.
 * @param string $method Calling method.
 * @param array $opt wp_remote_get options.
 * @return stdClass
 */
function get_remote_url( $url, $method, $opt = null ) {
	$opt = $opt ?? [ 'headers' => [ 'Accept' => 'application/did+ld+json' ] ];
	$response = wp_cache_get( CACHE_PREFIX . sha1( $url ) );
	if ( ! $response ) {
		$response = wp_remote_get( $url, $opt );
		if ( is_wp_error( $response ) ) {
			throw new Exception( "Error {$method}: " . $response->get_error_message() );
		}
		wp_cache_set( CACHE_PREFIX . sha1( $url ), $response, '', CACHE_LIFETIME );
	}

	return $response;
}
