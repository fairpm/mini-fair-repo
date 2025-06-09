<?php

namespace MiniFAIR\Admin;

use MiniFAIR;
use MiniFAIR\PLC\DID;
use MiniFAIR\Keys;
use WP_Post;

const NONCE_CREATE_ACTION = 'minifair_create';
const NONCE_SYNC_ACTION = 'minifair_sync';
const PAGE_SLUG = 'minifair';

function bootstrap() {
	// Register the admin menu and page before the PLC DID post type is registered.
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu', 0 );
	add_action( 'post_action_sync', __NAMESPACE__ . '\\maybe_on_sync' );

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

function fetch_did( DID $did ) {
	$url = DID::DIRECTORY_API . '/' . $did->id;
	$res = wp_remote_get( $url );
	return json_decode( $res['body'], true );
}

function render_editor() {
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

function render_new_page( WP_Post $post ) {
	// Check user permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'minifair' ) );
	}

	if ( isset( $_POST['action'] ) ) {
		if ( $_POST['action'] !== 'create' ) {
			wp_die( __( 'Invalid action.', 'minifair' ) );
		}

		check_admin_referer( NONCE_CREATE_ACTION );

		// Handle the form submission to create a new PLC DID.
		$did = DID::create();
		if ( is_wp_error( $did ) ) {
			echo '<div class="error"><p>' . esc_html( $did->get_error_message() ) . '</p></div>';
		} else {
			wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
			exit;
		}
	}

	?>
	<p><?php esc_html_e( "PLC DIDs are used as your globally-unique package identifier. You can create one here if you're publishing a new package.", 'minifair' ) ?></p>
	<p><?php esc_html_e( 'PLC DIDs are permanent, and publicly available in the PLC directory.', 'minifair' ) ?></p>

	<form action="" method="post">
		<?php wp_nonce_field( NONCE_CREATE_ACTION ) ?>
		<input type="hidden" name="post" value="<?php echo esc_attr( $post->ID ); ?>" />
		<input type="hidden" name="action" value="create" />

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

function maybe_on_sync( int $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== DID::POST_TYPE ) {
		return;
	}

	// Handle the form submission to sync the PLC DID with the PLC Directory.
	check_admin_referer( NONCE_SYNC_ACTION );

	$did = DID::from_post( $post );
	try {
		$did->update();
		wp_redirect( get_edit_post_link( $did->get_internal_post_id(), 'raw' ) );
		exit;
	} catch ( \Exception $e ) {
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
						<li><code><?php echo esc_html( Keys\encode_public_key( $key, Keys\CURVE_K256 ) ); ?></code></li>
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
				<ol>
					<?php foreach ( $did->get_verification_keys() as $key ) : ?>
						<li><code><?php echo esc_html( Keys\encode_public_key( $key, Keys\CURVE_K256 ) ); ?></code></li>
					<?php endforeach; ?>
				</ol>
				<p class="description"><?php esc_html_e( 'Verification keys are used for package signing.', 'minifair' ); ?></p>
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
				<form action="" method="post">
					<?php wp_nonce_field( NONCE_SYNC_ACTION ); ?>
					<input type="hidden" name="post" value="<?php echo esc_attr( $post->ID ); ?>" />
					<input type="hidden" name="action" value="sync" />
					<?php submit_button( __( 'Sync to PLC Directory', 'minifair' ), 'primary', 'update_did' ); ?>
				</form>
			</td>
		</tr>
	</table>

	<?php
}
