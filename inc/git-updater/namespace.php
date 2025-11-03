<?php
/**
 * The Git Updater namespace.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\Git_Updater;

use MiniFAIR;
use MiniFAIR\Keys\Key;
use MiniFAIR\PLC\DID;
use MiniFAIR\PLC\Util;
use stdClass;
use WP_Error;

/**
 * Bootstrap.
 *
 * @return void
 */
function bootstrap() : void {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\on_load' );
}

/**
 * Add hooks on load.
 *
 * @return void
 */
function on_load() : void {
	// Only run if Git Updater is active.
	if ( ! class_exists( 'Fragen\Git_Updater\Bootstrap' ) ) {
		return;
	}

	add_filter( 'minifair.providers', __NAMESPACE__ . '\\register_provider' );
	add_action( 'get_remote_repo_meta', __NAMESPACE__ . '\\update_on_get_remote_meta', 20, 2 );
}

/**
 * Register the Git Updater provider.
 *
 * @param array<string, ProviderInterface> $providers The previously registered providers.
 * @return array<string, ProviderInterface>
 */
function register_provider( array $providers ): array {
	return array_merge( $providers, [
		Provider::TYPE => new Provider(),
	] );
}

/**
 * Update necessary FAIR data during the Git Updater get_remote_repo_meta().
 *
 * @param stdClass $repo     Repository to update.
 * @param object   $repo_api Repository API object.
 * @return void
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
 * @param stdClass $repo     Repository to update.
 * @param object   $repo_api Repository API object.
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
		__( 'Error updating FAIR data for repository.', 'mini-fair' )
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
 * Generate an artifact's metadata.
 *
 * @param DID    $did              The DID object.
 * @param string $url              The artifact's download URL.
 * @param bool   $force_regenerate Optional. True to skip cache. Default false.
 * @return array|WP_Error The artifact's metadata, or WP_Error on failure.
 */
function generate_artifact_metadata( DID $did, string $url, $force_regenerate = false ) {
	$keys = $did->get_verification_keys();
	if ( empty( $keys ) ) {
		return new WP_Error(
			'minifair.generate_artifact_metadata.missing_keys',
			__( 'No verification keys found for DID', 'mini-fair' )
		);
	}

	// todo: make active key selectable.
	$signing_key = end( $keys );
	if ( empty( $signing_key ) ) {
		return new WP_Error(
			'minifair.generate_artifact_metadata.missing_signing_key',
			__( 'No signing key found for DID', 'mini-fair' )
		);
	}

	$artifact_id = sprintf( '%s:%s', $did->id, substr( sha1( $url ), 0, 8 ) );
	$artifact_metadata = get_option( 'minifair_artifact_' . $artifact_id, null );

	// Fetch the artifact.
	$opt = str_contains( $url, 'api.github.com' ) && str_contains( $url, 'releases/assets' )
		? [ 'headers' => [ 'Accept' => 'application/octet-stream' ] ]
		: [];
	if ( ! $force_regenerate && ! empty( $artifact_metadata ) && isset( $artifact_metadata['etag'] ) ) {
		$opt['headers']['If-None-Match'] = $artifact_metadata['etag'];
	}

	$res = MiniFAIR\get_remote_url( $url, $opt );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	if ( ! $force_regenerate && 304 === $res['response']['code'] ) {
		// Not modified, no need to update.
		return $artifact_metadata;
	}
	if ( 200 !== $res['response']['code'] ) {
		// Handle unexpected response code.
		return new WP_Error(
			'minifair.artifact.fetch_error',
			sprintf( __( 'Error fetching artifact: %s', 'mini-fair' ), $res['response']['code'] ),
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

/**
 * Sign an artifact's data.
 *
 * @param Key    $key  The signing key.
 * @param string $data The artifact's data.
 * @return string The data's signature.
 */
function sign_artifact_data( Key $key, $data ) {
	// Hash, then sign the hash.
	$hash = hash( 'sha384', $data, false );
	$signature = $key->sign( $hash );

	$compact = hex2bin( $signature );
	return Util\base64url_encode( $compact );
}
