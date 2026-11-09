<?php
namespace MSC;
final class Seo {
	public static function enforced(): bool {
		$types = array();
		if ( is_singular() ) {
			$types[] = get_post_type();
		}
		if ( is_post_type_archive() ) {
			$types = array_merge( $types, (array) get_query_var( 'post_type' ) );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			$tax  = get_taxonomy( $term->taxonomy ?? '' );
			if ( $tax ) {
				$types = array_merge( $types, $tax->object_type );
			}
		}
		foreach ( $types as $type ) {
			if ( isset( Schema::all()[ $type ] ) && ( Schema::get( $type )['noindex'] ?? true ) ) {
				return true;
			}
		}
		return false;
	}
	public static function robots( array $robots ): array {
		if ( self::enforced() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
		}
		return $robots;
	}
	public static function sitemap_types( array $types ): array {
		foreach ( Schema::all() as $type => $schema ) {
			if ( $schema['noindex'] ?? true ) {
				unset( $types[ $type ] );
			}
		}
		return $types;
	}
	public static function sitemap_taxonomies( array $types ): array {
		foreach ( $types as $name => $tax ) {
			foreach ( $tax->object_type as $type ) {
				if ( isset( Schema::all()[ $type ] ) && ( Schema::get( $type )['noindex'] ?? true ) ) {
					unset( $types[ $name ] );
				}
			}
		}
		return $types;
	}
	public static function headers(): void {
		if ( self::enforced() && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex', true );
		}
	}
	public static function metadata(): void {
		if ( ! is_singular() || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$r = Plugin::store()->by_post( get_queried_object_id() );
		if ( $r ) {
			$data = Plugin::store()->resolved( $r['id'] )['data'];
			if ( ! empty( $data['seo_description'] ) ) {
				echo '<meta name="description" content="' . esc_attr( $data['seo_description'] ) . '">' . "\n";
			}
		}
	}
	public static function seo_value( $value, string $field ) {
		if ( ! is_singular() ) {
			return $value;
		}
		$r = Plugin::store()->by_post( get_queried_object_id() );
		return $r ? ( Plugin::store()->resolved( $r['id'] )['data'][ $field ] ?? $value ) : $value;
	}
	public static function boot(): void {
		add_filter( 'wp_robots', array( self::class, 'robots' ), PHP_INT_MAX );
		add_action( 'send_headers', array( self::class, 'headers' ), PHP_INT_MAX );
		add_action( 'wp_head', array( self::class, 'metadata' ) );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'sitemap_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'sitemap_taxonomies' ) );
		foreach ( array( 'wpseo_title', 'rank_math/frontend/title', 'pre_get_document_title' ) as $hook ) {
			add_filter( $hook, static fn( $v ) => self::seo_value( $v, 'seo_title' ), 20 );
		}
		foreach ( array( 'wpseo_metadesc', 'rank_math/frontend/description' ) as $hook ) {
			add_filter( $hook, static fn( $v ) => self::seo_value( $v, 'seo_description' ), 20 );
		}
		add_filter( 'wpseo_robots', static fn( $v ) => self::enforced() ? 'noindex, follow' : $v, PHP_INT_MAX );
		add_filter(
			'rank_math/frontend/robots',
			static function ( $v ) {
				if ( self::enforced() ) {
					$v['index'] = 'noindex';
				}
				return $v;
			},
			PHP_INT_MAX
		);
		foreach ( array( 'wpseo_sitemap_exclude_post_type', 'rank_math/sitemap/exclude_post_type' ) as $hook ) {
			add_filter( $hook, static fn( $exclude, $type ) => $exclude || isset( Schema::all()[ $type ] ) && ( Schema::get( $type )['noindex'] ?? true ), 10, 2 );
		}
	}
}
