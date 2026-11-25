<?php
namespace MSC;

/** One picker per catalog relationship, including older names with no linked profile. */
final class EntityEditor {
	private const NAME_PREFIX = 'name:';

	public static function fields( array $schema, bool $global ): array {
		$pairs = array();
		foreach ( EntityCatalog::definitions() as $target => list( $label, , , $links, $names ) ) {
			$link_field = $schema['fields'][ $links ] ?? array();
			$name_field = $schema['fields'][ $names ] ?? array();
			if ( 'relationship_list' !== ( $link_field['type'] ?? '' ) || $target !== ( $link_field['target'] ?? '' ) ||
				! in_array( $name_field['type'] ?? '', array( 'text', 'multiselect' ), true ) ||
				! empty( $link_field['hidden'] ) || ! empty( $name_field['hidden'] ) ||
				( ! $global && ( false === ( $link_field['override'] ?? true ) || false === ( $name_field['override'] ?? true ) ) ) ) {
				continue;
			}
			$pairs[ $links ] = array( 'label' => $label, 'names' => $names, 'field' => $name_field, 'target' => $target );
		}
		return $pairs;
	}

	public static function submitted( array $pairs, array $request ): array {
		return array_intersect_key( $pairs, (array) ( $request['entities'] ?? array() ) );
	}

	private static function overridden( array $record, string $links, string $names ): bool {
		$overrides = $record['overrides'] ?? array();
		return array_key_exists( $links, $overrides ) || array_key_exists( $names, $overrides );
	}

	private static function name_choice( string $name ): array {
		return array( 'id' => self::NAME_PREFIX . $name, 'label' => Presentation::text( $name ) );
	}

	private static function selection( array $data, string $links, array $pair ): array {
		$selected = FieldPicker::selection( $data[ $links ] ?? array(), $pair['target'] );
		$labels = array_column( $selected, 'label' );
		$seen = array_fill_keys( array_map( array( EntityCatalog::class, 'name' ), $labels ), true );
		foreach ( EntityCatalog::legacy_names( $pair['target'], $data[ $pair['names'] ] ?? '', $seen ) as $name ) {
			$normalized = EntityCatalog::name( $name );
			if ( isset( $seen[ $normalized ] ) ) { continue; }
			$selected[] = self::name_choice( $name );
			$seen[ $normalized ] = true;
		}
		return $selected;
	}

	public static function control( string $links, array $field, array $pair, array $record, bool $global ): void {
		$data = ( $global ? $record['global'] : $record['data'] ) ?? array();
		$selected = self::selection( $data, $links, $pair );
		echo '<div class="msc-entity-editor">';
		if ( ! $global ) {
			$inherited = array_column( self::selection( $record['global'], $links, $pair ), 'label' );
			echo '<details><summary>Global value</summary><p>' . esc_html( $inherited ? implode( ', ', $inherited ) : 'None' ) . '</p></details>';
			echo '<label><input type="checkbox" name="override[' . esc_attr( $links ) . ']" value="1" ' . checked( self::overridden( $record, $links, $pair['names'] ), true, false ) . '> Override on this site</label>';
		}
		$names = $pair['field'];
		$picker = array(
			'choices' => array_map( array( self::class, 'name_choice' ), Taxonomies::choices( $names ) ),
			'allowName' => empty( $names['taxonomy'] ) && empty( $names['options'] ),
			'singleName' => 'text' === $names['type'] && ! EntityCatalog::splits_legacy_names( $pair['target'] ),
		);
		$prefix = 'entities[' . $links . ']';
		echo '<input type="hidden" name="' . esc_attr( $prefix . '[original]' ) . '" value="' . esc_attr( wp_json_encode( array_column( $selected, 'id' ) ) ) . '">';
		FieldPicker::control( $prefix . '[selection]', 'msc-' . $links, $field, array(), $selected, $picker );
		echo '</div>';
	}

	private static function ids( $raw ): array {
		$ids = is_string( $raw ) ? json_decode( $raw, true, 32, JSON_THROW_ON_ERROR ) : null;
		if ( ! is_array( $ids ) || ! array_is_list( $ids ) || count( $ids ) > 200 ) {
			throw new \InvalidArgumentException( 'Choose up to 200 catalog entries.' );
		}
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) || ( ! str_starts_with( $id, self::NAME_PREFIX ) && ! wp_is_uuid( $id ) ) ) {
				throw new \InvalidArgumentException( 'Invalid catalog entry.' );
			}
		}
		return $ids;
	}

	public static function changes( array $pairs, array $request, array $record, bool $global, array &$values, array &$reset ): void {
		$data = $record['data'] ?? array();
		foreach ( $pairs as $links => $pair ) {
			$names = $pair['names'];
			if ( ! $global && empty( $request['override'][ $links ] ) ) {
				array_push( $reset, $links, $names );
				continue;
			}
			$submitted = $request['entities'][ $links ];
			$selection = self::ids( $submitted['selection'] ?? null );
			$original = self::ids( $submitted['original'] ?? null );
			$names_field = $pair['field'];
			$empty = 'text' === $names_field['type'] ? '' : array();
			$previous = $data[ $names ] ?? $empty;
			if ( $selection === $original ) {
				if ( ! $global && ! self::overridden( $record, $links, $names ) ) {
					$values[ $links ] = $data[ $links ] ?? array();
					$values[ $names ] = $previous;
				}
				continue;
			}
			$uuids = array();
			$labels = array();
			foreach ( $selection as $id ) {
				if ( str_starts_with( $id, self::NAME_PREFIX ) ) {
					$labels[] = substr( $id, strlen( self::NAME_PREFIX ) );
				} else {
					$uuids[] = $id;
				}
			}
			$linked = array_column( FieldPicker::selection( $uuids, $pair['target'] ), 'label' );
			$seen = array_fill_keys( array_map( array( EntityCatalog::class, 'name' ), $linked ), true );
			$previous_names = EntityCatalog::legacy_names( $pair['target'], $previous, $seen );
			$allowed = ! empty( $names_field['taxonomy'] ) || ! empty( $names_field['options'] ) ? Taxonomies::choices( $names_field, $previous_names ) : null;
			$retained = array();
			foreach ( $labels as $label ) {
				if ( null !== $allowed && ! in_array( $label, $allowed, true ) && ! in_array( $label, $previous_names, true ) ) {
					throw new \InvalidArgumentException( 'Choose a name from the suggestions.' );
				}
				$normalized = EntityCatalog::name( $label );
				if ( '' === $normalized || isset( $seen[ $normalized ] ) ) { continue; }
				$retained[] = $label;
				$seen[ $normalized ] = true;
			}
			if ( $retained === $previous_names ) {
				$values[ $names ] = $previous;
			} elseif ( 'multiselect' === $names_field['type'] ) {
				$values[ $names ] = $retained;
			} else {
				$values[ $names ] = EntityCatalog::splits_legacy_names( $pair['target'] ) ? implode( '; ', $retained ) : ( $retained[0] ?? '' );
			}
			$values[ $links ] = $uuids;
		}
	}
}
