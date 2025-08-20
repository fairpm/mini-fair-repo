<?php

namespace MiniFAIR;

use Exception;
use MiniFAIR\PLC\DID;

function bootstrap() {
	Admin\bootstrap();
	API\bootstrap();
	Keys\bootstrap();
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
