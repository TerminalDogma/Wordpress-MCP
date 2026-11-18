<?php
use MSC\{Attribution, Media, Presentation, Timelines};

$record = $args['record'];
$data = $record['data'];
$model = $data['chronology'] ?? array();
$eras = array_column( $model['eras'] ?? array(), null, 'id' );
$entries = $model['entries'] ?? array();
$archive = get_post_type_archive_link( Timelines::TYPE );
$franchise = ! empty( $data['franchise'] ) ? Presentation::record( $data['franchise'] ) : null;
$intro = msc_screen_overview( $record );
$sources = array();
foreach ( (array) ( $data['additional_metadata']['timeline_sources'] ?? array() ) as $source ) {
    if ( ! is_array( $source ) || ! is_string( $source['url'] ?? null ) || ! is_string( $source['label'] ?? null ) ) { continue; }
    $url = esc_url( $source['url'], array( 'http', 'https' ) );
    if ( $url ) { $sources[] = '<a href="' . $url . '" rel="noopener">' . esc_html( $source['label'] ) . '</a>'; }
}
if ( $sources ) { Attribution::collect_html( 'Chronology references', '<p>' . implode( ' · ', $sources ) . '</p>' ); }
$groups = array();
foreach ( $entries as $entry ) {
    $last = array_key_last( $groups );
    if ( null === $last || $groups[ $last ]['label'] !== $entry['section'] ) {
        $groups[] = array( 'label' => $entry['section'], 'entries' => array() );
        $last = array_key_last( $groups );
    }
    $groups[ $last ]['entries'][] = $entry;
}
?>
<article class="story-timeline" data-story-timeline>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <?php if ( $archive ) { ?><a href="<?php echo esc_url( $archive ); ?>">Timelines</a><?php } ?>
        <?php if ( $franchise ) { ?><a href="<?php echo esc_url( Presentation::url( $franchise ) ); ?>"><?php echo esc_html( $franchise['data']['title'] ); ?></a><?php } ?>
        <span><?php echo esc_html( $data['title'] ); ?></span>
    </nav>
    <header class="timeline-header">
        <div>
            <p class="eyebrow">In-universe chronology</p>
            <h1><?php echo esc_html( $data['title'] ); ?></h1>
            <?php if ( trim( $intro ) ) { ?><div class="catalog-description timeline-intro"><?php echo $intro; ?></div><?php } ?>
        </div>
        <?php if ( $entries ) { ?>
            <dl class="timeline-counts">
                <?php $counts = array_count_values( array_column( $entries, 'kind' ) ); ?>
                <div><dt>Movies</dt><dd><?php echo (int) ( $counts['movie'] ?? 0 ); ?></dd></div>
                <div><dt>TV entries</dt><dd><?php echo (int) ( $counts['show'] ?? 0 ); ?></dd></div>
            </dl>
        <?php } ?>
    </header>
    <?php if ( ! $entries ) { ?><p class="timeline-empty">No entries have been added to this timeline.</p><?php } else { ?>
        <?php if ( $eras ) { ?>
            <ul class="timeline-legend" aria-label="Era color key">
                <?php foreach ( $eras as $era ) { ?>
                    <li style="--era-color:<?php echo esc_attr( $era['color'] ); ?>"><?php echo esc_html( $era['label'] ); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
        <form class="timeline-filter" hidden>
            <label>Show <select name="track"><option value="">Movies and TV Shows</option><option value="movie">Movies</option><option value="show">TV Shows</option></select></label>
            <?php if ( $eras ) { ?>
                <label>Era <select name="era">
                    <option value="">All eras</option>
                    <?php foreach ( $eras as $era ) { ?><option value="<?php echo esc_attr( $era['id'] ); ?>"><?php echo esc_html( $era['label'] ); ?></option><?php } ?>
                </select></label>
            <?php } ?>
            <p role="status" data-timeline-count></p>
        </form>
        <div class="timeline-lanes" aria-hidden="true"><span>Movies</span><span>Story date</span><span>TV Shows</span></div>
        <?php foreach ( $groups as $group ) { ?>
            <section class="timeline-section" data-timeline-section>
                <?php if ( $group['label'] ) { ?><h2 class="timeline-section-label"><?php echo esc_html( $group['label'] ); ?></h2><?php } ?>
                <ol class="timeline-entries">
                    <?php foreach ( $group['entries'] as $entry ) {
                        $era = $eras[ $entry['era'] ] ?? null;
                        $linked = $entry['item'] ? Presentation::record( $entry['item'] ) : null;
                        $ref = $entry['image'] ?: ( $linked['data']['poster'] ?? array() ) ?: ( $linked['data']['featured_image'] ?? array() );
                        $image = Media::url( $ref, 'medium' );
                        $date = $entry['date'] ?: 'Date unknown';
                        $initial = mb_substr( $entry['title'], 0, 1 );
                        ?>
                        <li class="timeline-entry lane-<?php echo esc_attr( $entry['kind'] ); ?>" data-timeline-entry data-track="<?php echo esc_attr( $entry['kind'] ); ?>" data-era="<?php echo esc_attr( $entry['era'] ); ?>"<?php if ( $era ) { ?> style="--era-color:<?php echo esc_attr( $era['color'] ); ?>"<?php } ?>>
                            <div class="timeline-date">
                                <span><?php echo esc_html( $date ); ?></span>
                                <?php if ( $entry['date'] && 'exact' !== $entry['precision'] ) { ?><small><?php echo esc_html( Timelines::DATES[ $entry['precision'] ] ); ?></small><?php } ?>
                            </div>
                            <article class="timeline-bubble">
                                <div class="timeline-art<?php echo $image ? '' : ' timeline-art-placeholder'; ?>">
                                    <?php if ( $image ) { ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async"><?php Attribution::collect_media( $ref, $entry['title'] ); ?>
                                    <?php } else { ?><span aria-hidden="true"><?php echo esc_html( $initial ); ?></span><?php } ?>
                                </div>
                                <div class="timeline-copy">
                                    <p class="timeline-type">
                                        <?php echo 'movie' === $entry['kind'] ? 'Movie' : 'TV'; ?>
                                        <?php if ( $era ) { ?><span> · <?php echo esc_html( $era['label'] ); ?></span><?php } ?>
                                    </p>
                                    <h3><?php if ( $linked ) { ?><a href="<?php echo esc_url( Presentation::url( $linked ) ); ?>"><?php echo esc_html( $entry['title'] ); ?></a><?php } else { echo esc_html( $entry['title'] ); } ?></h3>
                                    <?php if ( $entry['note'] ) { ?><p class="timeline-note"><?php echo esc_html( $entry['note'] ); ?></p><?php } ?>
                                </div>
                            </article>
                        </li>
                    <?php } ?>
                </ol>
            </section>
        <?php } ?>
        <p class="timeline-empty" data-timeline-empty hidden>No entries match these filters.</p>
    <?php } ?>
</article>
