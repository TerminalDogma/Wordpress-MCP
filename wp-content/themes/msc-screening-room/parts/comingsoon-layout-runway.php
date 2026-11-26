<?php
use MSC\{NetworkSites, ReleaseCalendar};

$periods = array( '14' => '14 days', '30' => '30 days', '60' => '60 days' );
$period = is_string( $_GET['window'] ?? null ) ? sanitize_key( wp_unslash( $_GET['window'] ) ) : '';
$period = isset( $periods[ $period ] ) ? $period : '30';
$today = current_datetime();
$end = $today->modify( '+' . ( (int) $period - 1 ) . ' days' );
$period_format = $today->format( 'Y' ) === $end->format( 'Y' ) ? 'M j' : 'M j, Y';
$grouped = array();
$count = 0;
for ( $month = $today->modify( 'first day of this month' ); $month <= $end && $count < 12; $month = $month->modify( '+1 month' ) ) {
	foreach ( ReleaseCalendar::data( $month->format( 'Y-m' ) )['events'] as $event ) {
		if ( $event['date'] < $today->format( 'Y-m-d' ) || $event['date'] > $end->format( 'Y-m-d' ) || ! in_array( $event['type'], array( 'msc_movie', 'msc_show', 'msc_episode' ), true ) ) { continue; }
		$grouped[ $event['date'] ][] = $event;
		if ( ++$count >= 12 ) { break; }
	}
}
$dispatches = array_slice( array_values( array_filter( $args['stories'], static fn( WP_Post $story ) => 'latest' === msc_cs_preview_meta( $story, 'section' ) ) ), 0, 5 );
?>
<header class="runway-title">
	<h1><?php echo esc_html( $args['title'] ); ?></h1>
	<div class="runway-period">
		<p>Upcoming movies &amp; TV</p>
		<strong><?php echo esc_html( wp_date( $period_format, $today->getTimestamp() ) . ' – ' . wp_date( $period_format, $end->getTimestamp() ) ); ?></strong>
	</div>
</header>
<div class="runway-layout">
	<section class="runway-board" aria-labelledby="runway-board-title">
		<div class="runway-board-head">
			<h2 id="runway-board-title">Release Schedule</h2>
			<nav aria-label="Release window" class="layout-tabs">
				<?php foreach ( $periods as $value => $label ) { ?>
					<a href="<?php echo esc_url( add_query_arg( 'window', $value, $args['url'] ) ); ?>"<?php if ( $period === (string) $value ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $label ); ?></a>
				<?php } ?>
			</nav>
		</div>
		<?php if ( ! $grouped ) { ?><p class="runway-empty">The catalog has no dated releases in this window.</p><?php } ?>
		<?php foreach ( $grouped as $date => $events ) { $day = new DateTimeImmutable( $date, wp_timezone() ); ?>
			<div class="runway-day">
				<h3><time datetime="<?php echo esc_attr( $date ); ?>"><span><?php echo esc_html( wp_date( 'D, M', $day->getTimestamp() ) ); ?></span><strong><?php echo esc_html( wp_date( 'j', $day->getTimestamp() ) ); ?></strong></time></h3>
				<ul class="runway-events">
					<?php foreach ( $events as $event ) { ?>
						<li>
							<div><a href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $event['show_title'] ? $event['show_title'] . ': ' . $event['title'] : $event['title'] ); ?></a></div>
							<div class="runway-event-kind"><span><?php echo esc_html( $event['kind'] ); ?></span>
								<?php foreach ( $event['services'] as $service ) { ?><span class="runway-service"><?php if ( $service['logo'] ) { ?><img src="<?php echo esc_url( $service['logo'] ); ?>" width="32" height="17" loading="lazy" alt=""><?php } ?><?php echo esc_html( $service['title'] ); ?></span><?php } ?>
							</div>
						</li>
					<?php } ?>
				</ul>
			</div>
		<?php } ?>
		<a class="runway-calendar" href="<?php echo esc_url( NetworkSites::calendar_url() ); ?>">Open the full release calendar <span aria-hidden="true">↗</span></a>
	</section>
	<?php if ( $dispatches ) { ?>
		<aside class="runway-dispatches" aria-labelledby="runway-news">
			<h2 id="runway-news">Latest Headlines</h2>
			<?php foreach ( $dispatches as $story ) { ?>
				<article>
					<p class="layout-label"><?php echo esc_html( msc_cs_preview_meta( $story, 'label' ) ); ?></p>
					<h3><a href="<?php echo esc_url( get_permalink( $story ) ); ?>"><?php echo esc_html( get_the_title( $story ) ); ?></a></h3>
				</article>
			<?php } ?>
		</aside>
	<?php } ?>
</div>
<?php $watch = array_slice( array_values( array_filter( $args['stories'], static fn( WP_Post $story ) => 'streaming' === msc_cs_preview_format( $story ) ) ), 0, 3 ); ?>
<?php if ( $watch ) { ?>
	<section class="runway-watch" aria-labelledby="runway-watch-title">
		<h2 id="runway-watch-title">What to Watch Now</h2>
		<div>
			<?php foreach ( $watch as $story ) { ?>
				<a href="<?php echo esc_url( get_permalink( $story ) ); ?>">
					<?php msc_cs_preview_art( $story ); ?>
					<strong><?php echo esc_html( get_the_title( $story ) ); ?></strong>
				</a>
			<?php } ?>
		</div>
	</section>
<?php } ?>
