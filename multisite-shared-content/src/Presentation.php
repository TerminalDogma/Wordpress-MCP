<?php
namespace MSC;

/** Public presentation helpers always enforce this site's publication state. */
final class Presentation {
	public static function identities(): array {
		return array(
			'a' => array( 'name' => 'Site A', 'label' => 'Site A', 'tagline' => 'Shared content demo', 'color' => '#a9e34b', 'ink' => '#142012' ),
			'b' => array( 'name' => 'Site B', 'label' => 'Site B', 'tagline' => 'Shared content demo', 'color' => '#b33123', 'ink' => '#fff8ec' ),
			'c' => array( 'name' => 'Site C', 'label' => 'Site C', 'tagline' => 'Shared content demo', 'color' => '#c4a4ff', 'ink' => '#1b1037' ),
		);
	}
	public static function identity(): array {
		$key = get_option( 'msc_site_identity', 'a' ); return self::identities()[ $key ] ?? self::identities()['a'];
	}
	public static function boot(): void {
		add_action( 'admin_bar_menu', static function ( $bar ) {
			if ( ! get_option( 'msc_site_identity' ) ) { return; }
			$i = self::identity(); $bar->add_node( array( 'id' => 'msc-identity', 'title' => esc_html( $i['label'] ), 'href' => home_url( '/' ) ) );
		}, 80 );
		$style = static function () {
			if ( ! get_option( 'msc_site_identity' ) ) { return; }
			$i = self::identity(); echo '<style>#wpadminbar #wp-admin-bar-msc-identity>.ab-item{background:' . esc_html( $i['color'] ) . ';color:' . esc_html( $i['ink'] ) . ';font-weight:700}body.wp-admin #wpadminbar{border-bottom:4px solid ' . esc_html( $i['color'] ) . '}</style>';
		};
		add_action( 'admin_head', $style ); add_action( 'wp_head', $style );
	}
	public static function record( $id ): ?array {
		$r = Plugin::store()->resolved( $id );
		return $r && Schema::enabled( $r['type'] ) && 'active' === $r['state'] && ! empty( $r['mapping']['post_id'] ) && empty( $r['mapping']['suppressed'] ) && 'publish' === $r['mapping']['status'] && 'publish' === get_post_status( (int) $r['mapping']['post_id'] ) && ! post_password_required( (int) $r['mapping']['post_id'] ) ? $r : null;
	}
	public static function url( array $r ): string { return (string) get_permalink( (int) $r['mapping']['post_id'] ); }
	public static function image( array $r, string $class = '', string $size = 'large' ): string {
		$d = $r['data']; $keys = array( 'msc_movie' => 'poster', 'msc_show' => 'poster', 'msc_episode' => 'episode_image', 'msc_person' => 'headshot' );
		$url = Media::url( (array) ( $d[ $keys[ $r['type'] ] ?? 'featured_image' ] ?? array() ), $size );
		if ( ! $url ) { $url = Media::url( (array) ( $d['featured_image'] ?? array() ), $size ); }
		return $url ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $d['title'] ?? '' ) . '" loading="lazy" decoding="async">' : '<div class="art-placeholder ' . esc_attr( $class ) . '" aria-label="No image available"><span>' . esc_html( mb_substr( $d['title'] ?? '?', 0, 1 ) ) . '</span></div>';
	}
	public static function card( array $r ): void {
		$label = array( 'msc_movie' => 'Movie', 'msc_show' => 'TV Show', 'msc_episode' => 'Episode', 'msc_person' => 'Person' )[ $r['type'] ] ?? 'Content';
		echo '<article class="catalog-card type-' . esc_attr( $r['type'] ) . '"><a href="' . esc_url( self::url( $r ) ) . '">' . self::image( $r, 'card-art', 'medium_large' ) . '<div class="card-copy"><span class="eyebrow">' . esc_html( $label ) . '</span><h3>' . esc_html( $r['data']['title'] ?? '' ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $r['data']['excerpt'] ?? '' ), 16 ) ) . '</p></div></a>' . Attribution::render( $r, array( 'title', 'excerpt' ) ) . Attribution::image_html( $r ) . '</article>';
	}
	public static function credits( array $r ): void {
		$groups = array( 'actors' => 'Cast', 'directors' => 'Directors', 'screenwriters' => 'Writers', 'producers' => 'Producers', 'creators' => 'Creators' );
		foreach ( $groups as $key => $label ) {
			$people = array(); foreach ( (array) ( $r['data'][ $key ] ?? array() ) as $uuid ) { $person = self::record( $uuid ); if ( $person && 'msc_person' === $person['type'] ) { $people[] = $person; } }
			if ( ! $people ) { continue; }
			echo '<section class="credit-section"><h2>' . esc_html( $label ) . '</h2><div class="people-grid">';
			foreach ( $people as $person ) { echo '<div class="person-credit"><a class="person-card" href="' . esc_url( self::url( $person ) ) . '">' . self::image( $person, '', 'medium' ) . '<span>' . esc_html( $person['data']['title'] ) . '</span></a>' . Attribution::render( $person, array( 'title' ) ) . Attribution::image_html( $person ) . '</div>'; }
			echo '</div></section>';
		}
	}
	public static function filmography( string $person ): array {
		$meta = array( 'relation' => 'OR' ); foreach ( array( 'actors', 'directors', 'screenwriters', 'producers', 'creators' ) as $field ) { $meta[] = array( 'key' => 'msc_' . $field, 'value' => $person, 'compare' => 'LIKE' ); }
		$posts = get_posts( array( 'post_type' => array( 'msc_movie', 'msc_show', 'msc_episode' ), 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_query' => $meta, 'orderby' => 'title', 'order' => 'ASC' ) );
		$result = array(); foreach ( $posts as $post ) { $global = Plugin::store()->by_post( $post->ID ); $r = $global ? self::record( $global['id'] ) : null; if ( ! $r ) { continue; } $roles = array(); foreach ( array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Writer', 'producers' => 'Producer', 'creators' => 'Creator' ) as $key => $label ) { if ( in_array( $person, (array) ( $r['data'][ $key ] ?? array() ), true ) ) { $roles[] = $label; } } if ( $roles ) { $r['credit_roles'] = $roles; $result[] = $r; } }
		return $result;
	}
}
