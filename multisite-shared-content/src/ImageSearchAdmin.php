<?php
namespace MSC;
final class ImageSearchAdmin {
	public static function boot(): void {
		add_action(
			'network_admin_menu',
			static function () {
				add_submenu_page( 'msc-network', 'Image Search', 'Image Search', 'manage_network_options', 'msc-image-search', array( self::class, 'page' ) );
			},
			20
		);
		add_action(
			'admin_post_msc_image_search_settings',
			static function () {
				check_admin_referer( 'msc_image_search_settings' );
				try {
					if ( ! is_string( $_POST['api_key'] ?? '' ) ) {
								throw new SourceError( 'Invalid image search key.' );
					}
					ImageSearch::save_key( wp_unslash( $_POST['api_key'] ?? '' ), ! empty( $_POST['clear'] ) );
					wp_safe_redirect( network_admin_url( 'admin.php?page=msc-image-search&saved=1' ) );
					exit;
				} catch ( \Throwable $e ) {
					wp_die(
						esc_html( $e->getMessage() ),
						'Image Search',
						array(
							'response'  => $e instanceof SourceError ? $e->status : 400,
							'back_link' => true,
						)
					);
				}
			}
		);
	}
	public static function page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'Network administrator permission required.' );
		}
		echo '<div class="wrap msc"><h1>Image Search</h1><p>Search Google Images and import a result directly inside the image fields in both editors. Serper supplies the results; the same private API key also powers YouTube trailer search in the trailer fields.</p><p><strong>Status: ' . ( ImageSearch::configured() ? 'Configured' : 'Not configured' ) . '</strong>' . ( ImageSearch::managed() ? ' · Managed by server configuration' : '' ) . '</p>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success inline"><p>Image search settings saved.</p></div>';
		}
		if ( ! ImageSearch::managed() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_image_search_settings">';
			wp_nonce_field( 'msc_image_search_settings' );
			echo '<table class="form-table"><tr><th><label for="msc-serper-key">Serper API key</label></th><td><input type="password" id="msc-serper-key" name="api_key" value="" autocomplete="new-password" class="regular-text"><p class="description">A blank field retains the saved key. Saved keys are never displayed.</p></td></tr></table><p><label><input type="checkbox" name="clear" value="1"> Remove saved key</label></p>';
			submit_button( 'Save image search settings' );
			echo '</form>';
		}
		echo '<p>Get a key from <a href="https://serper.dev/" target="_blank" rel="noopener noreferrer">Serper</a>. Its trial currently includes 2,500 queries. Searches use your provider account credits; repeated results are cached.</p><p>In an image field, choose Google Images, search, then select Import image. The image and its original source page are added to the media library. Save the record to apply it.</p></div>';
	}
}
