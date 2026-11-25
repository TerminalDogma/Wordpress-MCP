<?php
namespace MSC;

/** Shared editing workflow for the full record form and the ACF form. */
final class EditorLayout {
	public static function sections( array $fields ): array {
		$sections = array(
			'Overview' => array( false, array() ),
			'Catalog details' => array( false, array() ),
			'Artwork and trailers' => array( false, array() ),
			'Release history and availability' => array( false, array() ),
			'Optional editorial details' => array( true, array() ),
			'Aggregate review scores' => array( true, array() ),
			'Additional settings' => array( true, array() ),
		);
		foreach ( $fields as $key => $field ) {
			$heading = match ( true ) {
				(bool) preg_match( '/^(?:rotten_tomatoes|metacritic|opencritic|score)_/', $key ) => 'Aggregate review scores',
				in_array( $key, array( 'title', 'original_title', 'content', 'excerpt', 'editorial_synopsis', 'film_logline', 'film_tagline' ), true ) => 'Overview',
				in_array( $field['type'], array( 'media', 'media_list' ), true ) || in_array( $key, array( 'trailers', 'trailer_source_url' ), true ) => 'Artwork and trailers',
				in_array( $key, array( 'release_history', 'additional_release_dates', 'watch_options', 'release_dates' ), true ) => 'Release history and availability',
				in_array( $key, array( 'spoiler_synopsis', 'movie_faq', 'rating_reason', 'franchise_context', 'post_credits_count', 'film_budget', 'film_budget_url', 'domestic_opening', 'worldwide_gross', 'box_office_currency', 'box_office_url' ), true ) => 'Optional editorial details',
				in_array( $key, array( 'slug', 'external_ids', 'additional_metadata', 'platform_ids', 'seo_title', 'seo_description' ), true ) => 'Additional settings',
				default => 'Catalog details',
			};
			$sections[ $heading ][1][ $key ] = $field;
		}
		return array_filter( $sections, static fn( array $section ) => (bool) $section[1] );
	}

	public static function open( string $heading, bool $collapsed ): string {
		$container = $collapsed ? 'details' : 'section';
		$label = $collapsed ? 'summary' : 'h2';
		echo '<' . $container . ' class="msc-editor-section"><' . $label . '>' . esc_html( $heading ) . '</' . $label . '>';
		return $container;
	}
}
