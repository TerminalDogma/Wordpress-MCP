<?php
/** Include from a participating site's single-msc_show.php or single-msc_episode.php. */
$r = msc_from_post( get_the_ID() );
if ( ! $r || 'publish' !== get_post_status() || 'active' !== $r['state'] ) { return; }
$data = msc_get_record( $r['uuid'] )['data'];
$url = msc_media_url( (array) ( $data['poster'] ?? $data['featured_image'] ?? array() ) );
?>
<article class="shared-show-card">
	<?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy"><?php endif; ?>
	<h1><?php echo esc_html( $data['title'] ?? '' ); ?></h1>
	<div><?php echo wp_kses_post( $data['excerpt'] ?? '' ); ?></div>
	<?php $next = msc_next_episode( $r['uuid'] ); if ( $next ) : ?>
		<p>Next episode: <?php echo esc_html( $next['data']['title'] . ' — ' . $next['data']['release_date'] ); ?></p>
	<?php endif; ?>
</article>
