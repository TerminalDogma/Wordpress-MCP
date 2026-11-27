<?php
use MSC\{Attribution, NetworkSites, Presentation, ReviewScores, Reviews};

$releases = $args['releases'] ?? array();
if ( ! $releases ) { return; }

$compact = ! empty( $args['compact'] );
$spotlight = $compact ? null : array_shift( $releases );
$card = static function ( array $release, bool $featured = false ) use ( $compact ): void {
	$record = $release['record'];
	$poster = Presentation::artwork( $record, 'medium_large' );
	$review = Reviews::own( $record );
	if ( $review && get_post_meta( $review['review_id'], '_msc_movie_preview', true ) ) { $review['name'] = 'Sample review score'; }
	$score = $review ? ReviewScores::badge_row( $review ) : '';
	$date = new DateTimeImmutable( $release['date'], wp_timezone() );
	?>
	<a class="cs-release-card<?php if ( $featured ) { ?> cs-release-card--spotlight<?php } ?>" href="<?php echo esc_url( $release['url'] ); ?>">
		<span class="cs-release-poster">
			<?php if ( $poster ) { ?>
				<img src="<?php echo esc_url( $poster['url'] ); ?>" alt="" loading="lazy" decoding="async" width="180" height="270">
			<?php } else { ?>
				<span aria-hidden="true" class="cs-release-initial"><?php echo esc_html( mb_substr( $release['title'], 0, 1 ) ); ?></span>
			<?php } ?>
			<?php if ( ! $compact ) { echo $score; } ?>
		</span>
		<div class="cs-release-copy">
			<?php if ( ! empty( $release['pinned'] ) || $featured ) { ?><p class="cs-release-feature-label"><?php echo empty( $release['pinned'] ) ? 'Release Spotlight' : 'Editor’s Pick'; ?></p><?php } ?>
			<h3><?php echo esc_html( $release['title'] ); ?></h3>
			<p class="cs-release-date"><time datetime="<?php echo esc_attr( $release['date'] ); ?>"><?php echo esc_html( wp_date( $featured ? 'M j, Y' : 'M j', $date->getTimestamp() ) ); ?></time><span><?php echo esc_html( $release['kind'] ); ?></span></p>
			<?php if ( $compact ) { echo $score; } elseif ( $featured ) { ?><span class="cs-release-view">View movie <span aria-hidden="true">→</span></span><?php } ?>
		</div>
	</a>
	<?php
	Attribution::collect( $record, array( 'title', $release['field'] ) );
	if ( $poster ) { Attribution::collect_media( $poster['reference'], $release['title'] ); }
};
$controls = static function (): void {
	?>
	<div class="cs-releases-controls" data-release-controls hidden>
		<button type="button" aria-label="Previous releases" aria-controls="cs-release-track" data-release-prev>←</button>
		<button type="button" aria-label="Next releases" aria-controls="cs-release-track" data-release-next>→</button>
	</div>
	<?php
};
?>
<section class="cs-releases<?php if ( ! empty( $args['frame'] ) ) { ?> cs-frame<?php } ?><?php if ( $compact ) { ?> cs-releases--compact<?php } ?><?php if ( ! empty( $args['top'] ) ) { ?> cs-releases--top<?php } ?>" aria-labelledby="cs-releases-title" data-cs-releases>
	<div class="cs-releases-heading">
		<h2 id="cs-releases-title">Latest &amp; Upcoming Movies</h2>
		<div class="cs-releases-links">
			<a href="<?php echo esc_url( NetworkSites::calendar_url() ); ?>">Release calendar</a>
			<a class="cs-releases-database" href="<?php echo esc_url( get_post_type_archive_link( 'msc_movie' ) ); ?>">Movie database <span aria-hidden="true">→</span></a>
			<?php if ( $compact && count( $releases ) > 1 ) { $controls(); } ?>
		</div>
	</div>
	<div class="cs-releases-display<?php if ( ! $releases ) { ?> cs-releases-display--single<?php } ?>">
		<?php if ( $spotlight ) { $card( $spotlight, true ); } ?>
		<?php if ( $releases ) { ?>
			<div class="cs-releases-carousel" aria-roledescription="carousel" role="group" aria-label="<?php echo $compact ? 'Movie releases' : 'More movie releases'; ?>">
				<?php if ( ! $compact ) { ?>
					<div class="cs-releases-carousel-header">
						<p>More Releases</p>
						<?php $controls(); ?>
					</div>
				<?php } ?>
				<ul id="cs-release-track" class="cs-release-track" aria-label="Movie releases" tabindex="0" data-release-track>
					<?php foreach ( $releases as $index => $release ) { ?><li><?php $card( $release, $compact && 0 === $index ); ?></li><?php } ?>
				</ul>
			</div>
		<?php } ?>
	</div>
</section>
