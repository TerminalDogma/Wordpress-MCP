<?php
namespace MSC;

/** Portable editorial categories projected into each site's native taxonomies. */
final class Taxonomies {
	private static string $registered = '';
	public static function definitions(): array {
		return array(
			'msc_game_platform' => array( 'Game Platforms', 'game-platform', array( 'PlayStation', 'PlayStation 2', 'PlayStation 3', 'PlayStation 4', 'PlayStation 5', 'PlayStation Portable', 'PlayStation Vita', 'PlayStation VR', 'PlayStation VR2', 'PC (Microsoft Windows)', 'Mac', 'Linux', 'Xbox', 'Xbox 360', 'Xbox One', 'Xbox Series X|S', 'Nintendo Switch', 'Nintendo Switch 2', 'iOS', 'Android' ) ),
			'msc_game_developer' => array( 'Game Developers', 'game-developer', array() ),
			'msc_game_publisher' => array( 'Game Publishers', 'game-publisher', array() ),
			'msc_comic_publisher' => array( 'Comic Publishers', 'comic-publisher', array() ),
			'msc_comic_creator' => array( 'Comic Creators', 'comic-creator', array() ),
			'msc_game_mode' => array( 'Game Modes', 'game-mode', array( 'Single player', 'Multiplayer', 'Co-operative', 'Split screen', 'Massively Multiplayer Online (MMO)', 'Battle Royale' ) ),
			'msc_genre' => array( 'Genres', 'genre', array( 'Action', 'Adventure', 'Animation', 'Comedy', 'Crime', 'Documentary', 'Drama', 'Family', 'Fantasy', 'Horror', 'Mystery', 'Romance', 'Science Fiction', 'Thriller', 'Role-playing (RPG)', 'Racing', 'Shooter', 'Strategy', 'Indie', 'Puzzle', 'Platform', 'Simulator', 'Sport' ) ),
			'msc_network' => array( 'TV Networks', 'network', array( 'Demo Network', 'BBC', 'HBO', 'NBC', 'ABC', 'CBS', 'Fox' ) ),
			'msc_platform' => array( 'Streaming Services', 'streaming-service', array( 'Netflix', 'Prime Video', 'Disney+', 'Apple TV+', 'Hulu', 'Max', 'Paramount+', 'Peacock' ) ),
			'msc_country' => array( 'Countries', 'country', array( 'United States', 'United Kingdom', 'Canada', 'Australia', 'France', 'Germany', 'India', 'Japan', 'South Korea' ) ),
			'msc_language' => array( 'Languages', 'language', array( 'English', 'French', 'German', 'Hindi', 'Japanese', 'Korean', 'Spanish' ) ),
			'msc_show_status' => array( 'TV Show Statuses', 'show-status', array( 'Upcoming', 'Returning Series', 'Ended', 'Canceled', 'In Production' ) ),
			'msc_movie_status' => array( 'Movie Statuses', 'movie-status', array( 'Announced', 'In Production', 'Post Production', 'Released' ) ),
			'msc_rating' => array( 'Content Ratings', 'content-rating', array( 'G', 'PG', 'PG-13', 'R', 'NC-17', 'Not Rated' ) ),
			'msc_profession' => array( 'Professions', 'profession', array( 'Actor', 'Director', 'Writer', 'Producer', 'Creator', 'Composer', 'Cinematographer' ) ),
		);
	}
	public static function register(): void {
		$objects = array();
		foreach ( Schema::all() as $type => $schema ) {
			foreach ( $schema['fields'] as $field ) { if ( ! empty( $field['taxonomy'] ) ) { $objects[ $field['taxonomy'] ][] = $type; } }
		}
		// Terms must also work while unpublishing a disabled type during a sync.
		$signature = md5( serialize( $objects ) );
		if ( self::$registered === $signature && ! array_filter( array_keys( $objects ), static fn( $key ) => ! taxonomy_exists( $key ) ) ) { return; }
		foreach ( self::definitions() as $key => $definition ) {
			if ( empty( $objects[ $key ] ) ) { continue; }
			register_taxonomy( $key, array_unique( $objects[ $key ] ), array(
				'label' => $definition[0], 'public' => true, 'hierarchical' => true,
				'rewrite' => array( 'slug' => $definition[1], 'with_front' => false ),
				// All writes go through explicit-scope MSC forms and optimistic locking.
				'show_ui' => false, 'show_in_rest' => false, 'show_admin_column' => true,
				'meta_box_cb' => false, 'show_in_quick_edit' => false,
				'capabilities' => array( 'manage_terms' => 'msc_manage_schemas', 'edit_terms' => 'msc_manage_schemas', 'delete_terms' => 'msc_manage_schemas', 'assign_terms' => 'msc_override_local' ),
			) );
		}
		self::$registered = $signature;
	}
	public static function choices( array $field, $current = array() ): array {
		if ( empty( $field['taxonomy'] ) ) { return (array) ( $field['options'] ?? array() ); }
		if ( ! empty( $field['options'] ) ) { return array_values( array_unique( array_merge( $field['options'], array_filter( (array) $current, 'strlen' ) ) ) ); }
		$key = $field['taxonomy']; $definition = self::definitions()[ $key ] ?? null;
		if ( ! $definition ) { return array(); }
		$custom = get_network_option( get_current_network_id(), 'msc_category_choices', array() );
		$terms = taxonomy_exists( $key ) ? get_terms( array( 'taxonomy' => $key, 'hide_empty' => false, 'fields' => 'names' ) ) : array();
		$choices = array_merge( $definition[2], $custom[ $key ] ?? array(), is_wp_error( $terms ) ? array() : $terms, (array) $current );
		$choices = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $choices ), 'strlen' ) ) );
		natcasesort( $choices ); return array_values( $choices );
	}
	public static function add_choice( string $taxonomy, string $name ): void {
		if ( ! current_user_can( 'msc_manage_schemas' ) ) { throw new \RuntimeException( 'Category permission denied.' ); }
		$name = sanitize_text_field( trim( $name ) );
		if ( ! isset( self::definitions()[ $taxonomy ] ) || ! $name || strlen( $name ) > 180 ) { throw new \InvalidArgumentException( 'Choose a category and a name of 1–180 bytes.' ); }
		Database::lock( 'category-choices', static function () use ( $taxonomy, $name ) {
			$network = get_current_network_id(); wp_cache_delete( $network . ':msc_category_choices', 'site-options' );
			$choices = get_network_option( $network, 'msc_category_choices', array() );
			$choices[ $taxonomy ] = array_values( array_unique( array_merge( $choices[ $taxonomy ] ?? array(), array( $name ) ) ) );
			update_network_option( $network, 'msc_category_choices', $choices );
		} );
	}
	public static function sync( int $post, array $schema, array $values ): void {
		self::register(); $groups = array();
		foreach ( $schema['fields'] as $key => $field ) {
			if ( ! empty( $field['taxonomy'] ) ) { $groups[ $field['taxonomy'] ] = array_merge( $groups[ $field['taxonomy'] ] ?? array(), (array) ( $values[ $key ] ?? array() ) ); }
		}
		foreach ( $groups as $taxonomy => $names ) {
			$names = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $names ), 'strlen' ) ) );
			$result = wp_set_object_terms( $post, $names, $taxonomy, false );
			if ( is_wp_error( $result ) ) { throw new \RuntimeException( $result->get_error_message() ); }
		}
	}
}
