<?php
namespace MSC;

/** Public presentation helpers always enforce this site's publication state. */
final class Presentation {
	private const INITIAL_CREDITS = 10;

	/** Decode source text before the output context applies HTML escaping. */
	public static function text( string $value ): string { return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); }
	public static function identities(): array {
		return NetworkSites::profiles();
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
		return $r && Schema::enabled( $r['type'] ) && GamePolicy::accepts( $r ) && 'active' === $r['state'] && ! empty( $r['mapping']['post_id'] ) && empty( $r['mapping']['suppressed'] ) && 'publish' === $r['mapping']['status'] && 'publish' === get_post_status( (int) $r['mapping']['post_id'] ) && ! post_password_required( (int) $r['mapping']['post_id'] ) ? GamePolicy::project( $r ) : null;
	}
	public static function url( array $r ): string { return (string) get_permalink( (int) $r['mapping']['post_id'] ); }
	/** Credit names belong to the referring title; profile publication controls the link. */
	public static function credit( string $uuid, ?string $target = null ): ?array {
		$r = Plugin::store()->resolved( $uuid );
		if ( ! $r || 'active' !== $r['state'] || ( $target && $target !== $r['type'] ) || ! GamePolicy::accepts( $r ) || '' === trim( $r['data']['title'] ?? '' ) ) { return null; }
		$public = self::record( $uuid );
		if ( ! $public && 'msc_person' !== $r['type'] && ! isset( EntityCatalog::definitions()[ $r['type'] ] ) ) { return null; }
		return array( 'title' => self::text( $r['data']['title'] ), 'uuid' => $r['uuid'], 'url' => $public ? self::url( $public ) : '', 'public' => $public );
	}
	/** Only published, accessible local Episodes contribute seasons or rows. */
	public static function season_page( string $show, $requested = null, $requested_page = null ): array {
		global $wpdb; $empty = array( 'seasons' => array(), 'season' => '', 'episodes' => array(), 'total' => 0, 'page' => 1, 'pages' => 1 );
		if ( ! Schema::enabled( 'msc_episode' ) || ! ( $owner = self::record( $show ) ) || 'msc_show' !== $owner['type'] ) { return $empty; }
		$records = Database::table( 'records' ); $maps = Database::table( 'maps' );
		$season = "CASE WHEN s.meta_value REGEXP '^(0|[1-9][0-9]{0,9})$' THEN s.meta_value ELSE 'unknown' END";
		$from = "$wpdb->posts p INNER JOIN $maps m ON m.post_id=p.ID AND m.site_id=" . (int) get_current_blog_id() . " INNER JOIN $records r ON r.id=m.record_id LEFT JOIN $wpdb->postmeta parent ON parent.post_id=p.ID AND parent.meta_key='msc_parent_show' LEFT JOIN $wpdb->postmeta s ON s.post_id=p.ID AND s.meta_key='msc_season_number'";
		$where = $wpdb->prepare( "p.post_type='msc_episode' AND p.post_status='publish' AND p.post_password='' AND m.status='publish' AND m.suppressed=0 AND r.state='active' AND parent.meta_value=%s", $show );
		$seasons = $wpdb->get_col( "SELECT DISTINCT $season AS season FROM $from WHERE $where ORDER BY season='unknown' ASC,CAST(season AS UNSIGNED) ASC" ); if ( ! $seasons ) { return $empty; }
		$selected = is_string( $requested ) && in_array( $requested, $seasons, true ) ? $requested : $seasons[0]; $where .= $wpdb->prepare( " AND $season=%s", $selected );
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM $from WHERE $where" ); $pages = max( 1, (int) ceil( $total / 50 ) );
		$page = is_scalar( $requested_page ) && preg_match( '/^[1-9][0-9]{0,6}$/D', (string) $requested_page ) ? min( $pages, (int) $requested_page ) : 1;
		$from .= " LEFT JOIN $wpdb->postmeta n ON n.post_id=p.ID AND n.meta_key='msc_episode_number'"; $number = "CASE WHEN n.meta_value REGEXP '^[0-9]+$' THEN CAST(n.meta_value AS UNSIGNED) ELSE NULL END";
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT r.id,$number AS number_value FROM $from WHERE $where ORDER BY number_value IS NULL ASC,number_value ASC,r.id ASC LIMIT 50 OFFSET %d", ( $page - 1 ) * 50 ) );
		return array( 'seasons' => $seasons, 'season' => $selected, 'episodes' => array_values( array_filter( array_map( array( self::class, 'record' ), $ids ) ) ), 'total' => $total, 'page' => $page, 'pages' => $pages );
	}
	/** Resolve the catalog art type and fall back to the featured image. */
	public static function artwork( array $r, string $size = 'large', array $preferred = array() ): array {
		$d = $r['data']; $keys = array( 'msc_movie' => 'poster', 'msc_show' => 'poster', 'msc_episode' => 'episode_image', 'msc_person' => 'headshot', 'msc_game' => 'poster', 'msc_franchise' => 'poster', 'msc_comic' => 'poster', 'msc_comic_issue' => 'poster', 'msc_comic_arc' => 'poster' );
		$field = $keys[ $r['type'] ] ?? ( isset( EntityCatalog::definitions()[ $r['type'] ] ) ? 'logo' : 'featured_image' );
		foreach ( array( $preferred, $d[ $field ] ?? array(), $d['featured_image'] ?? array() ) as $reference ) {
			if ( ! $reference ) { continue; }
			$url = Media::url( (array) $reference, $size );
			if ( $url ) { return array( 'reference' => $reference, 'url' => $url ); }
		}
		return array();
	}
	public static function image( array $r, string $class = '', string $size = 'large' ): string {
		$d = $r['data']; $url = self::artwork( $r, $size )['url'] ?? Media::url( array(), $size );
		return $url ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $url ) . '" alt="' . esc_attr( $d['title'] ?? '' ) . '" loading="lazy" decoding="async">' : '<div class="art-placeholder ' . esc_attr( $class ) . '" aria-label="No image available"><span>' . esc_html( mb_substr( $d['title'] ?? '?', 0, 1 ) ) . '</span></div>';
	}
	public static function card( array $r ): void {
		$label = array( 'msc_movie' => 'Movie', 'msc_show' => 'TV Show', 'msc_episode' => 'Episode', 'msc_person' => 'Person', 'msc_game' => 'Video Game' )[ $r['type'] ] ?? ( EntityCatalog::definitions()[ $r['type'] ][1] ?? ( Comics::singular( $r['type'] ) ?: 'Content' ) );
		echo '<article class="catalog-card type-' . esc_attr( $r['type'] ) . '"><a href="' . esc_url( self::url( $r ) ) . '">' . '<span class="catalog-card-media">' . self::image( $r, 'card-art', 'medium_large' ) . ReviewScores::badge( $r ) . '</span><div class="card-copy"><span class="eyebrow">' . esc_html( $label ) . '</span><h3>' . esc_html( $r['data']['title'] ?? '' ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $r['data']['excerpt'] ?? '' ), 16 ) ) . '</p></div></a>' . Attribution::collect( $r, array( 'title', 'excerpt' ) ) . Attribution::collect_image( $r ) . '</article>';
	}
	public static function credits( array $r, bool $compact = false, bool $crew_cards = false ): void {
		$crew = array();
		$characters = CastRoles::by_actor( $r['data']['cast_roles'] ?? '' );
		$groups = array( 'actors' => 'Cast', 'directors' => 'Directors', 'screenwriters' => 'Writers', 'producers' => 'Producers', 'creators' => 'Creators' );
		if ( $crew_cards ) { $groups += MovieEditorial::CREW; }
		foreach ( $groups as $key => $label ) {
			$people = array(); foreach ( (array) ( $r['data'][ $key ] ?? array() ) as $uuid ) { $person = self::credit( $uuid, 'msc_person' ); if ( $person ) { $people[] = $person; } }
			if ( ! $people ) { continue; }
			if ( $crew_cards && 'directors' === $key && 1 === count( $people ) ) { $label = 'Director'; }
			if ( $compact && 'actors' !== $key ) {
				$crew[ $label ] = $people;
				continue;
			}
			$heading = $compact ? 'h3' : 'h2';
			$roles = 'actors' === $key ? $characters : array();
			echo '<section class="credit-section"' . ( 'actors' === $key ? ' id="cast"' : '' ) . '><' . $heading . '>' . esc_html( $label ) . '</' . $heading . '>';
			self::credit_cards( array_slice( $people, 0, self::INITIAL_CREDITS ), $roles );
			$more = array_slice( $people, self::INITIAL_CREDITS );
			if ( $more ) {
				self::credit_overflow( strtolower( $label ), static fn() => self::credit_cards( $more, $roles ) );
			}
			echo '</section>';
		}
		if ( $crew ) {
			echo '<div class="title-crew"><h3>Crew</h3>';
			if ( $crew_cards ) {
				self::credit_groups( $crew, 'crew', static function ( array $groups ): void {
					foreach ( $groups as $label => $people ) {
						echo '<div class="movie-crew-group"><h4>' . esc_html( $label ) . '</h4>';
						self::credit_cards( $people, array() );
						echo '</div>';
					}
				} );
			} else { self::credit_names( $crew, 'crew' ); }
			echo '</div>';
		}
	}
	private static function credit_cards( array $people, array $roles ): void {
		echo '<div class="people-grid">';
		foreach ( $people as $person ) {
			echo '<div class="person-credit">';
			$character = $roles[ PersonIdentity::name( $person['title'] ) ] ?? '';
			$name = '<span class="person-card-name">' . esc_html( $person['title'] ) . '</span>';
			if ( $character ) { $name .= '<span class="person-card-character">' . esc_html( $character ) . '</span>'; }
			if ( $person['public'] ) { echo '<a class="person-card" href="' . esc_url( $person['url'] ) . '">' . self::image( $person['public'], '', 'medium' ) . $name . '</a>' . Attribution::collect( $person['public'], array( 'title' ) ) . Attribution::collect_image( $person['public'] ); }
			else { echo '<span class="person-card person-credit-name">' . $name . '</span>'; }
			echo '</div>';
		}
		echo '</div>';
	}
	/** Compact grouped credits. Each entry has a title and an optional profile URL. */
	public static function credit_names( array $groups, string $label, string $class = '' ): void {
		self::credit_groups( $groups, $label, static fn( array $rows ) => self::credit_rows( $rows, $class ) );
	}

	private static function credit_groups( array $groups, string $label, callable $render ): void {
		$remaining = self::INITIAL_CREDITS;
		$first = $more = array();
		foreach ( $groups as $role => $people ) {
			$first[ $role ] = array_slice( $people, 0, $remaining );
			$more[ $role ] = array_slice( $people, $remaining );
			$remaining -= count( $first[ $role ] );
		}
		$render( array_filter( $first ) );
		$more = array_filter( $more );
		if ( $more ) {
			self::credit_overflow( $label, static fn() => $render( $more ) );
		}
	}
	private static function credit_rows( array $groups, string $class ): void {
		echo '<dl class="' . esc_attr( $class ) . '">';
		foreach ( $groups as $label => $people ) {
			$names = array_map( array( self::class, 'credit_link' ), $people );
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . implode( ', ', $names ) . '</dd></div>';
		}
		echo '</dl>';
	}
	private static function credit_overflow( string $label, callable $render ): void {
		echo '<details class="credit-overflow"><summary><span class="credit-show-more">' . esc_html( 'Show more ' . $label ) . '</span><span class="credit-show-less">' . esc_html( 'Show less ' . $label ) . '</span></summary>';
		$render();
		echo '</details>';
	}
	private static function credit_link( array $person ): string {
		$name = esc_html( $person['title'] );
		return ! empty( $person['url'] ) ? '<a href="' . esc_url( $person['url'] ) . '">' . $name . '</a>' : $name;
	}
	public static function filmography( string $person ): array {
		$meta = array( 'relation' => 'OR' ); foreach ( array( 'actors', 'directors', 'screenwriters', 'producers', 'creators' ) as $field ) { $meta[] = array( 'key' => 'msc_' . $field, 'value' => $person, 'compare' => 'LIKE' ); }
		$posts = get_posts( array( 'post_type' => array( 'msc_movie', 'msc_show', 'msc_episode' ), 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_query' => $meta, 'orderby' => 'title', 'order' => 'ASC' ) );
		$result = array(); foreach ( $posts as $post ) { $global = Plugin::store()->by_post( $post->ID ); $r = $global ? self::record( $global['id'] ) : null; if ( ! $r ) { continue; } $roles = array(); foreach ( array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Writer', 'producers' => 'Producer', 'creators' => 'Creator' ) as $key => $label ) { if ( in_array( $person, (array) ( $r['data'][ $key ] ?? array() ), true ) ) { $roles[] = $label; } } if ( $roles ) { $r['credit_roles'] = $roles; $result[] = $r; } }
		return $result;
	}
}
