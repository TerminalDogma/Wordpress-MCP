<?php
namespace MSC;

/** Searchable choices for the full Shared Content editor, including network records without a local post. */
final class FieldPicker {
	public static function selection( $value, string $target ): array {
		$selected = array();
		foreach ( (array) $value as $item ) {
			if ( '' === (string) $item ) { continue; }
			$label = $item;
			if ( $target ) {
				$record = Plugin::store()->get( $item );
				$label = $record['data']['title'] ?? 'Unavailable record';
			}
			$selected[] = array( 'id' => (string) $item, 'label' => $label );
		}
		return $selected;
	}

	public static function control( string $name, string $id, array $field, $value, ?array $selected = null, array $picker = array() ): void {
		$multiple = in_array( $field['type'], array( 'multiselect', 'relationship_list' ), true );
		$target = $field['target'] ?? '';
		$choices = array();
		$selected ??= self::selection( $value, $target );
		if ( ! $target ) {
			$choices = array_map( static fn( $choice ) => array( 'id' => (string) $choice, 'label' => (string) $choice ), Taxonomies::choices( $field, $value ) );
		}
		$config = $picker + array( 'multiple' => $multiple, 'target' => $target, 'choices' => $choices, 'selected' => $selected );
		$stored = $multiple ? wp_json_encode( array_column( $selected, 'id' ) ) : ( $selected[0]['id'] ?? '' );
		echo '<div class="msc-field-picker" data-picker="' . esc_attr( wp_json_encode( $config ) ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $stored ) . '">';
		echo '<input type="search" class="regular-text" id="' . esc_attr( $id ) . '" autocomplete="off" placeholder="' . esc_attr( $target ? 'Search by name or title' : 'Search choices' ) . '">';
		echo '<ul class="msc-field-picker-selected"></ul><p class="description" role="status"></p></div>';
	}

	public static function search(): void {
		check_ajax_referer( 'msc_field_picker_' . get_current_blog_id(), 'nonce' );
		if ( ! ( current_user_can( 'msc_create_global' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_override_local' ) ) ) { wp_send_json_error( null, 403 ); }
		$type = is_string( $_GET['type'] ?? null ) ? sanitize_key( $_GET['type'] ) : '';
		$query = is_string( $_GET['term'] ?? null ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_GET['term'] ) ) ), 0, 150 ) : '';
		if ( mb_strlen( $query ) < 2 || ! isset( Schema::all()[ $type ] ) ) { wp_send_json( array() ); }

		global $wpdb;
		$table = Database::table( 'records' );
		$title = "JSON_UNQUOTE(JSON_EXTRACT(data,'$.title'))";
		$prefix = $wpdb->esc_like( mb_strtolower( $query, 'UTF-8' ) ) . '%';
		$matches = $wpdb->get_results( $wpdb->prepare(
			"SELECT uuid AS id, $title AS label FROM $table WHERE type=%s AND state='active' AND LOWER($title) LIKE %s ORDER BY (LOWER($title) LIKE %s) DESC, label, id LIMIT 30",
			$type, '%' . $prefix, $prefix
		), ARRAY_A );
		$matches = $matches ?: array();
		foreach ( $matches as &$match ) {
			$match['key'] = PersonIdentity::name( $match['label'] );
			$match['label'] = Presentation::text( $match['label'] );
		}
		unset( $match );
		wp_send_json( $matches );
	}
}
