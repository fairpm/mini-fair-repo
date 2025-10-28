<?php
/**
 * Command.
 *
 * @package MiniFAIR
 */

namespace MiniFAIR\PLC;

use WP_CLI;
use WP_CLI_Command;

/**
 * Command class.
 */
class Command extends WP_CLI_Command {
	/**
	 * Generate a new DID.
	 *
	 * @param array $args       The command line arguments.
	 * @param array $assoc_args The associative command line arguments.
	 * @return void
	 */
	public function generate( $args, $assoc_args ) {
		$did = DID::create();

		printf(
			"DID:              %s\n",
			esc_html( $did->id )
		);

		$rot_keys = $did->get_rotation_keys();
		foreach ( $rot_keys as $key ) {
			$encoded = $key->encode_public();
			printf(
				"Rotation key:     %s\n",
				esc_html( $encoded )
			);
		}
		$verif_keys = $did->get_verification_keys();
		foreach ( $verif_keys as $key ) {
			$encoded = $key->encode_public();
			printf(
				"Verification key: %s\n",
				esc_html( $encoded )
			);
		}
		exit;
	}

	/**
	 * Get a DID.
	 *
	 * @param array $args       The command line arguments.
	 * @param array $assoc_args The associative command line arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		$did = DID::get( $args[0] );
		var_dump( $did );

		printf(
			"DID:              %s\n",
			esc_html( $did->id )
		);

		$rot_keys = $did->get_rotation_keys();
		foreach ( $rot_keys as $key ) {
			$encoded = $key->encode_public();
			printf(
				"Rotation key:     %s\n",
				esc_html( $encoded )
			);
		}
		$verif_keys = $did->get_verification_keys();
		foreach ( $verif_keys as $key ) {
			$encoded = $key->encode_public();
			printf(
				"Verification key: %s\n",
				esc_html( $encoded )
			);
		}
	}

	/**
	 * Update a DID.
	 *
	 * ## OPTIONS
	 * <did>
	 * : The DID to update
	 *
	 * @param array $args       The command line arguments.
	 * @param array $assoc_args The associative command line arguments.
	 * @return void
	 */
	public function update( $args, $assoc_args ) {
		$did = DID::get( $args[0] );
		if ( ! $did ) {
			WP_CLI::error( 'DID not found' );
		}

		$res = $did->update();
		var_dump( $res );
	}

	/**
	 * Import a DID.
	 *
	 * ## OPTIONS
	 * <did>
	 * : The DID to import.
	 *
	 * <rotation_keys>
	 * : The rotation keys for the DID.
	 *
	 * <verification_keys>
	 * : The verification keys for the DID.
	 *
	 * @param array $args       The command line arguments.
	 * @param array $assoc_args The associative command line arguments.
	 */
	public function import( $args, $assoc_args ) {
		$did = new DID();
		// $did->set_id( $args[0] );.
		// $did->set_rotation_keys( $args[1] );.
		// $did->set_verification_keys( $args[2] );.
		$did->save();
	}
}
