<?php
$design = $args['palette'];
$lead = $args['lead'];
$sections = $args['sections'];
$support = match ( $design ) {
	'signal' => $args['spotlight'],
	'zine' => array_slice( $sections['features'] ?? array(), 0, 3 ),
	'disc' => array_slice( $sections['trailers'] ?? array(), 0, 3 ),
};
$headlines = array_slice( $args['latest'], 0, 4 );
?>
<div class="cs-frame cs-exp-home">
	<h1 class="screen-reader-text">ComingSoon</h1>
	<div class="cs-exp-home-stage">
		<?php if ( $lead ) { ?>
			<section class="cs-exp-feature" aria-label="Featured story">
				<article>
					<a class="cs-exp-feature-link" href="<?php echo esc_url( get_permalink( $lead ) ); ?>">
						<div class="cs-exp-feature-art"><?php msc_cs_preview_art( $lead, true ); ?></div>
						<div class="cs-exp-feature-copy">
							<p class="cs-exp-label"><?php echo esc_html( msc_cs_preview_meta( $lead, 'label' ) ); ?></p>
							<h2><?php echo esc_html( get_the_title( $lead ) ); ?></h2>
							<?php if ( $lead->post_excerpt ) { ?><p class="cs-exp-deck"><?php echo esc_html( $lead->post_excerpt ); ?></p><?php } ?>
							<span class="cs-exp-read">Read the story <span aria-hidden="true">↗</span></span>
						</div>
					</a>
				</article>
			</section>
		<?php } ?>
		<?php if ( 'signal' === $design ) { ?>
			<section class="cs-exp-wire" aria-labelledby="exp-wire-title">
				<h2 id="exp-wire-title">News Wire</h2>
				<?php foreach ( $headlines as $story ) { msc_cs_preview_card( $story, 'brief' ); } ?>
				<a class="cs-exp-read" href="#the-feed">All headlines ↓</a>
			</section>
		<?php } else { ?>
			<section class="cs-exp-clippings" aria-labelledby="exp-clippings-title">
				<h2 id="exp-clippings-title"><?php echo 'zine' === $design ? 'Interviews &amp; Features' : 'Trailers &amp; First Looks'; ?></h2>
				<?php foreach ( $support as $story ) { msc_cs_preview_card( $story, 'grid-highlight' ); } ?>
			</section>
		<?php } ?>
	</div>

	<div class="cs-exp-home-rows">
		<section class="cs-exp-home-desk" aria-labelledby="exp-desk-title">
			<h2 id="exp-desk-title"><?php echo 'signal' === $design ? 'In Focus' : 'Latest Headlines'; ?></h2>
			<div class="cs-exp-story-grid">
				<?php foreach ( 'signal' === $design ? $support : $headlines as $story ) { msc_cs_preview_card( $story, 'grid-highlight' ); } ?>
			</div>
		</section>
		<section class="cs-exp-home-desk" aria-labelledby="exp-watch-title">
			<h2 id="exp-watch-title">What to Watch</h2>
			<?php foreach ( array_slice( $args['picks'], 0, 3 ) as $story ) { msc_cs_preview_card( $story, 'brief' ); } ?>
			<a class="cs-exp-read" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Release dates ↗</a>
		</section>
	</div>
</div>
