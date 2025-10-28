<?php
/**
 * Provider.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR;

use MiniFAIR\PLC\DID;

/**
 * Provider interface.
 */
interface Provider {
	/**
	 * Get the active package IDs for this provider.
	 *
	 * @return array An array of active package IDs.
	 */
	public function get_active_ids() : array;

	/**
	 * Get the package IDs that have problems.
	 *
	 * @return WP_Error[] Map of package ID to WP_Error object. (Use DID as key if available, or some other human-readable identifier.)
	 */
	public function get_invalid() : array;

	/**
	 * Check if this provider is authoritative for the given DID.
	 *
	 * @param DID $did The DID to check.
	 * @return bool True if this provider is authoritative for the DID, false otherwise.
	 */
	public function is_authoritative( DID $did ) : bool;

	/**
	 * Get the package metadata for a given package ID.
	 *
	 * @param DID $did The DID object representing the package.
	 * @return API\MetadataDocument|WP_Error
	 */
	public function get_package_metadata( DID $did );

	/**
	 * Get the release document for a given package ID and version.
	 *
	 * @param DID    $did     The DID object representing the package.
	 * @param string $version The version to get.
	 * @return API\ReleaseDocument|WP_Error
	 */
	public function get_release( DID $did, string $version );
}

