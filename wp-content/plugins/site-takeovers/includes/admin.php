<?php
namespace SiteTakeovers;

final class Admin {
    public static function boot(): void {
        add_action( 'add_meta_boxes_' . Plugin::TYPE, static function (): void {
            add_meta_box( 'sat-placement', 'Placement and palette', array( self::class, 'placement' ), Plugin::TYPE, 'normal', 'high' );
            add_meta_box( 'sat-creative', 'Advertising artwork and links', array( self::class, 'creative' ), Plugin::TYPE, 'normal', 'high' );
            add_meta_box( 'sat-schedule', 'Publishing period', array( self::class, 'schedule' ), Plugin::TYPE, 'side', 'high' );
        } );
        add_action( 'admin_enqueue_scripts', static function (): void {
            $screen = get_current_screen();
            if ( Plugin::TYPE !== ( $screen->post_type ?? '' ) || 'post' !== $screen->base ) { return; }
            wp_enqueue_media();
            wp_enqueue_script( 'site-takeovers-admin', plugins_url( 'assets/admin.js', dirname( __DIR__ ) . '/site-takeovers.php' ), array( 'media-editor' ), Plugin::VERSION, true );
        } );
        add_action( 'save_post_' . Plugin::TYPE, array( self::class, 'save' ) );
        add_action( 'admin_notices', static function (): void {
            if ( Plugin::TYPE === ( get_current_screen()->post_type ?? '' ) && isset( $_GET['sat_period_error'] ) ) {
                wp_admin_notice( 'Enter valid dates and times with the end later than the start. The previous campaign settings are still in effect.', array( 'type' => 'error' ) );
            }
        } );
        add_filter( 'manage_' . Plugin::TYPE . '_posts_columns', static function ( array $columns ): array {
            $columns['sat_scope'] = 'Placement';
            $columns['sat_period'] = 'Publishing period';
            return $columns;
        } );
        add_action( 'manage_' . Plugin::TYPE . '_posts_custom_column', static function ( string $column, int $id ): void {
            $saved = Plugin::read( $id );
            $scope = $saved['scope'] ?? 'off';
            if ( 'sat_period' === $column ) {
                if ( 'off' === $scope || 'publish' !== get_post_status( $id ) ) { echo '—'; return; }
                $window = $saved['window'] ?? array();
                echo esc_html( Schedule::state( $window, time() ) );
                foreach ( array( 'start' => 'Starts', 'end' => 'Ends' ) as $key => $label ) {
                    if ( isset( $window[ $key ] ) ) { echo '<br>' . esc_html( $label . ' ' . Schedule::format( $window[ $key ], 'M j, Y g:i a T' ) ); }
                }
                return;
            }
            if ( 'sat_scope' !== $column ) { return; }
            if ( 'pages' === $scope ) {
                $count = count( $saved['targets'] );
                echo esc_html( sprintf( _n( '%d page', '%d pages', $count ), $count ) );
            } else {
                echo 'site' === $scope ? 'Entire site' : 'Off';
            }
        }, 10, 2 );
    }

    public static function save( int $id ): void {
        $nonce = $_POST['sat_nonce'] ?? null;
        $input = $_POST['sat'] ?? null;
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'sat_save' ) || ! is_array( $input ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
        try {
            Plugin::save( $id, wp_unslash( $input ) );
        } catch ( \InvalidArgumentException $error ) {
            add_filter( 'redirect_post_location', static fn( string $url ): string => add_query_arg( 'sat_period_error', '1', $url ) );
        }
    }

    public static function schedule( \WP_Post $post ): void {
        $data = Plugin::read( $post->ID );
        $preview = Plugin::preview_url( $post->ID );
        foreach ( array( 'start' => 'Starts at', 'end' => 'Ends at' ) as $key => $label ) {
            $id = 'sat-window-' . $key;
            ?>
            <p><label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br>
                <input type="datetime-local" id="<?php echo esc_attr( $id ); ?>" name="sat[window][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( Schedule::format( $data['window'][ $key ] ?? null ) ); ?>"></p>
            <?php
        }
        ?>
        <p class="description">Times use <?php echo esc_html( wp_timezone_string() ); ?>. A blank start begins when published. A blank end runs until turned off.</p>
        <?php if ( $preview ) { ?>
            <p><a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener">Preview saved takeover</a></p>
        <?php } ?>
        <?php
    }

    public static function placement( \WP_Post $post ): void {
        wp_nonce_field( 'sat_save', 'sat_nonce' );
        $data = Plugin::read( (int) $post->ID );
        $targets = $data['targets'] ?? array();
        $scope = $data['scope'] ?? 'off';
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="sat-scope">Show on</label></th>
                <td><select name="sat[scope]" id="sat-scope">
                    <?php foreach ( array( 'off' => 'Off', 'site' => 'Entire site', 'pages' => 'Selected pages' ) as $key => $label ) { ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $scope ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php } ?>
                </select><p class="description">Publish to turn on a takeover. A selected-page campaign takes precedence over an entire-site campaign.</p></td>
            </tr>
            <tr>
                <th scope="row"><label for="sat-targets">Selected page URLs or IDs</label></th>
                <td>
                    <textarea id="sat-targets" name="sat[targets]" class="large-text" rows="4"><?php echo esc_textarea( implode( "\n", $targets ) ); ?></textarea>
                    <p class="description">Enter one public page, post or catalog permalink or post ID per line. Targets belong to this site.</p>
                    <?php if ( 'pages' === $scope && ! $targets ) { ?><p><strong>Enter a valid target to display this campaign.</strong></p><?php } ?>
                    <?php if ( $targets ) { ?><ul>
                        <?php foreach ( $targets as $target ) { if ( ! get_post( $target ) ) { continue; } ?>
                            <li><a href="<?php echo esc_url( get_permalink( $target ) ); ?>"><?php echo esc_html( get_the_title( $target ) ); ?></a></li>
                        <?php } ?>
                    </ul><?php } ?>
                </td>
            </tr>
            <?php foreach ( Plugin::COLORS as $key => list( $label, $default ) ) { ?>
                <tr><th scope="row"><label for="sat-color-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td><input id="sat-color-<?php echo esc_attr( $key ); ?>" type="color" name="sat[colors][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $data['colors'][ $key ] ?? $default ); ?>"></td>
                </tr>
            <?php } ?>
        </table>
        <?php
    }

    public static function creative( \WP_Post $post ): void {
        $data = Plugin::read( (int) $post->ID );
        ?>
        <p>Use finished ad artwork, or add a campaign logo or headline and copy over the artwork. Side ads display on wide screens.</p>
        <table class="form-table" role="presentation">
            <?php foreach ( array( 'destination' => 'Default destination URL', 'brand' => 'Brand line', 'headline' => 'Headline', 'tagline' => 'Tagline', 'message' => 'Supporting text', 'cta' => 'Button text' ) as $key => $label ) { ?>
                <tr><th scope="row"><label for="sat-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td><input id="sat-<?php echo esc_attr( $key ); ?>" name="sat[<?php echo esc_attr( $key ); ?>]" class="regular-text" type="<?php echo 'destination' === $key ? 'url' : 'text'; ?>" value="<?php echo esc_attr( $data[ $key ] ?? '' ); ?>"></td>
                </tr>
            <?php } ?>
            <?php foreach ( Plugin::SLOTS as $key => $label ) {
                $id = (int) ( $data['art'][ $key ] ?? 0 );
                ?>
                <tr><th scope="row"><?php echo esc_html( $label ); ?></th><td>
                    <div data-sat-media>
                        <input type="hidden" name="sat[art][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $id ); ?>">
                        <p data-sat-preview><?php if ( $id ) { echo wp_get_attachment_image( $id, 'thumbnail' ); } ?></p>
                        <button type="button" class="button" data-sat-choose>Choose image</button>
                        <button type="button" class="button" data-sat-remove<?php if ( ! $id ) { ?> hidden<?php } ?>>Remove image</button>
                    </div>
                    <?php if ( isset( Plugin::PLACEMENTS[ $key ] ) ) { ?>
                        <p><label>Destination URL <input type="url" class="regular-text" name="sat[links][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $data['links'][ $key ] ?? '' ); ?>" placeholder="Use default destination"></label></p>
                    <?php } ?>
                </td></tr>
            <?php } ?>
        </table>
        <?php
    }
}
