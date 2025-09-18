<?php

namespace MiniFAIR\Admin;

use Exception;
use MiniFAIR;
use MiniFAIR\Keys;
use MiniFAIR\PLC\DID;
use WP_Post;

const ACTION_CREATE = 'create';
const ACTION_KEY_ADD = 'key_add';
const ACTION_KEY_REVOKE = 'key_revoke';
const ACTION_RESIGN = 'resign';
const ACTION_SYNC = 'sync';
const NONCE_PREFIX = 'minifair_';
const PAGE_SLUG = 'minifair';

function bootstrap() {
	// Register the admin menu and page before the PLC DID post type is registered.
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu', 0 );
	add_action( 'post_action_' . ACTION_KEY_ADD, __NAMESPACE__ . '\\handle_action', 10, 1 );
	add_action( 'post_action_' . ACTION_KEY_REVOKE, __NAMESPACE__ . '\\handle_action', 10, 1 );
	add_action( 'post_action_' . ACTION_RESIGN, __NAMESPACE__ . '\\handle_action', 10, 1 );
	add_action( 'post_action_' . ACTION_SYNC, __NAMESPACE__ . '\\handle_action', 10, 1 );

	// Hijack the post-new.php page to render our own form.
	add_action( 'replace_editor', function ( $res, WP_Post $post ) {
		if ( $post->post_type === DID::POST_TYPE ) {
			// Is it time to render?
			if ( ! empty( $GLOBALS['post'] ) ) {
				render_editor();
			}

			return true;
		}

		return $res;
	}, 10, 2 );
}

function add_admin_menu() {
	// add top level page
	$hook = add_menu_page(
		__( 'Mini FAIR', 'minifair' ),
		__( 'Mini FAIR', 'minifair' ),
		'manage_options',
		PAGE_SLUG,
		__NAMESPACE__ . '\\render_settings_page'
	);
	add_action( 'load-' . $hook, __NAMESPACE__ . '\\load_settings_page' );
}

function load_settings_page() {
}

function render_settings_page() {
	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'minifair' ) );
	}

	$providers = MiniFAIR\get_providers();
	$packages = MiniFAIR\get_available_packages();

	$invalid = [];
	foreach ( $providers as $provider ) {
		$invalid = array_merge( $invalid, $provider->get_invalid() );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Mini FAIR', 'minifair' ); ?></h1>

		<p><?php
			printf(
				__( 'Mini FAIR is active on your site. View your active packages at <a href="%1$s"><code>%1$s</code></a>', 'minifair' ),
				rest_url( '/minifair/v1/packages' )
			);
		?></p>

		<h2><?php esc_html_e( 'Active Packages', 'minifair' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Package ID', 'minifair' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name', 'minifair' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $packages as $package_id ) : ?>
					<tr>
						<?php
						$did = DID::get( $package_id );
						if ( ! $did ) {
							continue;
						}
						$data = MiniFAIR\get_package_metadata( $did );
						?>
						<td><code><?php echo esc_html( $package_id ); ?></code>
							<a href="<?php echo get_edit_post_link( $did->get_internal_post_id() ) ?>"><?php esc_html_e( '(View DID)', 'minifair' ) ?></a></td>
						<td><?php echo esc_html( $data->name ); ?></td>
					</tr>
				<?php endforeach; ?>
		</table>

		<?php if ( ! empty( $invalid ) ) : ?>
			<h2><?php esc_html_e( 'Invalid Packages', 'minifair' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Package ID', 'minifair' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Error', 'minifair' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $invalid as $id => $error ) : ?>
						<tr>
							<td><code><?php echo esc_html( $id ); ?></code></td>
							<td><?php echo esc_html( $error->get_error_message() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Publish a New Package', 'minifair' ); ?></h2>
		<p><?php esc_html_e( 'The first step in publishing a new package is to create a DID for it. This will act as the permanent, globally-unique ID for your package.', 'minifair' ); ?></p>
		<p>
			<a href="<?php echo admin_url( 'post-new.php?post_type=' . DID::POST_TYPE ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create New PLC DID…', 'minifair' ); ?>
			</a>
		</p>
	</div>
	<?php
}

/**
 * Fetch the raw data for the DID document.
 *
 * @internal This is intentionally uncached, as need the latest data for the DID.
 * @return stdClass|WP_Error
 */
function fetch_did( DID $did ) {
	$url = DID::DIRECTORY_API . '/' . $did->id;
	$res = wp_remote_get( $url, [
		'headers' => [
			'Accept' => 'application/did+ld+json',
		]
	] );
	if ( is_wp_error( $res ) ) {
		return $res;
	}

	return json_decode( $res['body'], true );
}

function render_editor() {
	if ( isset( $_POST['action'] ) && $_POST['action'] === ACTION_CREATE ) {
		on_create();
	}

	require_once ABSPATH . 'wp-admin/admin-header.php';

	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">';
	echo esc_html( $title );
	echo '</h1>';

	/** @var WP_Post */
	$post = $GLOBALS['post'];
	if ( $post->post_status === 'auto-draft' ) {
		// If the post is an auto-draft, we are creating a new PLC DID.
		render_new_page( $post );
	} else {
		// Otherwise, we are editing an existing PLC DID.
		render_edit_page( $post );
	}
}

function on_create() {
	check_admin_referer( NONCE_PREFIX . ACTION_CREATE );

	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'minifair' ) );
	}

	// Handle the form submission to create a new PLC DID.
	$did = DID::create();
	if ( is_wp_error( $did ) ) {
		wp_admin_notice(
			sprintf(
				__( 'Could not create DID: %s', 'minifair' ),
				$did->get_error_message()
			),
			[
				'type'               => 'error',
				'additional_classes' => [ 'notice-alt' ],
			]
		);
	} else {
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	}
}

function render_new_page( WP_Post $post ) {
	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'minifair' ) );
	}

	?>
	<p><?php esc_html_e( "PLC DIDs are used as your globally-unique package identifier. You can create one here if you're publishing a new package.", 'minifair' ) ?></p>
	<p><?php esc_html_e( 'PLC DIDs are permanent, and publicly available in the PLC directory.', 'minifair' ) ?></p>

	<form action="" method="post">
		<?php wp_nonce_field( NONCE_PREFIX . ACTION_CREATE ) ?>
		<input type="hidden" name="post" value="<?php echo esc_attr( $post->ID ); ?>" />
		<input type="hidden" name="action" value="<?= esc_attr( ACTION_CREATE ) ?>" />

		<table class="form-table">
			<!-- <tr>
				<th scope="row">
					<label for="recovery"><?php esc_html_e( 'Recovery Key', 'minifair' ); ?></label>
				</th>
				<td>
					<input type="text" id="recovery" name="recovery" class="regular-text" />
					<p class="description"><?php esc_html_e( 'If you have an existing recovery public key, enter it here.', 'minifair' ); ?></p>
				</td>
			</tr> -->
			<tr>
				<td colspan="2">
					<?php submit_button( __( 'Create PLC DID', 'minifair' ), 'primary', 'create_did' ); ?>
				</td>
			</tr>
		</table>
	</form>

	<?php
}

function handle_action( int $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== DID::POST_TYPE ) {
		return;
	}

	$action = $_REQUEST['action'] ?? '';
	if ( empty( $action ) ) {
		// This should never occur, since we're hooked into specific actions above.
		wp_die( __( 'No action specified.', 'minifair' ), '', [ 'response' => 400 ] );
	}

	check_admin_referer( NONCE_PREFIX . $action );

	$did = DID::from_post( $post );
	switch ( $action ) {
		case ACTION_KEY_ADD:
			on_add_key( $did );
			break;
		case ACTION_KEY_REVOKE:
			on_revoke_key( $did );
			break;
		case ACTION_RESIGN:
			on_resign( $did );
			break;
		case ACTION_SYNC:
			on_sync( $did );
			break;
		default:
			wp_die( __( 'Invalid action.', 'minifair' ), '', [ 'response' => 400 ] );
	}
}

function on_sync( DID $did ) {
	check_admin_referer( NONCE_PREFIX . ACTION_SYNC );

	try {
		$did->update();
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	} catch ( \Exception $e ) {
		wp_die( $e->getMessage(), __( 'Error Syncing PLC DID', 'minifair' ), [ 'response' => 500 ] );
	}
}

function on_resign( DID $did ) {
	check_admin_referer( NONCE_PREFIX . ACTION_RESIGN );

	try {
		MiniFAIR\update_metadata( $did, true );
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	} catch ( \Exception $e ) {
		wp_die( $e->getMessage(), __( 'Error Regenerating Signatures', 'minifair' ), [ 'response' => 500 ] );
	}
}

function on_add_key( DID $did ) {
	// Handle adding a new verification key.
	$did->generate_verification_key();

	try {
		$did->update();
		$did->save();
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	} catch ( \Exception $e ) {
		var_dump( $e );
		wp_die( $e->getMessage(), __( 'Error Syncing PLC DID', 'minifair' ), [ 'response' => 500 ] );
	}
}

function on_revoke_key( DID $did ) {
	// Handle revoking an existing verification key.
	$key_id = $_POST['key_id'] ?? '';
	if ( empty( $key_id ) ) {
		wp_die( __( 'No key ID specified.', 'minifair' ), '', [ 'response' => 400 ] );
	}

	// Find corresponding private key.
	$keys = $did->get_verification_keys();
	$key = array_find( $keys, fn ( $k ) => $k->encode_public() === $key_id );
	if ( empty( $key ) ) {
		wp_die( __( 'Invalid key ID.', 'minifair' ), '', [ 'response' => 400 ] );
	}

	if ( ! $did->invalidate_verification_key( $key ) ) {
		wp_die( __( 'Failed to revoke key.', 'minifair' ), '', [ 'response' => 500 ] );
	}

	try {
		$did->update();
		$did->save();
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	} catch ( Exception $e ) {
		var_dump( $e );
		wp_die( $e->getMessage(), __( 'Error Syncing PLC DID', 'minifair' ), [ 'response' => 500 ] );
	}
}

function render_edit_page( WP_Post $post ) {
	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'minifair' ) );
	}

	$did = DID::from_post( $post );
	$remote = fetch_did( $did );
	?>
	<p><?php esc_html_e( "PLC DIDs are used as your globally-unique package identifier.", 'minifair' ) ?></p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'DID', 'minifair' ); ?>
			</th>
			<td>
				<code><?php echo esc_html( $did->id ); ?></code>
				<p class="description"><?php esc_html_e( 'PLC DIDs are permanent, and publicly available in the PLC directory.', 'minifair' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Rotation Public Keys', 'minifair' ); ?>
			</th>
			<td>
				<ol>
					<?php foreach ( $did->get_rotation_keys() as $key ) : ?>
						<li><code><?php echo esc_html( $key->encode_public() ); ?></code></li>
					<?php endforeach; ?>
				</ol>
				<p class="description"><?php esc_html_e( 'Rotation keys are used to manage the DID itself.', 'minifair' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="recovery"><?php esc_html_e( 'Verification Public Keys', 'minifair' ); ?></label>
			</th>
			<td>
				<p class="description"><?php esc_html_e( 'Verification keys are used for package signing. Your newest (last) key is used for signing, but older keys are still used for verification. Revoking any key will invalidate any older packages which may be cached, so should only be done after some time (such as a week) has passed.', 'minifair' ); ?></p>
				<ol>
					<?php
					$verification_keys = $did->get_verification_keys();
					$last = end( $verification_keys );
					foreach ( $verification_keys as $key ) : ?>
						<?php
						$public = $key->encode_public();
						$id = substr( hash( 'sha256', $public ), 0, 6 );
						?>
						<li>
							<code>fair_<?= esc_html( $id ); ?></code>:
							<code><?= esc_html( $public ); ?></code>
							<?php if ( $key instanceof Keys\ECKey ) : ?>
								<p><small><em>(Key is using outdated algorithm and should be replaced.)</em></small></p>
							<?php endif; ?>
							<?php if ( $key === $last ): ?>
								<p><small><strong>Current</strong></small></p>
							<?php endif; ?>

							<form action="" method="post">
								<?php wp_nonce_field( NONCE_PREFIX . ACTION_KEY_REVOKE ); ?>
								<input type="hidden" name="post" value="<?= esc_attr( $post->ID ); ?>" />
								<input type="hidden" name="action" value="<?= esc_attr( ACTION_KEY_REVOKE ); ?>" />
								<input type="hidden" name="key_id" value="<?= esc_attr( $key->encode_public() ); ?>" />
								<?php
								$disabled = count( $verification_keys ) === 1
									? [
										'disabled' => 'disabled',
										'title' => __( 'You must have at least one verification key.', 'minifair' ),
									]
									: [];

								submit_button(
									__( 'Revoke', 'minifair' ),
									'',
									'revoke_verification_key',
									true,
									$disabled
								); ?>
							</form>
						</li>
					<?php endforeach; ?>
				</ol>
				<form action="" method="post">
					<?php wp_nonce_field( NONCE_PREFIX . ACTION_KEY_ADD ); ?>
					<input type="hidden" name="post" value="<?= esc_attr( $post->ID ); ?>" />
					<input type="hidden" name="action" value="<?= esc_attr( ACTION_KEY_ADD ); ?>" />
					<?php submit_button( __( 'Add new key', 'minifair' ), '', 'add_verification_key' ); ?>
				</form>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'DID Document', 'minifair' ); ?>
			</th>
			<td>
				<pre><?php echo esc_html( json_encode( $remote, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
				<p class="description">
					<?php
					printf(
						__( 'Current DID Document in the <a href="%s">PLC Directory</a>.', 'minifair' ),
						'https://web.plc.directory/did/' . $did->id
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Sync to PLC Directory', 'minifair' ); ?>
			</th>
			<td>
				<p><?php esc_html_e( 'If the service endpoint or keys have changed, you can resync to the PLC Directory.', 'minifair' ); ?></p>
				<details>
					<summary><?php esc_html_e( 'Expected changes', 'minifair' ); ?></summary>
					<?php
					$current = $remote;
					unset( $current['@context'] );
					$diff = wp_text_diff(
						json_encode( $current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
						json_encode( $did->get_expected_document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
					);
					if ( empty( $diff ) ) {
						echo '<p class="description">' . esc_html__( 'No changes detected. The PLC Directory is already up to date.', 'minifair' ) . '</p>';
					} else {
						echo '<div class="diff">' . $diff . '</div>';
					}
					?>
				</details>
				<form action="" method="post">
					<?php wp_nonce_field( NONCE_PREFIX . ACTION_SYNC ); ?>
					<input type="hidden" name="post" value="<?php echo esc_attr( $post->ID ); ?>" />
					<input type="hidden" name="action" value="<?= esc_attr( ACTION_SYNC ) ?>" />
					<?php submit_button( __( 'Sync to PLC Directory', 'minifair' ), 'primary', 'update_did' ); ?>
				</form>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Regenerate Signatures', 'minifair' ); ?>
			</th>
			<td>
				<p><?php esc_html_e( 'After generating a new key, regenerate artifact signatures to use the new key. Without regeneration, only new artifacts will use the new keys.', 'minifair' ) ?></p>
				<form action="" method="post">
					<?php wp_nonce_field( NONCE_PREFIX . ACTION_RESIGN ); ?>
					<input type="hidden" name="post" value="<?= esc_attr( $post->ID ); ?>" />
					<input type="hidden" name="action" value="<?= esc_attr( ACTION_RESIGN ); ?>" />
					<?php submit_button( __( 'Regenerate signatures', 'minifair' ), '', 'regenerate_signatures' ); ?>
				</form>
			</td>
		</tr>
	</table>

	<?php
}
