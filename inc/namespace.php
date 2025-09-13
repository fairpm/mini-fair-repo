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
 * @param string $url URL.
 * @param array $opt wp_remote_get options.
 * @return array|WP_Error
 */
function get_remote_json( $url, $opt = [] ) {
	$opt['headers']['Accept'] ??= 'application/did+ld+json';
	return wp_remote_get( $url, $opt );
}
