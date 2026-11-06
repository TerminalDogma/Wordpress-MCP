<?php
namespace MSC;

/** Site-level catalog rules are enforced on projection and public reads. */
final class GamePolicy {
    public const SONY_HOME = array( 7 => 'PlayStation', 8 => 'PlayStation 2', 9 => 'PlayStation 3', 48 => 'PlayStation 4', 167 => 'PlayStation 5', 165 => 'PlayStation VR', 390 => 'PlayStation VR2' );
    public const SONY_PORTABLE = array( 38 => 'PlayStation Portable', 46 => 'PlayStation Vita' );
    public static function mode( ?int $site = null ): string { return (string) get_blog_option( $site ?? get_current_blog_id(), 'msc_game_platform_policy', 'all' ); }
    public static function sony( ?int $site = null ): array { return self::SONY_HOME + ( 'sony_home' === self::mode( $site ) ? array() : self::SONY_PORTABLE ); }
    public static function platform_allowed( $id, string $label = '', ?int $site = null ): bool {
        if ( 'all' === self::mode( $site ) ) { return true; } $sony = self::sony( $site );
        if ( is_numeric( $id ) && isset( $sony[ (int) $id ] ) ) { return true; }
        $labels = array_map( 'strtolower', array_values( $sony ) ); if ( isset( $sony[38] ) ) { $labels[] = 'psp'; } if ( isset( $sony[46] ) ) { $labels[] = 'ps vita'; }
        return in_array( strtolower( trim( $label ) ), $labels, true );
    }
    public static function accepts( array $record, ?int $site = null ): bool {
        if ( 'msc_game' !== $record['type'] || 'all' === self::mode( $site ) ) { return true; }
        foreach ( (array) ( $record['data']['platform_ids'] ?? array() ) as $id ) { if ( self::platform_allowed( $id, '', $site ) ) { return true; } }
        foreach ( (array) ( $record['data']['platforms'] ?? array() ) as $name ) { if ( is_string( $name ) && self::platform_allowed( 0, $name, $site ) ) { return true; } }
        foreach ( (array) ( $record['data']['release_dates'] ?? array() ) as $row ) { if ( is_array( $row ) && self::platform_allowed( $row['platform_id'] ?? 0, $row['platform'] ?? '', $site ) ) { return true; } } return false;
    }
    public static function project( array $record, ?int $site = null ): array {
        if ( 'msc_game' !== $record['type'] || 'all' === self::mode( $site ) ) { return $record; } $d = &$record['data'];
        $d['platform_ids'] = array_values( array_filter( (array) ( $d['platform_ids'] ?? array() ), static fn( $id ) => self::platform_allowed( $id, '', $site ) ) );
        $d['platforms'] = array_values( array_filter( (array) ( $d['platforms'] ?? array() ), static fn( $label ) => is_string( $label ) && self::platform_allowed( 0, $label, $site ) ) );
        $d['release_dates'] = array_values( array_filter( (array) ( $d['release_dates'] ?? array() ), static fn( $r ) => is_array( $r ) && self::platform_allowed( $r['platform_id'] ?? 0, $r['platform'] ?? '', $site ) ) );
        $dates = array(); foreach ( $d['release_dates'] as $r ) { if ( 'day' === ( $r['precision'] ?? 'day' ) && ReleaseCalendar::date( $r['date'] ?? '' ) ) { $dates[] = $r['date']; } } sort( $dates );
        if ( ! array_key_exists( 'release_date', $record['overrides'] ?? array() ) && $dates ) { $d['release_date'] = $dates[0]; }
        elseif ( ! array_key_exists( 'release_date', $record['overrides'] ?? array() ) && ! $dates && ! empty( $record['global']['release_dates'] ?? $record['data']['release_dates'] ) ) { $d['release_date'] = ''; }
        return $record;
    }
    public static function boot(): void {
        add_action( 'template_redirect', static function () {
            if ( ! is_singular() ) { return; } $type = get_post_type(); if ( ! isset( Schema::all()[ $type ] ) ) { return; }
            $r = Plugin::store()->by_post( get_queried_object_id() );
            if ( ! Schema::enabled( $type ) || $r && ! self::accepts( Plugin::store()->resolved( $r['uuid'] ) ) ) { global $wp_query; $wp_query->set_404(); status_header( 404 ); nocache_headers(); }
        }, 0 );
        add_action( 'pre_get_posts', static function ( $q ) {
            if ( ! $q->is_main_query() ) { return; } $type = $q->get( 'post_type' );
            if ( is_admin() && 'msc_game' === $type && 'all' !== self::mode() ) { $filters = array( 'relation' => 'OR', array( 'key' => '_msc_game_eligible', 'value' => '1' ), array( 'key' => '_msc_game_eligible', 'compare' => 'NOT EXISTS' ) ); $old = $q->get( 'meta_query' ); $q->set( 'meta_query', $old ? array( 'relation' => 'AND', $old, $filters ) : $filters ); }
            if ( ! is_admin() && is_string( $type ) && isset( Schema::all()[ $type ] ) && ! Schema::enabled( $type ) ) { $q->set( 'post__in', array( 0 ) ); }
            if ( ! is_admin() && ( 'any' === $type || $q->is_search() && ! $type ) ) { $q->set( 'post_type', array_merge( array( 'post', 'page' ), array_values( array_filter( array_keys( Schema::all() ), static fn( $t ) => Schema::enabled( $t ) ) ) ) ); }
        }, 30 );
    }
}
