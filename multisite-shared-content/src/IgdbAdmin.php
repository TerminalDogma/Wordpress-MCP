<?php
namespace MSC;
final class IgdbAdmin {
    public static function boot(): void {
        add_action( 'network_admin_menu', static function () { add_submenu_page( 'msc-network', 'IGDB API', 'IGDB API', 'manage_network_options', 'msc-igdb-settings', array( self::class, 'page' ) ); }, 20 );
        add_action( 'admin_post_msc_igdb_settings', static function () { check_admin_referer( 'msc_igdb_settings' ); try { IgdbClient::save( is_string( $_POST['client_id'] ?? null ) ? wp_unslash( $_POST['client_id'] ) : '', is_string( $_POST['client_secret'] ?? null ) ? wp_unslash( $_POST['client_secret'] ) : '', ! empty( $_POST['clear'] ) ); wp_safe_redirect( network_admin_url( 'admin.php?page=msc-igdb-settings&saved=1' ) ); exit; } catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'IGDB settings', array( 'response' => $e instanceof SourceError ? $e->status : 400, 'back_link' => true ) ); } } );
    }
    public static function page(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { wp_die( 'Network permission required.' ); }
        echo '<div class="wrap msc"><h1>IGDB API</h1><p>Search Video Games through Shared Content → Import Content. Credentials and Twitch access tokens stay on the server.</p><p><strong>Status: ' . ( IgdbClient::configured() ? 'Configured' : 'Not configured' ) . '</strong>' . ( IgdbClient::managed() ? ' · Managed by server configuration' : '' ) . '</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success inline"><p>IGDB settings saved.</p></div>'; }
        if ( ! IgdbClient::managed() ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_igdb_settings">'; wp_nonce_field( 'msc_igdb_settings' ); echo '<table class="form-table"><tr><th><label for="msc-igdb-client">Twitch Client ID</label></th><td><input id="msc-igdb-client" name="client_id" autocomplete="off" value="" class="regular-text"></td></tr><tr><th><label for="msc-igdb-secret">Twitch Client Secret</label></th><td><input id="msc-igdb-secret" type="password" name="client_secret" autocomplete="new-password" value="" class="regular-text"><p>Blank fields retain the saved credentials. Saved values are never displayed.</p></td></tr></table><p><label><input type="checkbox" name="clear" value="1"> Remove saved credentials</label></p>'; submit_button( 'Save IGDB settings' ); echo '</form>'; }
        echo '<p>Create a confidential Twitch application following <a href="https://api-docs.igdb.com/#account-creation">IGDB’s setup instructions</a>. IGDB documents free commercial access through its <a href="https://api-docs.igdb.com/#partnership">partner program</a> with user-facing attribution. The game footer supplies that attribution when IGDB data is displayed.</p></div>';
    }
}
