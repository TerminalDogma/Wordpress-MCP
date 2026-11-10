<?php
namespace MSC;

final class Schema {
	public const BUILTIN_VERSION = 11;
	private static array $types = array();
	public static function all(): array {
		return get_network_option( get_current_network_id(), 'msc_schemas', self::defaults() );
	}
	public static function get( string $type ): array {
		$schemas = self::all();
		if ( ! isset( $schemas[ $type ] ) ) { throw new \InvalidArgumentException( 'Unknown shared post type.' ); }
		return $schemas[ $type ];
	}
	public static function defaults(): array {
		$common = array(
			'title' => array( 'type' => 'text', 'property' => 'post_title', 'label' => 'Title' ),
			'content' => array( 'type' => 'richtext', 'property' => 'post_content', 'label' => 'Description' ),
			'excerpt' => array( 'type' => 'richtext', 'property' => 'post_excerpt', 'label' => 'Synopsis' ),
			'slug' => array( 'type' => 'slug', 'property' => 'post_name', 'label' => 'URL slug' ),
			'featured_image' => array( 'type' => 'media' ), 'seo_title' => array( 'type' => 'text' ),
			'seo_description' => array( 'type' => 'text' ), 'additional_metadata' => array( 'type' => 'json' ),
		);
		$show = $common;
		$show['network'] = array( 'type' => 'text', 'label' => 'TV network' );
		$show['streaming_service'] = array( 'type' => 'text' );
		foreach ( array( 'original_title', 'status', 'country', 'language' ) as $field ) { $show[ $field ] = array( 'type' => 'text' ); }
		foreach ( array( 'premiere_date', 'finale_date' ) as $field ) { $show[ $field ] = array( 'type' => 'date', 'indexed' => true ); }
		$show['genres'] = array( 'type' => 'multiselect' ); $show['poster'] = array( 'type' => 'media' );
		$show['promotional_images'] = array( 'type' => 'media_list' ); $show['official_website'] = array( 'type' => 'url' );
		$show['number_of_seasons'] = array( 'type' => 'integer', 'min' => 0 );
		$show['number_of_episodes'] = array( 'type' => 'integer', 'min' => 0 ); $show['external_ids'] = array( 'type' => 'json' );
		$episode = $common + array(
			'network' => array( 'type' => 'text', 'label' => 'TV network' ),
			'streaming_service' => array( 'type' => 'text' ),
			'parent_show' => array( 'type' => 'relationship', 'target' => 'msc_show', 'indexed' => true ),
			'season_number' => array( 'type' => 'integer', 'min' => 0, 'indexed' => true ),
			'episode_number' => array( 'type' => 'integer', 'min' => 0, 'indexed' => true ),
			'overall_episode_number' => array( 'type' => 'integer', 'min' => 0 ),
			'release_date' => array( 'type' => 'date', 'indexed' => true ), 'air_time' => array( 'type' => 'time' ),
			'time_zone' => array( 'type' => 'timezone' ), 'runtime' => array( 'type' => 'number', 'min' => 0 ),
			'episode_image' => array( 'type' => 'media' ), 'production_code' => array( 'type' => 'text' ),
			'additional_release_dates' => array( 'type' => 'json' ), 'external_ids' => array( 'type' => 'json' ),
		);
		$movie = $common;
		foreach ( array( 'original_title', 'country', 'language', 'director', 'writers', 'distributor', 'production_companies', 'content_rating', 'release_status', 'streaming_service' ) as $field ) { $movie[ $field ] = array( 'type' => 'text' ); }
		$movie['cast'] = array( 'type' => 'richtext', 'label' => 'Cast and roles' );
		foreach ( array( 'release_date' => 'Theatrical release date', 'digital_release_date' => 'Digital release date', 'streaming_release_date' => 'Streaming release date', 'physical_release_date' => 'DVD / Blu-ray release date' ) as $field => $label ) { $movie[ $field ] = array( 'type' => 'date', 'label' => $label, 'indexed' => true ); }
		$movie['runtime'] = array( 'type' => 'number', 'min' => 0, 'label' => 'Runtime (minutes)' );
		$movie['genres'] = array( 'type' => 'multiselect' );
		$movie['poster'] = array( 'type' => 'media' );
		$movie['promotional_images'] = array( 'type' => 'media_list' );
		$movie['official_website'] = array( 'type' => 'url' );
		$movie['external_ids'] = array( 'type' => 'json', 'label' => 'External database identifiers' );
		$movie['additional_release_dates'] = array( 'type' => 'json', 'label' => 'Additional regional release dates' );
		foreach ( array( 'actors' => 'Actors', 'directors' => 'Directors', 'screenwriters' => 'Screenwriters', 'producers' => 'Producers' ) as $key => $label ) {
			$definition = array( 'type' => 'relationship_list', 'target' => 'msc_person', 'label' => $label );
			$show[ $key ] = $episode[ $key ] = $movie[ $key ] = $definition;
		}
		$show['creators'] = array( 'type' => 'relationship_list', 'target' => 'msc_person' );
		$person = $common + array(
			'headshot' => array( 'type' => 'media' ), 'birth_date' => array( 'type' => 'date' ),
			'country' => array( 'type' => 'text' ), 'professions' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_profession' ),
			'official_website' => array( 'type' => 'url' ), 'external_ids' => array( 'type' => 'json' ),
		);
		$game = $common + array(
			'release_date' => array( 'type' => 'date', 'indexed' => true, 'label' => 'First exact release date' ),
			'release_dates' => array( 'type' => 'json', 'label' => 'Platform and regional release dates' ),
			'platforms' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_game_platform', 'label' => 'Platforms' ),
			'platform_ids' => array( 'type' => 'json', 'label' => 'IGDB platform identifiers' ),
			'genres' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_genre' ),
			'developers' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_game_developer' ),
			'publishers' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_game_publisher' ),
			'game_modes' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_game_mode' ),
			'poster' => array( 'type' => 'media', 'label' => 'Cover image' ), 'promotional_images' => array( 'type' => 'media_list' ),
			'official_website' => array( 'type' => 'url' ), 'external_ids' => array( 'type' => 'json' ),
		);
		$trailer_field = array( 'type' => 'text', 'format' => 'trailers', 'multiline' => true, 'label' => 'Trailers', 'instructions' => 'Paste one YouTube or public Vimeo video URL per line, up to 12. The line order controls the carousel.' );
		$movie['trailers'] = $show['trailers'] = $game['trailers'] = $trailer_field;
		// Taxonomy values remain portable names, never site-specific term IDs.
		$categories = array( 'genres' => 'msc_genre', 'network' => 'msc_network', 'streaming_service' => 'msc_platform', 'country' => 'msc_country', 'language' => 'msc_language', 'status' => 'msc_show_status', 'release_status' => 'msc_movie_status', 'content_rating' => 'msc_rating' );
		foreach ( array( &$show, &$episode, &$movie, &$person ) as &$fields ) {
			foreach ( $categories as $key => $taxonomy ) { if ( isset( $fields[ $key ] ) ) { $fields[ $key ]['taxonomy'] = $taxonomy; } }
		}
		unset( $fields );
		foreach ( array( 'msc_show' => &$show, 'msc_episode' => &$episode, 'msc_movie' => &$movie, 'msc_game' => &$game ) as $type => &$fields ) { $fields += EntityCatalog::fields( $type ) + ReviewScores::fields( $type ); } unset( $fields );
		return array(
			'msc_show' => array( 'label' => 'TV Shows', 'slug' => 'shows', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $show ),
			'msc_episode' => array( 'label' => 'Episodes', 'slug' => 'episodes', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $episode ),
			'msc_movie' => array( 'label' => 'Movies', 'slug' => 'movies', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $movie ),
			'msc_person' => array( 'label' => 'People', 'slug' => 'people', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $person ),
			'msc_game' => array( 'label' => 'Video Games', 'slug' => 'games', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $game ),
		) + EntityCatalog::schemas( $common ) + Comics::schemas( $common );
	}
	/** Add new built-ins and missing fields once, retaining saved schema choices. */
	public static function upgrade_defaults(): void {
		$network = get_current_network_id();
		if ( (int) get_network_option( $network, 'msc_builtin_schema_version', 0 ) >= self::BUILTIN_VERSION ) { return; }
		Database::lock( 'builtin-schema', static function () use ( $network ) {
			// Another request may have completed the upgrade while this one waited.
			foreach ( array( 'msc_schemas', 'msc_builtin_schema_version' ) as $option ) { wp_cache_delete( $network . ':' . $option, 'site-options' ); }
			if ( (int) get_network_option( $network, 'msc_builtin_schema_version', 0 ) >= self::BUILTIN_VERSION ) { return; }
			$previous_version = (int) get_network_option( $network, 'msc_builtin_schema_version', 0 );
			$saved = get_network_option( $network, 'msc_schemas', false );
			if ( false !== $saved && ! is_array( $saved ) ) { throw new \RuntimeException( 'Stored shared schemas are invalid; repair them before upgrading.' ); }
			$schemas = false === $saved ? self::defaults() : $saved;
			foreach ( self::defaults() as $type => $default ) {
				if ( ! isset( $schemas[ $type ] ) ) { if ( Comics::is_type( $type ) && $previous_version < 10 || 'msc_movie' === $type && $previous_version < 2 || 'msc_person' === $type && $previous_version < 3 || 'msc_game' === $type && $previous_version < 5 || isset( EntityCatalog::definitions()[ $type ] ) && $previous_version < 6 ) { $schemas[ $type ] = $default; } continue; }
				$properties = array_column( $schemas[ $type ]['fields'], 'property' );
				foreach ( $default['fields'] as $key => $field ) {
					// The 1.4 upgrade adds only Episode external IDs to already upgraded schemas.
					if ( ! ( $previous_version < 11 && 'msc_comic' === $type && 'story_arcs' === $key ) && $previous_version >= 3 && ( 'msc_episode' !== $type || 'external_ids' !== $key ) && ! ( $previous_version < 6 && isset( EntityCatalog::fields( $type )[ $key ] ) ) && ! ( $previous_version < 7 && 'trailers' === $key ) && ! ( $previous_version < 9 && isset( ReviewScores::fields( $type )[ $key ] ) ) ) { continue; }
					if ( array_key_exists( $key, $schemas[ $type ]['fields'] ) ) {
						$existing = &$schemas[ $type ]['fields'][ $key ];
						if ( isset( $field['taxonomy'] ) && ! array_key_exists( 'taxonomy', $existing ) && $existing['type'] === $field['type'] ) { $existing['taxonomy'] = $field['taxonomy']; }
						unset( $existing ); continue;
					}
					if ( isset( $field['property'] ) && in_array( $field['property'], $properties, true ) ) { continue; }
					$schemas[ $type ]['fields'][ $key ] = $field;
					if ( isset( $field['property'] ) ) { $properties[] = $field['property']; }
				}
			}
			if ( $previous_version < 9 ) { foreach ( $schemas as &$schema ) { foreach ( array( 'score_rawg_id', 'score_opencritic_id' ) as $obsolete ) { if ( isset( $schema['fields'][ $obsolete ] ) ) { $schema['fields'][ $obsolete ]['hidden'] = true; } } if ( 'OpenCritic critic score / 100' === ( $schema['fields']['opencritic_score']['label'] ?? '' ) ) { $schema['fields']['opencritic_score']['label'] = 'OpenCritic score or critics recommended (0–100)'; } } unset( $schema ); }
			if ( $schemas !== $saved && ! update_network_option( $network, 'msc_schemas', $schemas ) ) { throw new \RuntimeException( 'Could not save the upgraded schemas; the upgrade will retry.' ); }
			if ( ! update_network_option( $network, 'msc_builtin_schema_version', self::BUILTIN_VERSION ) && (int) get_network_option( $network, 'msc_builtin_schema_version', 0 ) !== self::BUILTIN_VERSION ) { throw new \RuntimeException( 'Could not record the schema upgrade version; the upgrade will retry.' ); }
		} );
	}
	public static function register_type( string $name, callable $sanitize ): void { self::$types[ $name ] = $sanitize; }
	public static function save( array $schemas ): void {
		if ( ! current_user_can( 'manage_network_options' ) && ! current_user_can( 'msc_manage_schemas' ) ) { throw new \RuntimeException( 'Schema permission denied.' ); }
		if ( count( $schemas ) > 50 ) { throw new \InvalidArgumentException( 'Maximum 50 types.' ); }
		foreach ( $schemas as $name => &$schema ) {
			if ( ! preg_match( '/^msc_[a-z0-9_]{1,16}$/D', $name ) ) { throw new \InvalidArgumentException( 'Shared CPT names must start msc_ and be at most 20 characters.' ); }
			if ( empty( $schema['fields']['title'] ) ) { throw new \InvalidArgumentException( 'Every type requires title.' ); }
			$schema['label'] = sanitize_text_field( $schema['label'] ?? $name );
			$schema['slug'] = sanitize_title( $schema['slug'] ?? $name );
			$schema['noindex'] = (bool) ( $schema['noindex'] ?? true );
			$schema['archive'] = (bool) ( $schema['archive'] ?? true );
			if ( ! in_array( $schema['default_status'] ?? 'draft', array( 'draft', 'publish', 'private' ), true ) ) { throw new \InvalidArgumentException( 'Invalid publication default.' ); }
			$properties = array();
			foreach ( $schema['fields'] as $key => $field ) {
				if ( ! preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $key ) || ! in_array( $field['type'], self::field_types(), true ) ) { throw new \InvalidArgumentException( 'Invalid field key or type: ' . $key ); }
				if ( ! empty( $field['taxonomy'] ) && ( ! isset( Taxonomies::definitions()[ $field['taxonomy'] ] ) || ! in_array( $field['type'], array( 'text', 'select', 'multiselect' ), true ) ) ) { throw new \InvalidArgumentException( 'Unsupported category field.' ); }
				if ( isset( $field['property'] ) && ! in_array( $field['property'], array( 'post_title', 'post_content', 'post_excerpt', 'post_name' ), true ) ) { throw new \InvalidArgumentException( 'Unsupported native property.' ); }
				if ( isset( $field['property'] ) ) {
					if ( isset( $properties[ $field['property'] ] ) || ! in_array( $field['type'], array( 'text', 'richtext', 'slug' ), true ) ) { throw new \InvalidArgumentException( 'Native properties need unique text/richtext/slug fields.' ); }
					$properties[ $field['property'] ] = true;
				}
			}
		}
		unset( $schema );
		// Removing populated types/fields needs a deliberate migration, never a silent drop.
		global $wpdb;
		foreach ( self::all() as $name => $previous ) {
			if ( isset( $schemas[ $name ] ) ) {
				foreach ( $previous['fields'] as $key => $old_field ) {
					$new_field = $schemas[ $name ]['fields'][ $key ] ?? null;
					if ( $new_field && ( $new_field['type'] !== $old_field['type'] || ( $new_field['property'] ?? null ) !== ( $old_field['property'] ?? null ) || ( $new_field['override'] ?? true ) !== ( $old_field['override'] ?? true ) ) ) {
						if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . ' WHERE type=%s LIMIT 1', $name ) ) ) { throw new \InvalidArgumentException( 'Populated field type/property/override policy changes require an explicit migration.' ); }
					}
				}
			}
			if ( ! isset( $schemas[ $name ] ) || array_diff_key( $previous['fields'], $schemas[ $name ]['fields'] ) ) {
				if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . ' WHERE type=%s LIMIT 1', $name ) ) ) {
					throw new \InvalidArgumentException( 'Cannot remove populated types or fields; archive the type or write a migration.' );
				}
			}
		}
		update_network_option( get_current_network_id(), 'msc_schemas', $schemas );
	}
	public static function field_types(): array {
		return array_merge( array( 'text', 'richtext', 'slug', 'number', 'integer', 'boolean', 'date', 'time', 'timezone', 'select', 'multiselect', 'url', 'media', 'media_list', 'relationship', 'relationship_list', 'json' ), array_keys( self::$types ) );
	}
	public static function sanitize( array $field, $value ) {
		$type = $field['type'];
		if ( in_array( $field['format'] ?? '', array( 'score_url', 'score_source_url' ), true ) ) {
			if ( '' === $value || null === $value ) { return ''; }
			$url = 'score_url' === $field['format'] ? ReviewScores::provider_url( $field['provider'], $value ) : ReviewScores::source_url( $value );
			if ( ! $url ) { throw new \InvalidArgumentException( 'Enter an HTTPS title page URL on the selected score provider’s website.' ); } return $url;
		}
		if ( 'wikipedia_title' === ( $field['format'] ?? '' ) ) { return WikipediaScores::title( $value ); }
		if ( 'trailers' === ( $field['format'] ?? '' ) ) { return Trailers::sanitize( $value ); }
		if ( isset( self::$types[ $type ] ) ) { return ( self::$types[ $type ] )( $value, $field ); }
		if ( 'relationship_list' === $type ) {
			if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > 200 ) { throw new \InvalidArgumentException( 'Expected a list of at most 200 related records.' ); }
			foreach ( $value as $item ) { if ( ! is_scalar( $item ) || '' === (string) $item ) { throw new \InvalidArgumentException( 'Related record identifiers cannot be blank.' ); } }
			return array_values( array_unique( array_map( static fn( $item ) => self::sanitize( array( 'type' => 'relationship', 'target' => $field['target'] ), $item ), $value ) ) );
		}
		if ( 'boolean' === $type ) {
			if ( ! in_array( $value, array( true, false, 0, 1, '0', '1' ), true ) ) { throw new \InvalidArgumentException( 'Expected boolean.' ); }
			return (bool) $value;
		}
		if ( in_array( $type, array( 'json', 'media_list', 'multiselect', 'media' ), true ) ) {
			if ( 'media' === $type ) { return Media::sanitize( $value ); }
			if ( ! is_array( $value ) ) { throw new \InvalidArgumentException( 'Expected JSON array or object.' ); }
			if ( 'json' === $type ) { return json_decode( wp_json_encode( $value, JSON_THROW_ON_ERROR ), true, 32, JSON_THROW_ON_ERROR ); }
			if ( 'media_list' === $type ) { return array_map( array( Media::class, 'sanitize' ), $value ); }
			return array_values( array_map( static fn( $v ) => self::sanitize( array( 'type' => 'select', 'options' => $field['options'] ?? array() ), $v ), $value ) );
		}
		if ( ! is_scalar( $value ) && null !== $value ) { throw new \InvalidArgumentException( 'Expected scalar field.' ); }
		if ( '' === $value || null === $value ) { return ''; }
		if ( in_array( $type, array( 'number', 'integer' ), true ) ) {
			if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || ( 'integer' === $type && (float) (int) $value !== (float) $value ) ) { throw new \InvalidArgumentException( 'Invalid number.' ); }
			$n = 'integer' === $type ? (int) $value : (float) $value;
			if ( isset( $field['min'] ) && $n < $field['min'] || isset( $field['max'] ) && $n > $field['max'] ) { throw new \InvalidArgumentException( 'Number outside allowed range.' ); }
			return $n;
		}
		$value = (string) $value;
		if ( 'date' === $type && ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) || ! \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) || \DateTimeImmutable::createFromFormat( '!Y-m-d', $value )->format( 'Y-m-d' ) !== $value ) ) { throw new \InvalidArgumentException( 'Expected YYYY-MM-DD.' ); }
		if ( 'time' === $type && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D', $value ) ) { throw new \InvalidArgumentException( 'Expected HH:MM[:SS].' ); }
		if ( 'timezone' === $type && ! in_array( $value, \DateTimeZone::listIdentifiers(), true ) ) { throw new \InvalidArgumentException( 'Expected IANA time zone.' ); }
		if ( 'relationship' === $type ) {
			$r = Plugin::store()->get( $value );
			if ( ! $r || 'active' !== $r['state'] || isset( $field['target'] ) && $r['type'] !== $field['target'] ) { throw new \InvalidArgumentException( 'Relationship target is unavailable or has wrong type.' ); }
			return $r['uuid'];
		}
		if ( 'select' === $type && ! empty( $field['options'] ) && ! in_array( $value, $field['options'], true ) ) { throw new \InvalidArgumentException( 'Invalid selection.' ); }
		if ( 'richtext' === $type ) { return wp_kses_post( $value ); }
		if ( 'url' === $type ) { if ( ! wp_http_validate_url( $value ) ) { throw new \InvalidArgumentException( 'Expected public HTTP(S) URL.' ); } return esc_url_raw( $value ); }
		return 'slug' === $type ? sanitize_title( $value ) : sanitize_text_field( $value );
	}
	public static function values( string $type, array $values ): array {
		$schema = self::get( $type ); $clean = array();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $schema['fields'][ $key ] ) ) { throw new \InvalidArgumentException( 'Unknown field: ' . $key ); }
			$clean[ $key ] = 'msc_game' === $type && 'release_dates' === $key ? GameFields::sanitize( $value ) : self::sanitize( $schema['fields'][ $key ], $value );
		}
		return $clean;
	}
	public static function enabled( string $type, ?int $site = null ): bool {
		return in_array( $type, get_blog_option( $site ?? get_current_blog_id(), 'msc_types', array() ), true );
	}
	public static function participate( int $site, array $types ): void {
		if ( ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Network permission required.' ); }
		$blog = get_site( $site );
		if ( ! $blog || (int) $blog->network_id !== get_current_network_id() ) { throw new \InvalidArgumentException( 'Invalid site.' ); }
		foreach ( $types as $type ) { self::get( $type ); }
		$old = get_blog_option( $site, 'msc_types', array() );
		update_blog_option( $site, 'msc_types', array_values( $types ) );
		switch_to_blog( $site );
		try { Permissions::ensure_roles(); } finally { restore_current_blog(); }
		foreach ( array_unique( array_merge( $old, $types ) ) as $type ) { Queue::seed( $site, $type ); }
		update_blog_option( $site, 'msc_flush_rewrite', 1 );
	}
}
