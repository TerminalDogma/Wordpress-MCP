<?php
/**
 * Template Name: ComingSoon Detail Preview
 * Template Post Type: page, post
 */

require_once __DIR__ . '/inc/comingsoon-preview.php';

$preview = get_queried_object();
$is_movie = 'page' === $preview->post_type;
$kind = $is_movie ? 'Movie page' : 'Article';
$variant = msc_cs_preview_meta( $preview, 'detail_variant' );
$variant = in_array( $variant, array( 'b', 'c' ), true ) ? $variant : 'a';
$homepage = get_page_by_path( 'homepage-preview' . ( 'a' === $variant ? '' : '-' . $variant ) );
$home_id = $homepage && 'publish' === $homepage->post_status && 'home' === msc_cs_preview_meta( $homepage, 'preview' ) ? $homepage->ID : 0;
$prefix = $is_movie ? 'movie-page-preview-' : 'article-preview-';
$previews = get_option( 'msc_comingsoon_detail_previews', array() );
$companion = $previews[ ( $is_movie ? 'article-preview-' : 'movie-page-preview-' ) . $variant ] ?? 0;
$dependencies = array( 'a' => 'cs-editorial-preview', 'b' => 'cs-editorial-preview-b', 'c' => 'cs-editorial-preview-c' );
wp_enqueue_style( 'cs-detail-preview', get_template_directory_uri() . '/assets/comingsoon/detail-preview.css', array( $dependencies[ $variant ] ), '1.0.0' );

get_header( 'comingsoon-preview', array(
	'preview_home' => $home_id,
	'preview_variant' => $variant,
	'preview_label' => $kind . ' preview ' . strtoupper( $variant ),
) );
?>
<main id="main" class="cs-frame cs-detail-preview">
	<nav class="cs-preview-links" aria-label="Compare designs">
		<span><?php echo esc_html( $kind . ' designs' ); ?></span>
		<?php foreach ( array( 'a', 'b', 'c' ) as $letter ) {
			$id = $previews[ $prefix . $letter ] ?? 0;
			if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
			?>
			<a href="<?php echo esc_url( get_permalink( $id ) ); ?>"<?php if ( $preview->ID === $id ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( strtoupper( $letter ) ); ?></a>
		<?php } ?>
		<?php if ( $companion && 'publish' === get_post_status( $companion ) ) { ?><a class="cs-preview-companion" href="<?php echo esc_url( get_permalink( $companion ) ); ?>"><?php echo esc_html( $is_movie ? 'Article preview' : 'Movie page preview' ); ?> <span aria-hidden="true">→</span></a><?php } ?>
	</nav>
	<?php while ( have_posts() ) {
		the_post();
		if ( post_password_required() ) {
			echo get_the_password_form();
		} elseif ( $is_movie ) {
			$movie = get_page_by_path( 'iron-man', OBJECT, 'msc_movie' );
			$record = $movie ? msc_from_post( $movie->ID ) : null;
			$record = $record ? \MSC\Presentation::record( $record['id'] ) : null;
			if ( $record ) {
				get_template_part( 'parts/detail', 'movie', array( 'record' => $record, 'design_preview' => true ) );
				\MSC\Attribution::collect( $record );
			} else {
				?><h1>Iron Man</h1><p>This preview needs a published Iron Man entry on ComingSoon.</p><?php
			}
		} else {
			get_template_part( 'parts/comingsoon-detail-article', null, array( 'story' => $preview ) );
		}
	} ?>
</main>
<?php get_footer( 'comingsoon-preview', array(
	'preview_note' => $is_movie ? 'ComingSoon movie page design preview.' : 'Fictional news article for the ComingSoon design preview.',
	'sources' => $is_movie,
) ); ?>
