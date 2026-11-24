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
            $plugin = dirname( __DIR__ ) . '/site-takeovers.php';
            wp_enqueue_style( 'site-takeovers-admin', plugins_url( 'assets/admin.css', $plugin ), array(), Plugin::VERSION );
            wp_enqueue_script( 'site-takeovers-admin', plugins_url( 'assets/admin.js', $plugin ), array( 'media-editor', 'jquery-ui-autocomplete' ), Plugin::VERSION, true );
            wp_localize_script( 'site-takeovers-admin', 'satAdmin', array( 'nonce' => wp_create_nonce( 'sat_lookup_' . get_current_blog_id() ) ) );
        } );
        add_action( 'save_post_' . Plugin::TYPE, array( self::class, 'save' ) );
        add_action( 'wp_ajax_sat_lookup', array( self::class, 'lookup' ) );
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
                $count = count( $saved['targets'] ?? array() );
                echo esc_html( sprintf( _n( '%d page', '%d pages', $count ), $count ) );
            } elseif ( 'tags' === $scope ) {
                $count = count( $saved['tags'] ?? array() );
                echo esc_html( sprintf( _n( '%d tag', '%d tags', $count ), $count ) );
            } else {
                echo 'site' === $scope ? 'Entire site' : 'Off';
            }
        }, 10, 2 );
    }

    private static function post_label( \WP_Post $post ): string {
        return html_entity_decode( get_the_title( $post ) ?: '(Untitled)', ENT_QUOTES | ENT_HTML5, 'UTF-8' )
            . ' — ' . ( get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type );
    }

    public static function lookup(): void {
        check_ajax_referer( 'sat_lookup_' . get_current_blog_id(), 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( null, 403 ); }
        $value = $_GET['term'] ?? '';
        $search = is_string( $value ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $value ) ) ), 0, 100 ) : '';
        if ( mb_strlen( $search ) < 2 ) { wp_send_json( array() ); }

        if ( 'tags' === ( $_GET['kind'] ?? '' ) ) {
            $terms = get_terms( array( 'taxonomy' => 'post_tag', 'search' => $search, 'hide_empty' => false, 'number' => 12 ) );
            $items = is_wp_error( $terms ) ? array() : array_map( static fn( \WP_Term $tag ): array => array( 'id' => $tag->term_id, 'label' => $tag->name ), $terms );
        } else {
            $posts = get_posts( array( 'post_type' => Plugin::public_types(), 'post_status' => 'publish', 'has_password' => false,
                's' => $search, 'search_columns' => array( 'post_title' ), 'numberposts' => 12, 'orderby' => 'relevance',
            ) );
            $items = array_map( static fn( \WP_Post $post ): array => array(
                'id' => $post->ID, 'label' => self::post_label( $post ), 'detail' => $post->post_name,
            ), $posts );
        }
        wp_send_json( $items );
    }

    private static function picker( string $scope, array $ids ): void {
        $posts = 'pages' === $scope;
        $input = $posts ? 'sat[post_ids][]' : 'sat[tags][]';
        ?>
        <div data-sat-picker="<?php echo esc_attr( $scope ); ?>" data-sat-input="<?php echo esc_attr( $input ); ?>">
            <input class="regular-text" id="sat-search-<?php echo esc_attr( $scope ); ?>" type="search" autocomplete="off" placeholder="<?php echo esc_attr( $posts ? 'Type an article or page title' : 'Type a tag name' ); ?>">
            <p class="description"><?php echo esc_html( $posts ? 'Type at least two letters, then choose a result. You can add more than one.' : 'Choose one or more existing tags. A page or article qualifies when it has any selected tag.' ); ?></p>
            <p class="description" data-sat-status role="status"></p>
            <ul class="sat-selected">
                <?php foreach ( $ids as $id ) {
                    $item = $posts ? get_post( $id ) : get_term( $id, 'post_tag' );
                    if ( ! $item || is_wp_error( $item ) ) { continue; }
                    $label = $posts ? self::post_label( $item ) : $item->name;
                    ?>
                    <li><input type="hidden" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $id ); ?>"><span><?php echo esc_html( $label ); ?></span>
                        <button class="button-link-delete" type="button" data-sat-remove-target aria-label="<?php echo esc_attr( 'Remove ' . $label ); ?>">Remove</button>
                    </li>
                <?php } ?>
            </ul>
        </div>
        <?php
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
                    <?php foreach ( array( 'off' => 'Off', 'site' => 'Entire site', 'pages' => 'Selected pages', 'tags' => 'Tagged pages' ) as $key => $label ) { ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $scope ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php } ?>
                </select><p class="description">Publish to turn on a takeover. Selected pages take precedence over tagged pages, then entire-site campaigns.</p></td>
            </tr>
            <tr data-sat-targets="pages">
                <th scope="row"><label for="sat-search-pages">Find articles and pages</label></th>
                <td>
                    <?php self::picker( 'pages', $targets ); ?>
                    <details><summary>Add by URL or ID</summary>
                        <textarea aria-label="Additional page URLs or IDs" name="sat[targets]" class="large-text" rows="3" placeholder="One URL or ID from this site per line"></textarea>
                    </details>
                </td>
            </tr>
            <tr data-sat-targets="tags">
                <th scope="row"><label for="sat-search-tags">Find tags</label></th>
                <td><?php self::picker( 'tags', $data['tags'] ?? array() ); ?></td>
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
