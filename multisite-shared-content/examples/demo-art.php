<?php
/** Original geometric artwork generated locally; no remote image dependency. */
function msc_demo_art( string $kind ): array {
	$saved = get_option( 'msc_demo_art', array() );
	if ( ! empty( $saved[ $kind ] ) && get_post_type( $saved[ $kind ] ) === 'attachment' && is_file( get_attached_file( $saved[ $kind ] ) ?: '' ) ) {
		return array(
			'site_id'       => get_current_blog_id(),
			'attachment_id' => (int) $saved[ $kind ],
		);
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		throw new RuntimeException( 'The demo artwork requires PHP GD, included in the Docker image.' );
	}
	$portrait         = in_array( $kind, array( 'actor', 'director' ), true );
	$w                = 'episode' === $kind ? 1200 : 720;
	$h                = 'episode' === $kind ? 750 : ( $portrait ? 900 : 1080 );
	$im               = imagecreatetruecolor( $w, $h );
	$color            = static fn( $r, $g, $b ) => imagecolorallocate( $im, $r, $g, $b );
	$palettes         = array(
		'movie'    => array( array( 17, 29, 54 ), array( 154, 79, 62 ) ),
		'show'     => array( array( 15, 32, 47 ), array( 60, 114, 116 ) ),
		'episode'  => array( array( 18, 37, 58 ), array( 78, 128, 128 ) ),
		'actor'    => array( array( 225, 189, 141 ), array( 170, 103, 75 ) ),
		'director' => array( array( 164, 167, 165 ), array( 90, 117, 116 ) ),
	);
	[ $top, $bottom ] = $palettes[ $kind ];
	for ( $y = 0; $y < $h; ++$y ) {
		$t = $y / $h;
		imageline( $im, 0, $y, $w, $y, $color( ...array_map( static fn( $a, $b ) => (int) ( $a + ( $b - $a ) * $t ), $top, $bottom ) ) );
	}
	if ( $portrait ) {
		// Intentionally illustrative profile silhouettes of fictional people.
		$skin  = $color( 205, 149, 104 );
		$shade = $color( 161, 105, 79 );
		$hair  = $color( 36, 39, 43 );
		$coat  = $color( 38, 55, 61 );
		imagefilledellipse( $im, 370, 360, 290, 370, $hair );
		imagefilledellipse( $im, 374, 395, 230, 315, $skin );
		imagefilledrectangle( $im, 327, 493, 420, 660, $shade );
		imagefilledellipse( $im, 370, 875, 660, 610, $coat );
		imagefilledellipse( $im, 390, 385, 215, 260, $skin );
		imagefilledarc( $im, 360, 318, 270, 260, 175, 355, $hair, IMG_ARC_PIE );
		imagefilledellipse( $im, 417, 387, 15, 9, $hair );
		imageline( $im, 413, 467, 445, 462, $shade );
		if ( 'director' === $kind ) {
			imagesetthickness( $im, 5 );
			imageellipse( $im, 416, 392, 72, 48, $hair );
			imageline( $im, 375, 390, 305, 370, $hair );
		}
	} else {
		// Stars, a glowing sun, layered mountains, and a solitary observer.
		for ( $i = 0; $i < 140; ++$i ) {
			$x = ( $i * 173 + 41 ) % $w;
			$y = ( $i * 97 + 23 ) % (int) ( $h * .58 );
			imagefilledellipse( $im, $x, $y, $i % 5 === 0 ? 3 : 1, $i % 5 === 0 ? 3 : 1, $color( 160, 185, 190 ) );
		}
		$sx       = (int) ( $w * .55 );
		$sy       = (int) ( $h * .36 );
		$diameter = (int) ( $w * .42 );
		for ( $i = $diameter + 90; $i > $diameter; --$i ) {
			$alpha = min( 127, 110 + (int) ( 17 * ( $i - $diameter ) / 90 ) );
			imagefilledellipse( $im, $sx, $sy, $i, $i, imagecolorallocatealpha( $im, 255, 168, 94, $alpha ) );
		}
		imagefilledellipse( $im, $sx, $sy, $diameter, $diameter, 'movie' === $kind ? $color( 244, 186, 112 ) : $color( 178, 208, 188 ) );
		for ( $layer = 0; $layer < 4; ++$layer ) {
			$base   = (int) ( $h * ( .55 + .11 * $layer ) );
			$points = array( 0, $h, 0, $base );
			for ( $j = 1; $j <= 12; ++$j ) {
				$points[] = (int) ( $j * $w / 12 );
				$points[] = $base - ( ( $j * 37 + $layer * 51 ) % (int) ( $h * .16 ) );
			}
			$points[] = $w;
			$points[] = $h;
			imagefilledpolygon( $im, $points, $color( 27 - 4 * $layer, 56 - 9 * $layer, 68 - 9 * $layer ) );
		}
		$px   = (int) ( $w * .43 );
		$py   = (int) ( $h * .81 );
		$dark = $color( 8, 18, 24 );
		imagefilledellipse( $im, $px, $py, 23, 27, $dark );
		imagefilledrectangle( $im, $px - 12, $py + 11, $px + 12, $py + 75, $dark );
		imagefilledpolygon( $im, array( $px - 11, $py + 70, $px - 17, $py + 112, $px - 7, $py + 112, $px, $py + 80, $px + 6, $py + 112, $px + 16, $py + 112, $px + 11, $py + 70 ), $dark );
	}
	$uploads = wp_upload_dir();
	if ( $uploads['error'] ) {
		throw new RuntimeException( $uploads['error'] );
	}
	$name = wp_unique_filename( $uploads['path'], 'msc-demo-' . $kind . '.jpg' );
	$path = $uploads['path'] . '/' . $name;
	imagejpeg( $im, $path, 89 );
	imagedestroy( $im );
	$id = wp_insert_attachment(
		array(
			'post_title'     => 'Screening Room — ' . ucfirst( $kind ) . ' illustration',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		),
		$path,
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Original geometric ' . $kind . ' illustration for the fictional Screening Room demo' );
	$saved[ $kind ] = $id;
	update_option( 'msc_demo_art', $saved );
	return array(
		'site_id'       => get_current_blog_id(),
		'attachment_id' => (int) $id,
	);
}
