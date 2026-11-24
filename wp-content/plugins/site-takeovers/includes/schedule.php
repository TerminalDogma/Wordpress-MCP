<?php
namespace SiteTakeovers;

final class Schedule {
    public static function from_input( array $input ): array {
        $start = self::parse( $input['start'] ?? '' );
        $end = self::parse( $input['end'] ?? '' );
        if ( null !== $start && null !== $end && $start >= $end ) {
            throw new \InvalidArgumentException( 'The end must be later than the start.' );
        }
        return array( 'start' => $start, 'end' => $end );
    }

    private static function parse( $value ): ?int {
        if ( '' === $value ) { return null; }
        $format = 'Y-m-d\TH:i';
        $valid = is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value );
        $date = $valid ? \DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() ) : false;
        if ( ! $date || $date->format( $format ) !== $value ) {
            throw new \InvalidArgumentException( 'Enter a valid date and time in the site timezone.' );
        }
        return $date->getTimestamp();
    }

    public static function format( ?int $timestamp, string $format = 'Y-m-d\TH:i' ): string {
        return null === $timestamp ? '' : wp_date( $format, $timestamp );
    }

    public static function state( array $window, int $now ): string {
        if ( isset( $window['end'] ) && $now >= $window['end'] ) { return 'Ended'; }
        if ( isset( $window['start'] ) && $now < $window['start'] ) { return 'Scheduled'; }
        return 'Running';
    }
}
