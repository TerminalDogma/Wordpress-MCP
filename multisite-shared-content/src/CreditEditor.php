<?php
namespace MSC;

/** One editor row per Person, backed by the existing ordered credit fields. */
final class CreditEditor {
	public static function fields( array $schema ): array {
		return array_filter( $schema['fields'], static fn( $field ) => empty( $field['hidden'] ) && 'relationship_list' === $field['type'] && 'msc_person' === ( $field['target'] ?? '' ) );
	}

	public static function legacy( array $schema ): array {
		$roles = self::fields( $schema );
		return array_keys( array_filter( array( 'cast' => 'actors', 'director' => 'directors', 'writers' => 'screenwriters' ), static fn( $role, $old ) => isset( $roles[ $role ], $schema['fields'][ $old ] ) && in_array( $schema['fields'][ $old ]['type'], array( 'text', 'richtext' ), true ), ARRAY_FILTER_USE_BOTH ) );
	}

	public static function keys( array $schema ): array {
		$fields = self::fields( $schema );
		$keys = array_keys( $fields );
		if ( isset( $fields['actors'], $schema['fields']['cast_roles'] ) && empty( $schema['fields']['cast_roles']['hidden'] ) ) { $keys[] = 'cast_roles'; }
		return $keys;
	}

	public static function control( array $schema, array $values, array $overrides, bool $global, string $prefix ): void {
		$fields = self::fields( $schema );
		if ( ! $fields ) { return; }
		$people = $roles = $fields_values = array();
		$labels = array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Screenwriter', 'producers' => 'Producer', 'creators' => 'Creator', 'cinematographers' => 'Cinematographer', 'film_editors' => 'Editor', 'composers' => 'Composer' );
		$order = array_flip( array_keys( $labels ) );
		uksort( $fields, static fn( $a, $b ) => ( $order[ $a ] ?? PHP_INT_MAX ) <=> ( $order[ $b ] ?? PHP_INT_MAX ) );
		$raw_cast = (string) ( $values['cast_roles'] ?? '' );
		$has_cast = in_array( 'cast_roles', self::keys( $schema ), true );
		$can_change_cast = $has_cast && ( $global || false !== ( $schema['fields']['cast_roles']['override'] ?? true ) );
		$characters = CastRoles::by_actor( $raw_cast );
		foreach ( $fields as $name => $field ) {
			$locked = ! $global && false === ( $field['override'] ?? true );
			$roles[ $name ] = array( 'label' => $labels[ $name ] ?? ( $field['label'] ?? ucwords( str_replace( '_', ' ', $name ) ) ), 'locked' => $locked );
			$fields_values[ $name ] = array_values( (array) ( $values[ $name ] ?? array() ) );
			foreach ( $fields_values[ $name ] as $uuid ) {
				if ( isset( $people[ $uuid ] ) ) { continue; }
				$record = Plugin::store()->get( $uuid );
				$label = (string) ( $record['data']['title'] ?? 'Unavailable Person' );
				$key = PersonIdentity::name( $label );
				$people[ $uuid ] = array( 'id' => $uuid, 'label' => Presentation::text( $label ), 'key' => $key, 'character' => $characters[ $key ] ?? '' );
			}
		}
		$lines = array();
		foreach ( preg_split( '/\R/u', $raw_cast, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $line ) {
			$lines[] = array( 'key' => PersonIdentity::name( explode( '|', $line, 2 )[0] ), 'text' => $line );
		}
		$config = array( 'roles' => $roles, 'people' => array_values( $people ), 'values' => $fields_values, 'hasCast' => $has_cast, 'editCast' => $can_change_cast, 'lines' => $lines, 'characters' => (object) $characters );
		echo '<section class="msc-credit-editor" aria-label="Cast and crew" data-credits="' . esc_attr( wp_json_encode( $config ) ) . '"><h2>Cast and crew</h2><p class="description">Find a Person, then assign their roles.</p>';
		if ( array_filter( $roles, static fn( $role ) => ! $role['locked'] ) ) {
			echo '<div class="msc-credit-search"><label>Role <select data-msc-add-role>';
			foreach ( $roles as $name => $role ) { if ( ! $role['locked'] ) { echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $role['label'] ) . '</option>'; } }
			echo '</select></label><label>Find a Person <input type="search" class="regular-text" data-msc-credit-search autocomplete="off" placeholder="Type at least two letters"></label></div><p class="description" role="status"></p>';
		}
		foreach ( $fields_values as $name => $ids ) {
			if ( $roles[ $name ]['locked'] ) { continue; }
			echo '<input type="hidden" data-msc-credit-value="' . esc_attr( $name ) . '" name="' . esc_attr( $prefix . '[' . $name . ']' ) . '" value="' . esc_attr( wp_json_encode( $ids ) ) . '">';
		}
		if ( $can_change_cast ) { echo '<input type="hidden" data-msc-credit-value="cast_roles" name="' . esc_attr( $prefix . '[cast_roles]' ) . '" value="' . esc_attr( $raw_cast ) . '">'; }
		echo '<ul class="msc-credit-list" data-msc-credit-people></ul>';
		if ( ! $global ) {
			echo '<details class="msc-credit-overrides"><summary>Credit overrides on this site</summary>';
			$labels = array_map( static fn( $role ) => $role['label'], array_filter( $roles, static fn( $role ) => ! $role['locked'] ) );
			if ( $can_change_cast ) { $labels['cast_roles'] = 'Cast characters'; }
			foreach ( $labels as $name => $label ) {
				echo '<label><input type="checkbox" data-msc-credit-override="' . esc_attr( $name ) . '" name="override[' . esc_attr( $name ) . ']" value="1"' . checked( array_key_exists( $name, $overrides ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			echo '</details>';
		}
		$legacy = array_filter( array_intersect_key( $values, array_flip( self::legacy( $schema ) ) ) );
		if ( $legacy ) {
			echo '<details class="msc-credit-legacy"><summary>Existing text credits</summary>';
			foreach ( $legacy as $name => $value ) { echo '<h3>' . esc_html( ucfirst( $name ) ) . '</h3>' . wp_kses_post( wpautop( (string) $value ) ); }
			echo '</details>';
		}
		echo '</section>';
	}
}
