<?php

namespace MiniFAIR\API;

use MiniFAIR;

const REST_NAMESPACE = 'minifair/v1';

use MiniFAIR\PLC\DID;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Server;

function bootstrap() : void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );
}

function register_routes() : void {
	register_rest_route( REST_NAMESPACE, '/packages/?', [
		'show_in_index' => true,
		'methods' => WP_REST_Server::READABLE,
		'callback' => __NAMESPACE__ . '\\get_packages_data',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( REST_NAMESPACE, '/packages/(?P<id>did:\w+:[\w-]+)', [
		'show_in_index' => true,
		'methods' => WP_REST_Server::READABLE,
		'callback' => __NAMESPACE__ . '\\get_package_data',
		'permission_callback' => '__return_true',
		'args' => [
			'id' => [
				'type' => 'string',
				'required' => true,
				'validation_callback' => function ( $param, $request, $key ) {
					if ( ! preg_match( '/^did:plc:[\w-]+$/', $param ) ) {
						return new WP_Error(
							'minifair.get_package.invalid_id',
							__( 'Invalid package ID.', 'minifair' ),
							[ 'status' => WP_Http::BAD_REQUEST ]
						);
					}
					return true;
				},
			],
		],
	] );
}

function get_packages_data( WP_REST_Request $request ) {
	$response = [];
	$packages = MiniFAIR\get_available_packages();
	foreach ( $packages as $package_id ) {
		$did = DID::get( $package_id );
		if ( ! $did ) {
			continue;
		}
		$metadata = MiniFAIR\get_package_metadata( $did );
		if ( is_null( $metadata ) ) {
			continue;
		}
		$response[] = $metadata;
	}

	return $response;
}

function get_package_data( WP_REST_Request $request ) {
	$id = $request->get_param( 'id' );

	// Check that we actually manage this package.
	if ( ! str_starts_with( $id, 'did:plc:' ) ) {
		// todo, implement did:web
		return new WP_Error(
			'minifair.get_package.invalid_id',
			"Can't manage non-PLC package",
			[ 'status' => WP_Http::INTERNAL_SERVER_ERROR ]
		);
	}

	$did = DID::get( $id );
	if ( empty( $did ) ) {
		echo 'wat';
		return new WP_Error(
			'minifair.get_package.not_found',
			__( 'Package not found.', 'minifair' ),
			[ 'status' => WP_Http::NOT_FOUND ]
		);
	}

	foreach ( MiniFAIR\get_providers() as $provider ) {
		if ( ! $provider->is_authoritative( $did ) ) {
			continue;
		}

		// If the provider is authoritative, we can fetch the package data.
		$response = $provider->get_package_metadata( $did );
		break;
	}

	if ( empty( $response ) ) {
		return new WP_Error(
			'minifair.get_package.not_found',
			__( 'Package not found.', 'minifair' ),
			[ 'status' => WP_Http::NOT_FOUND ]
		);
	}

	return $response;
}
