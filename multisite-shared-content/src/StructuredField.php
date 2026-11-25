<?php
namespace MSC;

/** Friendly controls for existing structured values, with the original storage format. */
final class StructuredField {
	public static function uses( string $key, array $field ): bool {
		return ( 'json' === $field['type'] && 'release_dates' !== $key && 'chronology' !== ( $field['format'] ?? '' ) )
			|| ( 'multiselect' === $field['type'] && empty( $field['taxonomy'] ) && empty( $field['options'] ) );
	}

	private static function spec( string $key, bool $list ): array {
		$release = array( array( 'date', 'Date', 'date' ), array( 'label', 'Release label', 'text' ), array( 'region', 'Region', 'text' ), array( 'url', 'Source URL', 'url' ) );
		return match ( $key ) {
			'movie_faq' => array( 'mode' => 'rows', 'item' => 'question', 'columns' => array( array( 'question', 'Question', 'text' ), array( 'answer', 'Answer', 'textarea' ) ) ),
			'watch_options' => array( 'mode' => 'rows', 'item' => 'viewing option', 'columns' => array( array( 'service', 'Service', 'text' ), array( 'url', 'Watch URL', 'url' ), array( 'region', 'Region', 'text' ), array( 'kind', 'Availability (stream, rent, buy)', 'text' ), array( 'checked', 'Last checked', 'date' ) ) ),
			'release_history', 'additional_release_dates' => array( 'mode' => 'rows', 'item' => 'release', 'columns' => $release ),
			'external_ids' => array( 'mode' => 'identifiers', 'item' => 'identifier' ),
			'platform_ids' => array( 'mode' => 'values', 'item' => 'platform ID', 'type' => 'number' ),
			default => $list ? array( 'mode' => 'values', 'item' => 'value', 'type' => 'text' ) : array( 'mode' => 'generic' ),
		};
	}

	private static function node( $value, bool $empty_map = false ): array {
		if ( is_array( $value ) ) {
			$type = array_is_list( $value ) && ! ( $empty_map && ! $value ) ? 'list' : 'map';
			$items = array();
			foreach ( $value as $key => $child ) {
				$items[] = array( 'key' => (string) $key, 'node' => self::node( $child ) );
			}
			return array( 'type' => $type, 'items' => $items );
		}
		if ( is_bool( $value ) ) { return array( 'type' => 'boolean', 'value' => $value ); }
		if ( is_int( $value ) || is_float( $value ) ) { return array( 'type' => 'number', 'value' => wp_json_encode( $value, JSON_PRESERVE_ZERO_FRACTION ) ); }
		return null === $value ? array( 'type' => 'null' ) : array( 'type' => 'text', 'value' => (string) $value );
	}

	public static function control( string $key, string $name, string $id, array $value, bool $list ): void {
		$spec = self::spec( $key, $list );
		$root = self::node( $value, in_array( $spec['mode'], array( 'identifiers', 'generic' ), true ) );
		$config = array( 'spec' => $spec, 'root' => $root, 'key' => $key );
		echo '<div class="msc-structured" id="' . esc_attr( $id ) . '" role="group" aria-labelledby="' . esc_attr( $id . '-label' ) . '" data-structured="' . esc_attr( wp_json_encode( $config ) ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_json_encode( $value, JSON_PRESERVE_ZERO_FRACTION ) ) . '"><div class="msc-structured-body"></div></div>';
	}

	public static function preview( $value ): string {
		if ( ! is_array( $value ) ) {
			$text = is_bool( $value ) ? ( $value ? 'Yes' : 'No' ) : (string) $value;
			return esc_html( '' === $text ? '—' : $text );
		}
		if ( ! $value ) { return '<p class="description">No entries.</p>'; }
		$html = '<dl class="msc-structured-preview">';
		$list = array_is_list( $value );
		foreach ( $value as $key => $item ) {
			$label = $list ? 'Item ' . ( $key + 1 ) : ucfirst( str_replace( '_', ' ', (string) $key ) );
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . self::preview( $item ) . '</dd>';
		}
		return $html . '</dl>';
	}
}
