<?php
use SiteTakeovers\Schedule;

$check = static function ( bool $pass, string $message ): void {
    if ( ! $pass ) { throw new RuntimeException( $message ); }
    WP_CLI::log( 'PASS: ' . $message );
};
$timezone = static fn(): string => 'America/New_York';
add_filter( 'pre_option_timezone_string', $timezone );
try {
    $window = Schedule::from_input( array( 'start' => '2027-02-12T00:00', 'end' => '2027-02-22T00:00' ) );
    $check( strtotime( '2027-02-12 05:00:00 UTC' ) === $window['start'] && '2027-02-12T00:00' === Schedule::format( $window['start'] ), 'Editor times convert from the site timezone and round-trip.' );
    $check( 'Scheduled' === Schedule::state( $window, $window['start'] - 1 ) && 'Running' === Schedule::state( $window, $window['start'] ), 'An ad starts at the exact scheduled time.' );
    $check( 'Running' === Schedule::state( $window, $window['end'] - 1 ) && 'Ended' === Schedule::state( $window, $window['end'] ), 'An ad stops at the exact scheduled end.' );
    $check( 'Running' === Schedule::state( array(), time() ) && 'Running' === Schedule::state( Schedule::from_input( array() ), time() ), 'Existing and new undated ads can run immediately.' );
    $check( 'Running' === Schedule::state( array( 'start' => $window['start'] ), $window['end'] ) && 'Running' === Schedule::state( array( 'end' => $window['end'] ), $window['start'] - 1 ), 'Either end of the publishing period can be left open.' );
    $invalid = array(
        array( 'start' => '2027-02-30T00:00' ),
        array( 'start' => '2027-03-14T02:30' ),
        array( 'start' => "2027-02-12T00:00\0" ),
        array( 'start' => '2027-02-22T00:00', 'end' => '2027-02-12T00:00' ),
        array( 'start' => '2027-02-22T00:00', 'end' => '2027-02-22T00:00' ),
    );
    foreach ( $invalid as $input ) {
        try {
            Schedule::from_input( $input );
            throw new RuntimeException( 'An invalid window was accepted.' );
        } catch ( InvalidArgumentException $error ) {
            $check( true, 'Invalid dates, clock-change times and reversed or empty periods are rejected.' );
        }
    }
} finally {
    remove_filter( 'pre_option_timezone_string', $timezone );
}
