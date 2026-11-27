<?php
use MSC\{Presentation, ReleaseCalendar, Schema};

/** Choose dated releases from the current site's public catalog for the homepage previews. */
function msc_cs_releases(): array {
	if ( ! Schema::enabled( 'msc_movie' ) ) { return array(); }
	$today = current_datetime()->setTime( 0, 0 );
	$now = $today->format( 'Y-m-d' );
	$start = $today->modify( '-21 days' );
	$end = $today->modify( '+45 days' );
	$first_date = $start->format( 'Y-m-d' );
	$last_date = $end->format( 'Y-m-d' );
	$events = array();
	for ( $month = $start->modify( 'first day of this month' ); $month <= $end; $month = $month->modify( '+1 month' ) ) {
		foreach ( ReleaseCalendar::data( $month->format( 'Y-m' ), 'msc_movie' )['events'] as $event ) {
			if ( $event['date'] < $first_date || $event['date'] > $last_date ) { continue; }
			$day = new DateTimeImmutable( $event['date'], wp_timezone() );
			$upcoming = $event['date'] > $now;
			$distance = $today->diff( $day )->days;
			$rank = array( $upcoming && 'release_date' === $event['field'] ? 0 : ( $upcoming ? 2 : 1 ), $distance, $event['title'], $event['id'] );
			if ( ! isset( $events[ $event['uuid'] ] ) || $rank < $events[ $event['uuid'] ]['rank'] ) {
				$events[ $event['uuid'] ] = $event + compact( 'rank' );
			}
		}
	}
	if ( ! $events ) { return array(); }
	$pins = get_posts( array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 'has_password' => false, 'fields' => 'ids', 'numberposts' => -1, 'meta_key' => '_comingsoon_feature_release', 'meta_value' => '1' ) );
	foreach ( $pins as $post_id ) {
		$uuid = msc_from_post( $post_id )['uuid'] ?? '';
		if ( isset( $events[ $uuid ] ) ) { $events[ $uuid ]['pinned'] = true; }
	}
	uasort( $events, static fn( array $a, array $b ) => array( empty( $a['pinned'] ), ...$a['rank'] ) <=> array( empty( $b['pinned'] ), ...$b['rank'] ) );
	$releases = array();
	foreach ( $events as $event ) {
		$record = Presentation::record( $event['uuid'] );
		if ( ! $record ) { continue; }
		$releases[] = $event + compact( 'record' );
		if ( 8 === count( $releases ) ) { break; }
	}
	return $releases;
}

add_action( 'add_meta_boxes_msc_movie', static function (): void {
	if ( 'a' !== get_option( 'msc_site_identity' ) ) { return; }
	add_meta_box( 'msc-comingsoon-feature-release', 'ComingSoon Release Carousel', static function ( WP_Post $movie ): void {
		wp_nonce_field( 'msc_comingsoon_feature_release', 'msc_comingsoon_release_nonce' );
		?>
		<label><input type="checkbox" name="msc_comingsoon_feature_release" value="1" <?php checked( '1', get_post_meta( $movie->ID, '_comingsoon_feature_release', true ) ); ?>> Feature this release on ComingSoon</label>
		<p class="description">Featured movies appear first while their dates are in the carousel’s release window.</p>
		<?php
	}, 'msc_movie', 'side' );
} );

add_action( 'save_post_msc_movie', static function ( int $id ): void {
	$nonce = $_POST['msc_comingsoon_release_nonce'] ?? null;
	if ( 'a' !== get_option( 'msc_site_identity' ) || ! is_string( $nonce ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'msc_comingsoon_feature_release' ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	if ( '1' === ( $_POST['msc_comingsoon_feature_release'] ?? '' ) ) {
		update_post_meta( $id, '_comingsoon_feature_release', '1' );
	} else {
		delete_post_meta( $id, '_comingsoon_feature_release' );
	}
} );
