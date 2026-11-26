<?php
$story = $args['post'];
$content = apply_filters( 'the_content', get_the_content( null, false, $story ) );
$headings = new WP_HTML_Tag_Processor( $content );
$stops = array( 'view-chapter-1' => array( 'label' => 'The Story' ) );
$i = 1;
while ( $headings->next_tag( 'H2' ) ) {
	$id = $headings->get_attribute( 'id' );
	++$i;
	if ( ! is_string( $id ) || '' === $id ) {
		$id = 'view-chapter-' . $i;
		$headings->set_attribute( 'id', $id );
	}
	$label = '';
	while ( $headings->next_token() ) {
		if ( 'H2' === $headings->get_tag() && $headings->is_tag_closer() ) { break; }
		if ( '#text' === $headings->get_token_type() ) { $label .= $headings->get_modifiable_text(); }
	}
	$stops[ $id ] = array( 'label' => trim( $label ) ?: 'Section ' . $i );
}

$related = array();
foreach ( array( 'road-comedy', 'celeste-rowe', 'breakwater-production' ) as $slug ) {
	$page = get_page_by_path( 'homepage-preview/' . $slug );
	if ( $page && 'publish' === $page->post_status && ! $page->post_password && 'story' === msc_cs_preview_meta( $page, 'preview' ) ) { $related[] = $page; }
}
?>
<div class="cut-article-photo">
	<img src="<?php echo esc_url( msc_studio_art( 'cut' ) ); ?>" alt="" width="1440" height="960" fetchpriority="high" decoding="async">
</div>
<?php get_template_part( 'parts/comingsoon-cut-filmstrip', null, array( 'stops' => $stops, 'label' => 'In This Article' ) ); ?>
<div class="cut-reading-layout<?php if ( $related ) { ?> has-related<?php } ?>">
	<article id="view-chapter-1" class="cut-article-copy" aria-label="Article text">
		<?php echo $headings->get_updated_html(); ?>
	</article>
	<?php if ( $related ) { ?>
		<aside class="cut-related" aria-labelledby="cut-related-title">
			<h2 id="cut-related-title">More Movie News</h2>
			<?php foreach ( $related as $page ) { msc_cs_preview_card( $page, 'grid-highlight' ); } ?>
		</aside>
	<?php } ?>
</div>
