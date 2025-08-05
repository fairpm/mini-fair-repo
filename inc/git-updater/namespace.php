<?php

namespace MiniFAIR\Git_Updater;


use Elliptic\EC\KeyPair;
use MiniFAIR\PLC;
use MiniFAIR\PLC\DID;
use MiniFAIR\PLC\Util;
use stdClass;
use WP_Error;

const CACHE_PREFIX = 'minifair-';

function bootstrap() : void {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\on_load' );
}

function on_load() : void {
	// Only run if Git Updater is active.
	if ( ! class_exists( 'Fragen\Git_Updater\Bootstrap' ) ) {
		return;
	}

	add_action( 'get_remote_repo_meta', __NAMESPACE__ . '\\update_on_get_remote_meta', 20, 2 ) ;
}

/**
 * Update necessary FAIR data during the Git Updater get_remote_repo_meta().
 *
 * @param stdClass $repo Repository to update.
 * @param object $repo_api Repository API object.
 */
function update_on_get_remote_meta( stdClass $repo, $repo_api ) : void {
	$err = update_fair_data( $repo, $repo_api );
	if ( is_wp_error( $err ) ) {
		// Log the error.
		error_log( sprintf( 'Error updating FAIR data for %s: %s', $repo->git, $err->get_error_message() ) );
	}
}

/**
 * Update FAIR data for a specific repository.
 *
 * Generates metadata for each tag's artifact.
 *
 * @return null|WP_Error Error if one occurred, null otherwise.
 */
function update_fair_data( $repo, $repo_api ) : ?WP_Error {
	if ( empty( $repo->did ) ) {
		// Not a FAIR package, skip.
		return null;
	}

	if ( null === $repo_api ) {
		return null;
	}

	// Fetch the DID.
	$did = DID::get( $repo->did );
	if ( ! $did ) {
		// No DID found, skip.
		return null;
	}

	$errors = [];
	$versions = $repo_api->type->release_asset ? $repo_api->type->release_assets : $repo_api->type->tags;

	foreach ( $versions as $tag => $url ) {
		// This probably wants to be tied to the commit SHA, so that
		// if tags are changed, we refresh automatically.
		$data = generate_artifact_metadata( $did, $url );
		if ( is_wp_error( $data ) ) {
			$errors[] = $data;
		}
	}

	if ( empty( $errors ) ) {
		return null;
	}

	$err = new WP_Error(
		'minifair.update_fair_data.error',
		__( 'Error updating FAIR data for repository.', 'minifair' )
	);
	foreach ( $errors as $error ) {
		$err->merge_from( $error );
	}
	return $err;
}

/**
 * Get the artifact metadata for a given DID and URL.
 *
 * @param DID $did The DID object.
 * @param string $url The URL of the artifact.
 * @return array|null The artifact metadata, or null if not found.
 */
function get_artifact_metadata( DID $did, $url ) {
	$artifact_id = sprintf( '%s:%s', $did->id, substr( sha1( $url ), 0, 8 ) );
	return get_option( 'minifair_artifact_' . $artifact_id, null );
}

/**
 * @return array|WP_Error
 */
function generate_artifact_metadata( DID $did, $url ) {
	$signing_key = $did->get_verification_keys()[0] ?? null;
	if ( ! $signing_key ) {
		var_dump( 'No signing key found for DID' );
		return;
	}

	$artifact_id = sprintf( '%s:%s', $did->id, substr( sha1( $url ), 0, 8 ) );
	$artifact_metadata = get_option( 'minifair_artifact_' . $artifact_id, null );

	// Fetch the artifact.
	$opt = [
		'headers' => [
			'Accept' => 'application/octet-stream;q=1.0, */*;q=0.7',
		],
	];
	if ( ! empty( $artifact_metadata ) && isset( $artifact_metadata['etag'] ) ) {
		$opt['headers']['If-None-Match'] = $artifact_metadata['etag'];
	}
	$res = wp_cache_get( CACHE_PREFIX . sha1( $url ) );
	if ( ! $res ) {
		$res = wp_remote_get( $url, $opt );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		wp_cache_set( CACHE_PREFIX . sha1( $url ), $res, '', 12 * HOUR_IN_SECONDS );
	}

	if ( 304 === $res['response']['code'] ) {
		// Not modified, no need to update.
		return $artifact_metadata;
	}
	if ( 200 !== $res['response']['code'] ) {
		// Handle unexpected response code.
		return new WP_Error(
			'minifair.artifact.fetch_error',
			sprintf( __( 'Error fetching artifact: %s', 'minifair' ), $res['response']['code'] ),
			[ 'status' => $res['response']['code'] ]
		);
	}

	$next_metadata = [
		'etag' => $res['headers']['etag'] ?? null,
		'sha256' => 'sha256:' . hash( 'sha256', $res['body'], false ),
		'signature' => sign_artifact_data( $signing_key, $res['body'] ),
	];

	update_option( 'minifair_artifact_' . $artifact_id, $next_metadata );
	return $next_metadata;
}

function sign_artifact_data( KeyPair $key, $data ) {
	// Hash, then sign the hash.
	$hash = hash( 'sha256', $data, false );
	$signature = $key->sign( $hash, 'hex', [
		'canonical' => true
	] );

	// Convert to compact (IEEE-P1363) form, then to base64url.
	$compact = hex2bin( PLC\signature_to_compact( $key->ec, $signature ) );
	return Util\base64url_encode( $compact );
}
