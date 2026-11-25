<?php
namespace MSC;

/** A single editor control for directory links and names retained from imports. */
final class StreamingEditor {
	public const LINKS = 'streaming_service_records';
	public const NAME = 'streaming_service';
	private const IMPORTED = 'imported-service';

	public static function keys( array $schema, bool $global ): array {
		$links = $schema['fields'][ self::LINKS ] ?? array();
		$name = $schema['fields'][ self::NAME ] ?? array();
		if ( 'relationship_list' !== ( $links['type'] ?? '' ) || 'msc_streaming' !== ( $links['target'] ?? '' ) ||
			'text' !== ( $name['type'] ?? '' ) || ! empty( $links['hidden'] ) || ! empty( $name['hidden'] ) ||
			( ! $global && ( false === ( $links['override'] ?? true ) || false === ( $name['override'] ?? true ) ) ) ) {
			return array();
		}
		return array( self::LINKS, self::NAME );
	}

	private static function name( string $value ): string {
		return StreamingServices::name( Presentation::text( $value ) );
	}

	private static function overridden( array $record ): bool {
		$overrides = $record['overrides'] ?? array();
		return array_key_exists( self::LINKS, $overrides ) || array_key_exists( self::NAME, $overrides );
	}

	private static function selection( array $data ): array {
		$selected = FieldPicker::selection( $data[ self::LINKS ] ?? array(), 'msc_streaming' );
		$name = Presentation::text( $data[ self::NAME ] ?? '' );
		$labels = array_map( array( self::class, 'name' ), array_column( $selected, 'label' ) );
		if ( '' !== trim( $name ) && ! in_array( self::name( $name ), $labels, true ) ) {
			$selected[] = array( 'id' => self::IMPORTED, 'label' => $name );
		}
		return $selected;
	}

	public static function control( array $field, array $record, bool $global ): void {
		$selected = self::selection( ( $global ? $record['global'] : $record['data'] ) ?? array() );
		echo '<div class="msc-streaming-editor">';
		if ( ! $global ) {
			$inherited = array_column( self::selection( $record['global'] ), 'label' );
			echo '<details><summary>Global value</summary><p>' . esc_html( $inherited ? implode( ', ', $inherited ) : 'None' ) . '</p></details>';
			echo '<label><input type="checkbox" name="override[streaming_services]" value="1" ' . checked( self::overridden( $record ), true, false ) . '> Override on this site</label>';
		}
		echo '<input type="hidden" name="streaming_services_original" value="' . esc_attr( wp_json_encode( array_column( $selected, 'id' ) ) ) . '">';
		FieldPicker::control( 'streaming_services', 'msc-streaming-services', $field, array(), $selected );
		echo '</div>';
	}

	/** Preserve untouched data, including records that only override one of the stored fields. */
	public static function changes( array $request, array $record, bool $global, array &$values, array &$reset ): void {
		if ( ! $global && empty( $request['override']['streaming_services'] ) ) {
			array_push( $reset, self::LINKS, self::NAME );
			return;
		}
		$submitted = $request['streaming_services'];
		$original = $request['streaming_services_original'] ?? null;
		$changed = $submitted !== $original;
		if ( ! $changed && ( $global || self::overridden( $record ) ) ) { return; }

		$data = $record['data'] ?? array();
		if ( ! $changed ) {
			// A new local override starts with the latest effective values.
			$values[ self::LINKS ] = $data[ self::LINKS ] ?? array();
			$values[ self::NAME ] = $data[ self::NAME ] ?? '';
			return;
		}
		$selection = is_string( $submitted ) ? json_decode( $submitted, true, 32, JSON_THROW_ON_ERROR ) : null;
		if ( ! is_array( $selection ) || ! array_is_list( $selection ) ) {
			throw new \InvalidArgumentException( 'Choose streaming services from the search results.' );
		}
		$links = array_values( array_filter( $selection, static fn( $id ) => self::IMPORTED !== $id ) );
		foreach ( $links as $id ) {
			if ( ! is_string( $id ) || ! wp_is_uuid( $id ) ) { throw new \InvalidArgumentException( 'Invalid streaming service selection.' ); }
		}
		$name = in_array( self::IMPORTED, $selection, true ) ? ( $data[ self::NAME ] ?? '' ) : '';
		if ( '' !== $name ) {
			$selected = self::selection( array( self::LINKS => $links, self::NAME => $name ) );
			if ( ! in_array( self::IMPORTED, array_column( $selected, 'id' ), true ) ) { $name = ''; }
		}
		$values[ self::LINKS ] = $links;
		$values[ self::NAME ] = $name;
	}
}
