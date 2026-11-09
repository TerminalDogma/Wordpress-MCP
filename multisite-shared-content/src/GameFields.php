<?php
namespace MSC;

/** Structured release dates without requiring ACF Pro's repeater field. */
final class GameFields {
	public static function sanitize( $rows ): array {
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) || count( $rows ) > 1000 ) {
			throw new \InvalidArgumentException( 'Expected at most 1000 platform release dates.' );
		}
		$out = array(); foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				throw new \InvalidArgumentException( 'Invalid game release date row.' );
			}
			$clean = array();
			foreach ( array( 'platform', 'region', 'display', 'date', 'precision' ) as $key ) {
				$value = $row[ $key ] ?? ( 'precision' === $key ? 'day' : '' );
				if ( ! is_string( $value ) || strlen( $value ) > 200 ) {
					throw new \InvalidArgumentException( 'Invalid release date field.' );
				}
				$clean[ $key ] = sanitize_text_field( $value );
			}
			if ( ! in_array( $clean['precision'], array( 'day', 'month', 'year', 'quarter', 'tbd' ), true ) || $clean['date'] && ! ReleaseCalendar::date( $clean['date'] ) || 'day' === $clean['precision'] && ! $clean['date'] || 'day' !== $clean['precision'] && $clean['date'] ) {
				throw new \InvalidArgumentException( 'Use an exact YYYY-MM-DD for day precision; leave the date empty for a release window or TBD.' );
			}
			foreach ( array( 'platform_id', 'source_id' ) as $key ) {
				$id = $row[ $key ] ?? 0;
				if ( ! is_numeric( $id ) || (float) (int) $id !== (float) $id || (int) $id < 0 ) {
					throw new \InvalidArgumentException( 'Invalid game platform or source ID.' );
				}
				$clean[ $key ] = (int) $id;
			}
			$out[] = $clean;
		}
		return $out;
	}
	public static function control( array $rows ): void {
		echo '<div class="msc-game-dates"><input type="hidden" id="msc-release_dates" name="fields[release_dates]" class="msc-game-dates-value" value="' . esc_attr( wp_json_encode( $rows ) ) . '"><div class="msc-game-date-rows"></div><button type="button" class="button msc-game-date-add">Add platform release date</button><p class="description">Choose an exact day for the calendar. For a month, year, quarter or TBD, leave the day blank and enter the release window. IGDB platform IDs are optional when the platform name is used.</p></div>';
	}
	public static function boot(): void {
		add_action(
			'admin_enqueue_scripts',
			static function () {
				if ( 'msc' === ( $_GET['page'] ?? '' ) ) {
					wp_enqueue_script( 'msc-game-dates', plugins_url( 'assets/game-dates.js', MSC_FILE ), array(), MSC_VERSION, true );
				}
			}
		);
	}
}
