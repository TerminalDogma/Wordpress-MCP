<?php
use MSC\{Attribution, EntityCatalog, Franchises, Presentation, ReviewScores, Schema, Timelines, Trailers};

$record = $args['record'];
$data = $record['data'];
$title = $data['title'];
$is_movie = 'msc_movie' === $record['type'];
$archive = get_post_type_archive_link( $record['type'] );
$capture = static function ( callable $render ): string {
    ob_start();
    $render();
    return trim( (string) ob_get_clean() );
};

$lead_facts = $is_movie
    ? array( 'release_date' => 'Theatrical release', 'runtime' => 'Runtime', 'content_rating' => 'Rating' )
    : array( 'premiere_date' => 'Premiere', 'number_of_seasons' => 'Seasons', 'status' => 'Status' );
$detail_facts = $is_movie
    ? array( 'release_status' => 'Status', 'country' => 'Country', 'language' => 'Language', 'distributor' => 'Distributor', 'production_companies' => 'Production companies', 'streaming_service' => 'Streaming service', 'digital_release_date' => 'Digital release', 'streaming_release_date' => 'Streaming release', 'physical_release_date' => 'Physical release' )
    : array( 'network' => 'Network', 'streaming_service' => 'Streaming service', 'country' => 'Country', 'language' => 'Language', 'number_of_episodes' => 'Episodes', 'finale_date' => 'Finale' );

$relationships = EntityCatalog::rows( $record );
$metadata = $capture( static fn() => msc_screen_facts( $record, $detail_facts ) );
$metadata .= EntityCatalog::sidebar( $record, array_diff_key( $relationships, array_flip( array( 'directors', 'screenwriters', 'producers', 'creators' ) ) ) );
$website = esc_url( $data['official_website'] ?? '' );
$franchises = Franchises::related( $record );
$timelines = Timelines::related( $record );

$sections = array( 'overview' => array( 'Overview', msc_screen_overview( $record ) ) );
if ( ! $is_movie ) {
    $sections['episodes'] = array( 'Episodes', $capture( static fn() => get_template_part( 'parts/episodes', null, $args ) ) );
}
$sections['trailers'] = array( 'Trailers', $capture( static fn() => Trailers::render( $record ) ) );
$credits = $capture( static fn() => Presentation::credits( $record, true ) );
foreach ( array( 'director' => array( 'directors', 'Director' ), 'writers' => array( 'screenwriters', 'Writing credits' ), 'cast' => array( 'actors', 'Cast notes' ) ) as $field => $credit ) {
    if ( empty( $relationships[ $credit[0] ] ) && ! empty( $data[ $field ] ) ) {
        $credits .= '<div class="title-legacy-credit"><h3>' . esc_html( $credit[1] ) . '</h3><div class="prose">' . wp_kses_post( wpautop( $data[ $field ] ) ) . '</div></div>';
    }
}
$sections['credits'] = array( 'Cast & crew', $credits );
$sections['review-scores'] = array( 'Review scores', $capture( static fn() => ReviewScores::render( $record ) ) );
$sections['gallery'] = array( 'Gallery', $capture( static fn() => get_template_part( 'parts/gallery', null, $args ) ) );
$sections['recent-articles'] = array( 'Recent Articles', $capture( static fn() => get_template_part( 'parts/recent-articles', null, $args ) ) );
$sections = array_filter( $sections, static fn( $section ) => '' !== trim( $section[1] ) );
$genres = $capture( static fn() => msc_screen_chips( $record ) );
$lead = $capture( static fn() => msc_screen_facts( $record, $lead_facts ) );
$has_sidebar = $metadata || $website || $franchises || $timelines;
?>
<article class="screen-title">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span aria-hidden="true">/</span>
        <?php if ( $archive ) { ?><a href="<?php echo esc_url( $archive ); ?>"><?php echo esc_html( Schema::get( $record['type'] )['label'] ); ?></a><span aria-hidden="true">/</span><?php } ?>
        <span aria-current="page"><?php echo esc_html( $title ); ?></span>
    </nav>

    <header class="screen-title-hero">
        <div class="hero-art">
            <?php echo Presentation::image( $record, 'screen-title-poster' ); ?>
            <?php echo ReviewScores::badge( $record ); Attribution::collect_image( $record ); ?>
        </div>
        <div class="hero-copy">
            <p class="eyebrow"><?php echo $is_movie ? 'Movie' : 'TV Show'; ?></p>
            <h1><?php echo esc_html( $title ); ?></h1>
            <?php if ( ! empty( $data['original_title'] ) && $data['original_title'] !== $title ) { ?><p class="original-title"><?php echo esc_html( $data['original_title'] ); ?></p><?php } ?>
            <?php if ( $genres ) { ?><div class="chips"><?php echo $genres; ?></div><?php } ?>
            <?php echo $lead; ?>
        </div>
    </header>

    <?php if ( count( $sections ) > 1 ) { ?>
        <nav class="screen-title-nav" aria-label="On this page">
            <?php foreach ( $sections as $id => $section ) { ?><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section[0] ); ?></a><?php } ?>
        </nav>
    <?php } ?>

    <div class="screen-title-layout<?php echo $has_sidebar ? ' has-details' : ''; ?>">
        <?php if ( isset( $sections['overview'] ) ) { ?>
            <section class="story-copy screen-title-overview" id="overview" aria-labelledby="overview-heading">
                <h2 id="overview-heading">Overview</h2>
                <div class="prose catalog-description"><?php echo $sections['overview'][1]; ?></div>
            </section>
        <?php } ?>
        <?php if ( $has_sidebar ) { ?>
            <aside class="details-panel screen-title-details" aria-label="Title information">
                <?php if ( $metadata || $website ) { ?>
                    <div class="screen-title-detail-group">
                        <h2>Details</h2>
                        <?php echo $metadata; ?>
                        <?php if ( $website ) { ?><a class="screen-title-website" href="<?php echo $website; ?>">Official website <span aria-hidden="true">↗</span></a><?php } ?>
                    </div>
                <?php } ?>
                <?php if ( $franchises || $timelines ) { ?>
                    <div class="screen-title-detail-group screen-title-related">
                        <?php if ( $franchises ) { ?>
                            <h2>Franchises</h2>
                            <ul>
                                <?php foreach ( $franchises as $franchise ) { ?><li><a href="<?php echo esc_url( Presentation::url( $franchise ) ); ?>"><?php echo esc_html( $franchise['data']['title'] ); ?> <span aria-hidden="true">→</span></a></li><?php } ?>
                            </ul>
                        <?php } ?>
                        <?php if ( $timelines ) { get_template_part( 'parts/timeline-links', null, array( 'timelines' => $timelines ) ); } ?>
                    </div>
                <?php } ?>
            </aside>
        <?php } ?>
        <?php if ( array_diff_key( $sections, array( 'overview' => true ) ) ) { ?>
            <div class="screen-title-sections">
                <?php foreach ( $sections as $id => $section ) {
                    if ( 'overview' === $id ) { continue; }
                    if ( 'credits' === $id ) { ?>
                        <section class="title-credits" id="credits" aria-labelledby="credits-heading">
                            <h2 id="credits-heading">Cast &amp; crew</h2>
                            <?php echo $section[1]; ?>
                        </section>
                    <?php } else { echo $section[1]; }
                } ?>
            </div>
        <?php } ?>
    </div>
</article>
