<?php
use MSC\{Attribution, CatalogArticles, EntityCatalog, Franchises, Media, MovieEditorial, Presentation, ReleaseCalendar, ReviewScores, Reviews, Timelines};

$record = $args['record'];
$data = $record['data'];
$title = $data['title'];
$release = ReleaseCalendar::date( $data['release_date'] ?? '' );
$year = $release ? substr( $release, 0, 4 ) : '';
$logline = $data['film_logline'] ?? '';
$credits = EntityCatalog::rows( $record );
$franchises = Franchises::related( $record );
$timelines = Timelines::related( $record );
$watch = MovieEditorial::watch( $data );
$review = Reviews::own( $record );
$sample_review = $review && get_post_meta( $review['review_id'], '_msc_movie_preview', true );
$scores = ReviewScores::rows( $record );
$backdrop = Media::url( (array) ( $data['featured_image'] ?? array() ), 'full' );
$trailer_url = MovieEditorial::link( $data['trailer_source_url'] ?? '' );
$latest = CatalogArticles::query( $record, 1 )->posts[0] ?? null;
if ( $latest && (int) get_post_time( 'U', true, $latest ) < time() - 90 * DAY_IN_SECONDS ) { $latest = null; }

$section = static function ( string $part, array $values ): string {
    ob_start();
    get_template_part( 'parts/' . $part, null, $values );
    return trim( (string) ob_get_clean() );
};
$sections = array(
    'overview' => array( 'Overview', $section( 'movie-overview', $args ) ),
    'trailers' => array( 'Trailers', $section( 'movie-trailers', $args ) ),
    'gallery' => array( 'Image gallery', $section( 'gallery', $args ) ),
    'credits' => array( 'Cast and crew', $section( 'movie-credits', $args + array( 'credits' => $credits ) ) ),
    'review-scores' => array( 'Review scores', $section( 'movie-scores', $args + array( 'scores' => $scores ) ) ),
    'franchise-position' => array( 'Franchise', $section( 'movie-franchise', $args + compact( 'franchises', 'timelines' ) ) ),
    'recent-articles' => array( 'Coverage', $section( 'movie-coverage', $args ) ),
    'movie-details' => array( 'Details', $section( 'movie-details', $args ) ),
);
$sections = array_filter( $sections, static fn( array $row ) => '' !== $row[1] );
$sidebar_keys = array( 'actors', 'directors', 'screenwriters', 'producers', 'creators', ...array_keys( MovieEditorial::CREW ) );
$side_credits = array_diff_key( $credits, array_flip( $sidebar_keys ) );
foreach ( $side_credits as &$row ) {
    if ( 'Distributors' === $row['label'] && 1 === count( $row['items'] ) ) { $row['label'] = 'Distributor'; }
}
unset( $row );
$website = MovieEditorial::link( $data['official_website'] ?? '' );
ob_start();
msc_screen_facts( $record, array( 'country' => 'Country', 'language' => 'Language', 'distributor' => 'Distributor', 'production_companies' => 'Production companies', 'streaming_service' => 'Streaming service' ) );
$side_facts = ob_get_clean() . EntityCatalog::sidebar( $record, $side_credits );
$directors = array_column( $credits['directors']['items'] ?? array(), 'title' );
if ( ! $directors && ! empty( $data['director'] ) ) { $directors = array( $data['director'] ); }
$stars = array_slice( array_column( $credits['actors']['items'] ?? array(), 'title' ), 0, 3 );
?>
<article class="screen-title movie-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span aria-hidden="true">/</span>
        <a href="<?php echo esc_url( get_post_type_archive_link( 'msc_movie' ) ); ?>">Movies</a><span aria-hidden="true">/</span>
        <span aria-current="page"><?php echo esc_html( $title ); ?></span>
    </nav>

    <header class="movie-hero">
        <?php if ( $backdrop ) { ?>
            <img class="movie-backdrop" src="<?php echo esc_url( $backdrop ); ?>" alt="" fetchpriority="high">
            <?php Attribution::collect_media( (array) $data['featured_image'], $title . ' backdrop' ); ?>
        <?php } ?>
        <div class="hero-art">
            <?php echo Presentation::image( $record, 'movie-poster' ); Attribution::collect_image( $record ); ?>
            <?php if ( $review ) { echo ReviewScores::badge_row( $sample_review ? array_replace( $review, array( 'name' => 'Sample house score' ) ) : $review ); } ?>
        </div>
        <div class="movie-hero-copy">
            <?php if ( $franchises ) { ?><p class="movie-franchise-kicker"><a href="<?php echo esc_url( Presentation::url( $franchises[0] ) ); ?>"><?php echo esc_html( $franchises[0]['data']['title'] ); ?></a></p><?php } ?>
            <h1><?php echo esc_html( $title ); ?><?php if ( $year ) { ?> <span class="movie-year">(<?php echo esc_html( $year ); ?>)</span><?php } ?></h1>
            <?php if ( ! empty( $data['original_title'] ) && $data['original_title'] !== $title ) { ?><p class="movie-original"><?php echo esc_html( $data['original_title'] ); ?></p><?php } ?>
            <div class="movie-hero-meta">
                <?php if ( ! empty( $data['content_rating'] ) ) { ?><span class="movie-cert"><?php echo esc_html( $data['content_rating'] ); ?></span><?php } ?>
                <?php if ( ! empty( $data['runtime'] ) ) { ?><span><?php echo esc_html( $data['runtime'] . ' min' ); ?></span><?php } ?>
                <?php if ( ! empty( $data['genres'] ) ) { ?><span><?php echo esc_html( implode( ' · ', array_slice( (array) $data['genres'], 0, 3 ) ) ); ?></span><?php } ?>
            </div>
            <?php if ( ! empty( $data['film_tagline'] ) ) { ?><p class="movie-tagline"><?php echo esc_html( $data['film_tagline'] ); ?></p><?php } ?>
            <?php if ( $logline ) { ?><p class="movie-logline"><?php echo esc_html( $logline ); ?></p><?php } ?>
            <?php if ( $directors ) { ?><p class="movie-hero-credits"><strong>Directed by</strong> <?php echo esc_html( implode( ', ', $directors ) ); ?></p><?php } ?>
            <?php if ( $stars ) { ?><p class="movie-hero-credits"><strong>Starring</strong> <?php echo esc_html( implode( ', ', $stars ) ); ?></p><?php } ?>
            <?php if ( $scores ) { ?>
                <div class="movie-hero-scores" aria-label="Review scores">
                    <?php foreach ( $scores as $row ) { ?>
                        <a class="movie-hero-score<?php echo 'editorial' === $row['provider'] ? ' house-score' : ''; ?>" href="<?php echo esc_url( 'editorial' !== $row['provider'] && $row['url'] ? $row['url'] : '#movie-score-' . $row['provider'] ); ?>">
                            <strong><?php echo esc_html( Reviews::number( $row['score'] ) . ( 'rotten_tomatoes' === $row['provider'] ? '%' : '/' . $row['max'] ) ); ?></strong>
                            <span><?php echo esc_html( $sample_review && 'editorial' === $row['provider'] ? 'Sample house score' : $row['name'] ); ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ( $watch || isset( $sections['trailers'] ) || $trailer_url ) { ?>
                <div class="movie-hero-actions">
                    <?php if ( isset( $sections['trailers'] ) || $trailer_url ) { ?><a class="movie-action primary" href="<?php echo esc_url( isset( $sections['trailers'] ) ? '#trailers' : $trailer_url ); ?>"><span aria-hidden="true">▶</span> Watch trailer</a><?php } ?>
                    <?php if ( $watch ) { ?><a class="movie-action" href="<?php echo esc_url( $watch[0]['url'] ); ?>"><?php echo esc_html( $watch[0]['service'] ); ?></a><a class="movie-action" href="#where-to-watch">All watch options</a><?php } ?>
                </div>
            <?php } ?>
        </div>
    </header>

    <?php if ( $watch ) { get_template_part( 'parts/movie-watch', null, array( 'watch' => $watch ) ); } ?>
    <?php if ( $latest ) { ?>
        <div class="movie-latest"><strong>Latest coverage</strong><a href="<?php echo esc_url( get_permalink( $latest ) ); ?>"><?php echo esc_html( get_the_title( $latest ) ); ?></a><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $latest ) ); ?>"><?php echo esc_html( get_the_date( 'M j, Y', $latest ) ); ?></time></div>
    <?php } ?>
    <?php if ( $sections ) { ?>
        <nav class="movie-tabs" aria-label="On this page" data-movie-tabs>
            <?php foreach ( $sections as $id => $row ) { ?><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row[0] ); ?></a><?php } ?>
        </nav>
    <?php } ?>

    <div class="movie-body<?php echo $side_facts || $website ? ' has-rail' : ''; ?>">
        <div class="movie-main"><?php foreach ( $sections as $row ) { echo $row[1]; } ?></div>
        <?php if ( $side_facts || $website ) { ?>
            <aside class="movie-rail"><div class="details-panel">
                <h2>Film facts</h2>
                <?php echo $side_facts; ?>
                <?php if ( $website ) { ?><p><a class="text-link" href="<?php echo esc_url( $website ); ?>">Official film site ↗</a></p><?php } ?>
            </div></aside>
        <?php } ?>
    </div>
</article>
<?php echo MovieEditorial::structured_data( $record, $credits, $review ); ?>
