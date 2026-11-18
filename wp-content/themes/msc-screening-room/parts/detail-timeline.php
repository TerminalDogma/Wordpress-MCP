<?php
use MSC\{Attribution, Presentation, Timelines};

$record = $args['record'];
$data = $record['data'];
$model = Timelines::model( $data['chronology'] ?? array() );
$columns = array_column( $model['columns'], 'label', 'id' );
$sections = array_column( $model['sections'] ?? array(), 'label', 'id' );
$column_count = count( $columns );
$split = intdiv( $column_count, 2 );
$positions = array();
$grid = array();
foreach ( array_keys( $columns ) as $index => $id ) {
    if ( $index === $split ) { $grid[] = 'var(--timeline-date-width)'; }
    $grid[] = 'minmax(0, 1fr)';
    $positions[ $id ] = count( $grid );
}
$layout = '--timeline-columns:' . implode( ' ', $grid ) . ';--timeline-date-column:' . ( $split + 1 )
    . ';--timeline-date-fraction:' . sprintf( '%.4F', $split / max( 1, $column_count ) );
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
foreach ( $entries as $index => $entry ) {
    $label = $sections[ $entry['section'] ] ?? '';
    $last = array_key_last( $groups );
    if ( null === $last || $groups[ $last ]['id'] !== $entry['section'] ) {
        $groups[] = array( 'id' => $entry['section'], 'label' => $label, 'entries' => array() );
        $last = array_key_last( $groups );
    }
    $groups[ $last ]['entries'][ $index ] = $entry;
}
?>
<article class="story-timeline<?php echo $column_count > 3 ? ' timeline-many-columns' : ''; ?>" style="<?php echo esc_attr( $layout ); ?>" data-story-timeline>
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
                <?php $counts = array_count_values( array_column( $entries, 'column' ) ); ?>
                <?php foreach ( $columns as $key => $label ) { ?>
                    <div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo (int) ( $counts[ $key ] ?? 0 ); ?></dd></div>
                <?php } ?>
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
            <label>Column <select name="track"><option value="">All columns</option>
                <?php foreach ( $columns as $id => $label ) { ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option><?php } ?>
            </select></label>
            <?php if ( $eras ) { ?>
                <label>Era <select name="era">
                    <option value="">All eras</option>
                    <?php foreach ( $eras as $era ) { ?><option value="<?php echo esc_attr( $era['id'] ); ?>"><?php echo esc_html( $era['label'] ); ?></option><?php } ?>
                </select></label>
            <?php } ?>
            <p role="status" data-timeline-count></p>
        </form>
        <div class="timeline-lanes" aria-hidden="true">
            <?php foreach ( $columns as $id => $label ) { ?><span style="grid-column:<?php echo (int) $positions[ $id ]; ?>"><?php echo esc_html( $label ); ?></span><?php } ?>
            <span class="timeline-date-heading">Story date</span>
        </div>
        <?php foreach ( $groups as $group ) { ?>
            <section class="timeline-section" data-timeline-section>
                <?php if ( $group['label'] ) { ?><h2 class="timeline-section-label"><?php echo esc_html( $group['label'] ); ?></h2><?php } ?>
                <ol class="timeline-entries">
                    <?php foreach ( $group['entries'] as $index => $entry ) {
                        $era = $eras[ $entry['era'] ] ?? null;
                        $linked = $entry['item'] ? Presentation::record( $entry['item'] ) : null;
                        $art = Presentation::artwork( $linked ?? array( 'type' => '', 'data' => array() ), 'medium', $entry['image'] );
                        $image = $art['url'] ?? '';
                        $position = $positions[ $entry['column'] ];
                        $direction = $position < $split + 1 ? 'before' : 'after';
                        $style = '--timeline-card-column:' . $position;
                        if ( $era ) { $style .= ';--era-color:' . $era['color']; }
                        $date = $entry['date'] ?: 'Date unknown';
                        $initial = mb_substr( $entry['title'], 0, 1 );
                        ?>
                        <li id="timeline-entry-<?php echo (int) $index + 1; ?>" class="timeline-entry lane-<?php echo esc_attr( $entry['column'] ); ?> timeline-<?php echo esc_attr( $direction ); ?>-date" data-timeline-entry data-track="<?php echo esc_attr( $entry['column'] ); ?>" data-era="<?php echo esc_attr( $entry['era'] ); ?>" style="<?php echo esc_attr( $style ); ?>">
                            <div class="timeline-date">
                                <span><?php echo esc_html( $date ); ?></span>
                                <?php if ( $entry['date'] && 'exact' !== $entry['precision'] ) { ?><small><?php echo esc_html( Timelines::DATES[ $entry['precision'] ] ); ?></small><?php } ?>
                            </div>
                            <article class="timeline-bubble">
                                <div class="timeline-art<?php echo $image ? '' : ' timeline-art-placeholder'; ?>">
                                    <?php if ( $image ) { ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async"><?php Attribution::collect_media( $art['reference'], $entry['title'] ); ?>
                                    <?php } else { ?><span aria-hidden="true"><?php echo esc_html( $initial ); ?></span><?php } ?>
                                </div>
                                <div class="timeline-copy">
                                    <p class="timeline-type">
                                        <?php echo esc_html( $columns[ $entry['column'] ] ); ?>
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
