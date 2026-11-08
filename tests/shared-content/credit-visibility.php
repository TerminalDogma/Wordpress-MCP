<?php
use MSC\{EntityCatalog,Plugin,Presentation,Schema};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local network required.' ); }
wp_set_current_user( 1 ); $sites = array(); foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( in_array( $site->path, array( '/site-a/', '/site-b/' ), true ) ) { $sites[] = (int) $site->blog_id; } } [ $a, $b ] = $sites;
function cv_at( int $site, callable $fn ) { switch_to_blog( $site ); try { return $fn(); } finally { restore_current_blog(); } }
function cv_check( $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['cv_checks']; WP_CLI::log( 'PASS: ' . $message ); }
function cv_status( array $r, string $status ): void { $s = Plugin::store(); $m = $s->mapping( (int) $r['id'] ); $s->local( $r['uuid'], array(), array(), (int) $m['local_version'], $status ); }
$GLOBALS['cv_checks'] = 0; $old_a = get_blog_option( $a, 'msc_types', array() ); $old_b = get_blog_option( $b, 'msc_types', array() );
try {
    Schema::participate( $a, array_values( array_unique( array_merge( $old_a, array( 'msc_movie', 'msc_person', 'msc_studio' ) ) ) ) );
    Schema::participate( $b, array_values( array_unique( array_merge( array_diff( $old_b, array( 'msc_person' ) ), array( 'msc_movie', 'msc_studio' ) ) ) ) );
    cv_at( $a, static function () use ( $a, $b ) {
        $s = Plugin::store(); $tag = substr( wp_generate_uuid4(), 0, 8 );
        $person = $s->create( 'msc_person', array( 'title' => 'Draft Director ' . $tag, 'content' => 'PRIVATE_BIO_' . $tag ) );
        $company = $s->create( 'msc_studio', array( 'title' => 'Draft Company ' . $tag, 'content' => 'PRIVATE_COMPANY_' . $tag ) );
        $movie = $s->create( 'msc_movie', array( 'title' => 'Credit visibility ' . $tag, 'actors' => array( $person['uuid'] ), 'directors' => array( $person['uuid'] ), 'production_company_records' => array( $company['uuid'] ), 'production_companies' => $company['data']['title'] ) );
        foreach ( array( $person, $company, $movie ) as $r ) { Plugin::materializer()->sync( (int) $r['id'], $a ); } cv_status( $movie, 'publish' ); $public_movie = Presentation::record( $movie['uuid'] );
        $sidebar = EntityCatalog::sidebar( $public_movie ); cv_check( str_contains( $sidebar, $person['data']['title'] ) && str_contains( $sidebar, $company['data']['title'] ), 'Draft People and companies appear as credit names in the details sidebar' );
        cv_check( ! str_contains( $sidebar, '<a ' ) && 1 === substr_count( $sidebar, $company['data']['title'] ), 'Unpublished names have no links and duplicate legacy/company references are merged' );
        ob_start(); Presentation::credits( $public_movie ); $html = ob_get_clean(); cv_check( 2 === substr_count( $html, $person['data']['title'] ) && ! str_contains( $html, '<a ' ), 'Cast and director sections display the same draft Person in each credited role' );
        cv_check( ! str_contains( $html . $sidebar, 'PRIVATE_BIO_' ) && ! str_contains( $html . $sidebar, 'PRIVATE_COMPANY_' ), 'Only credited names are exposed from unpublished profiles' );
        cv_check( null === Presentation::record( $person['uuid'] ) && null === Presentation::record( $company['uuid'] ), 'Draft profile pages remain unpublished' );
        cv_status( $person, 'publish' ); cv_status( $company, 'publish' ); $linked = EntityCatalog::sidebar( $public_movie );
        cv_check( str_contains( $linked, esc_url( Presentation::url( Presentation::record( $person['uuid'] ) ) ) ) && str_contains( $linked, esc_url( Presentation::url( Presentation::record( $company['uuid'] ) ) ) ), 'Publishing profiles automatically turns existing credit names into links' );
        ob_start(); Presentation::credits( $public_movie ); $html = ob_get_clean(); cv_check( 2 === substr_count( $html, '<a class="person-card"' ), 'Published People automatically gain profile links in cast and crew sections' );
        cv_status( $person, 'draft' ); cv_status( $company, 'draft' ); cv_check( ! str_contains( EntityCatalog::sidebar( $public_movie ), '<a ' ) && str_contains( EntityCatalog::sidebar( $public_movie ), $company['data']['title'] ), 'Unpublishing removes links while retaining the names' );
        Plugin::materializer()->sync( (int) $movie['id'], $b );
        cv_at( $b, static function () use ( $person, $movie ) { $html = EntityCatalog::sidebar( Plugin::store()->resolved( $movie['uuid'] ) ); cv_check( str_contains( $html, $person['data']['title'] ) && ! str_contains( $html, '<a ' ), 'A site without Person pages still displays inherited credit names' ); } );
        $r = $s->resolved( $movie['uuid'] ); $s->local( $movie['uuid'], array( 'actors' => array() ), array(), (int) $r['mapping']['local_version'] ); $rows = EntityCatalog::rows( $s->resolved( $movie['uuid'] ) ); cv_check( ! isset( $rows['actors'] ) && isset( $rows['directors'] ), 'Explicit empty local credit overrides remain respected' );
        cv_check( $movie['data'] === $s->get( $movie['uuid'] )['data'], 'Publication changes do not require rewriting the title record or its relationships' );
    } );
} finally { Schema::participate( $a, $old_a ); Schema::participate( $b, $old_b ); }
WP_CLI::success( $GLOBALS['cv_checks'] . ' draft credit-name and automatic profile-link checks passed.' );
