<?php
namespace MSC;

final class PersonReview {
	public static function boot(): void {
		add_action(
			'admin_menu',
			static function () {
				if ( current_user_can( 'msc_edit_global' ) && Schema::enabled( 'msc_person' ) ) {
					add_submenu_page( 'msc', 'Duplicate People', 'Duplicate People', 'msc_edit_global', 'msc-person-duplicates', array( self::class, 'page' ) );
				}
			},
			20
		);
		add_action(
			'admin_notices',
			static function () {
				if ( 'edit-msc_person' === ( get_current_screen()->id ?? '' ) && current_user_can( 'msc_edit_global' ) ) {
					echo '<div class="notice notice-info"><p><a href="' . esc_url( self::url() ) . '">Check for duplicate People, including drafts</a>.</p></div>';
				}
			}
		);
	}
	public static function url(): string {
		return admin_url( 'admin.php?page=msc-person-duplicates' );
	}
	public static function choices( array $credits, array $matches ): void {
		echo '<table class="widefat striped msc-person-choices"><thead><tr><th>Credited Person</th><th>Person record</th></tr></thead><tbody>';
		foreach ( $credits as $key => $credit ) {
			$match = $matches[ $key ];
			echo '<tr><th>' . esc_html( $credit['name'] ) . '<br><small>' . esc_html( implode( ', ', $credit['roles'] ) ) . '</small></th><td><select name="people[' . esc_attr( $key ) . ']" aria-label="Person for ' . esc_attr( $credit['name'] ) . '">';
			echo '<option value="">' . ( $match['possible'] ? 'Choose an existing Person or confirm a distinct person' : 'Choose a Person record' ) . '</option><option value="skip">Do not link this Person</option>';
			foreach ( $match['strong'] + $match['possible'] as $r ) {
				echo '<option value="' . esc_attr( $r['uuid'] ) . '"' . selected( $match['suggested'], $r['uuid'], false ) . disabled( 'active' !== $r['state'], true, false ) . '>' . esc_html( 'Reuse: ' . PersonIdentity::label( $r ) ) . '</option>';
			}
			if ( ! $match['strong'] && current_user_can( 'msc_create_global' ) ) {
				$new = $match['possible'] ? 'new_distinct' : 'new';
				echo '<option value="' . $new . '"' . selected( $match['suggested'], $new, false ) . '>' . esc_html( $match['possible'] ? 'Create a distinct person with this name' : 'Create new Person: ' . $credit['name'] ) . '</option>';
			}
			echo '</select>';
			if ( $match['possible'] ) {
				echo '<p class="description">Possible duplicate found. Check the existing record’s identity before creating another draft.</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	public static function groups(): array {
		$buckets = $records = PersonIdentity::catalogue();
		$buckets = array();
		foreach ( $records as $uuid => $r ) {
			$name = PersonIdentity::name( $r['data']['title'] );
			if ( '' !== $name ) {
				$buckets[ 'name:' . $name ][] = $uuid;
			}
			foreach ( array_intersect_key( (array) $r['data']['external_ids'], array_flip( array( 'tvmaze', 'wikidata', 'imdb' ) ) ) as $provider => $id ) {
				if ( '' !== (string) $id ) {
					$buckets[ $provider . ':' . $id ][] = $uuid;
				}
			}
		}
		$links = array();
		foreach ( $buckets as $uuids ) {
			if ( count( $uuids ) < 2 ) {
				continue;
			}
			foreach ( $uuids as $uuid ) {
				$links[ $uuid ] = array_unique( array_merge( $links[ $uuid ] ?? array(), $uuids ) );
			}
		}
		$groups = $seen = array();
		foreach ( array_keys( $links ) as $uuid ) {
			if ( isset( $seen[ $uuid ] ) ) {
				continue;
			}
			$todo  = array( $uuid );
			$group = array();
			while ( $todo ) {
				$id = array_pop( $todo );
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$group[]     = $records[ $id ];
				foreach ( $links[ $id ] ?? array() as $other ) {
					if ( ! isset( $seen[ $other ] ) ) {
						$todo[] = $other;
					}
				}
			}
			$groups[] = $group;
		}
		return $groups;
	}
	public static function notice( array $r ): void {
		if ( 'msc_person' !== $r['type'] || ! current_user_can( 'msc_edit_global' ) ) {
			return;
		}
		foreach ( self::groups() as $group ) {
			if ( in_array( $r['uuid'], array_column( $group, 'uuid' ), true ) ) {
				echo '<div class="notice notice-warning inline"><p>This Person has possible duplicate records. <a href="' . esc_url( self::url() ) . '">Review Duplicate People</a> before creating or publishing another entry.</p></div>';
				return;
			}
		}
	}
	public static function page(): void {
		Permissions::require( 'msc_edit_global', 'msc_person' );
		$groups = self::groups();
		$page   = isset( $_GET['msc_paged'] ) && is_scalar( $_GET['msc_paged'] ) ? max( 1, absint( $_GET['msc_paged'] ) ) : 1;
		echo '<div class="wrap msc"><h1>Duplicate People</h1><p>Possible matches across all shared Person records, including unpublished drafts and archived records. A matching name is a review signal; different source IDs can identify different people. One Person can hold Actor, Director, Writer and other professions.</p><p>Open the records below to compare identities and choose the Person to use in future imports. Existing records and links are preserved for review.</p>';
		foreach ( array_slice( $groups, ( $page - 1 ) * 25, 25 ) as $group ) {
			echo '<section class="msc-person-duplicate"><h2>' . esc_html( $group[0]['data']['title'] ?: '(Untitled Person)' ) . '</h2><ul>';
			foreach ( $group as $r ) {
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=msc&mode=global&record=' . $r['uuid'] ) ) . '">' . esc_html( PersonIdentity::label( $r ) ) . '</a></li>';
			}
			echo '</ul></section>';
		}
		if ( ! $groups ) {
			echo '<p>No possible duplicates found.</p>';
		}
		$pages = max( 1, (int) ceil( count( $groups ) / 25 ) );
		echo '<p>' . esc_html( count( $groups ) . ' possible duplicate groups · Page ' . $page . ' of ' . $pages ) . '</p>';
		foreach ( array(
			$page - 1 => 'Previous page',
			$page + 1 => 'Next page',
		) as $number => $label ) {
			if ( $number >= 1 && $number <= $pages ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( 'msc_paged', $number, self::url() ) ) . '">' . esc_html( $label ) . '</a> ';
			}
		}
		echo '</div>';
	}
}
