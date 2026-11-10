<?php
use MSC\{Comics, ComicAdmin, ComicSource, ComicImport, Schema, Plugin, Queue, OpenSources, OpenImport, Presentation, ReleaseCalendar, Acf, Database};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() || '1' !== getenv( 'MSC_DISPOSABLE_TESTS' ) ) { throw new RuntimeException( 'Explicit disposable local test network required.' ); }
wp_set_current_user( 1 ); $GLOBALS['comic_checks'] = 0; $store = Plugin::store();
function comic_check( $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['comic_checks']; WP_CLI::log( 'PASS: ' . $message ); }
function comic_reject( callable $fn, string $message ): void { try { $fn(); } catch ( Throwable $e ) { comic_check( true, $message ); return; } throw new RuntimeException( 'FAIL: ' . $message ); }
function comic_drain(): void { for ( $i = 0; $i < 200 && Queue::status()['pending']; ++$i ) { $r = Queue::run( 1000 ); if ( $r['errors'] || ! $r['processed'] ) { throw new RuntimeException( 'Queue failed: ' . wp_json_encode( $r ) ); } } }
function comic_publish( array $r, string $status = 'publish' ): array { comic_drain(); $s = Plugin::store(); $current = $s->resolved( $r['uuid'] ); return $s->local( $r['uuid'], array(), array(), (int) $current['mapping']['local_version'], $status ); }
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
    $types = get_blog_option( $site->blog_id, 'msc_types', array() );
    if ( array_intersect( $types, Comics::TYPES ) ) { Schema::participate( (int) $site->blog_id, array_values( array_diff( $types, Comics::TYPES ) ) ); }
    delete_blog_option( (int) $site->blog_id, 'msc_comics_profile_version' );
}
Comics::enroll(); $sites = array(); foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { $key = MSC\NetworkSites::key( $site->path ); if ( $key ) { $sites[ $key ] = (int) $site->blog_id; } }
foreach ( $sites as $key => $site ) { foreach ( Comics::TYPES as $type ) { comic_check( Schema::enabled( $type, $site ) === ( 'b' === $key ), $type . ' is initially enabled only on SuperHeroHype' ); } }
switch_to_blog( $sites['b'] ); Plugin::register_posts(); Acf::register();
 $participation = get_option( 'msc_types' ); Schema::participate( $sites['b'], array_values( array_diff( $participation, array( 'msc_comic_arc' ) ) ) ); Comics::enroll();
 comic_check( ! Schema::enabled( 'msc_comic_arc' ), 'The one-time enrollment preserves a subsequent disabled type' ); Schema::participate( $sites['b'], $participation );
comic_check( post_type_exists( 'msc_comic' ) && post_type_exists( 'msc_comic_issue' ) && post_type_exists( 'msc_comic_arc' ), 'All three native comic types register on Site B' );
comic_reject( static fn() => Schema::values( 'msc_comic_issue', array( 'arc_position' => 0 ) ), 'Arc positions reject zero' );
comic_check( '1/2' === Schema::values( 'msc_comic_issue', array( 'issue_number' => '1/2' ) )['issue_number'], 'Published issue identifiers support fractional numbering' );
// Exercise the additive 2.4.0 upgrade rather than only a fresh schema install.
$old_schema = get_network_option( get_current_network_id(), 'msc_schemas' ); unset( $old_schema['msc_comic']['fields']['story_arcs'] );
update_network_option( get_current_network_id(), 'msc_schemas', $old_schema ); update_network_option( get_current_network_id(), 'msc_builtin_schema_version', 10 ); Schema::upgrade_defaults(); Acf::register();
comic_check( 'relationship_list' === Schema::get( 'msc_comic' )['fields']['story_arcs']['type'], 'Upgrading 2.4.0 adds book-level arc assignments without replacing saved schemas' );
$base = (string) random_int( 8800000, 8890000 ); $issue_id = (string) ( (int) $base + 1 ); $undated_id = (string) ( (int) $base + 2 ); $publisher_id = (string) ( (int) $base + 3 ); $query = 'Signal Comic ' . $base;
$url = static fn( $kind, $id ) => 'https://www.comics.org/api/' . $kind . '/' . $id . '/?format=json';
$series_data = array( 'api_url' => $url( 'series', $base ), 'name' => $query, 'country' => 'us', 'language' => 'en', 'year_began' => 2026, 'year_ended' => null, 'publisher' => $url( 'publisher', $publisher_id ), 'publishing_format' => 'ongoing series', 'active_issues' => array( $url( 'issue', $issue_id ), $url( 'issue', $undated_id ) ), 'issue_descriptors' => array( '10', '11' ), 'notes' => 'Fictional comic series for local automated tests.' );
$issue_data = array( 'api_url' => $url( 'issue', $issue_id ), 'series' => $url( 'series', $base ), 'series_name' => $query . ' (2026 series)', 'number' => '10', 'title' => '', 'volume' => '1', 'variant_name' => '', 'publication_date' => 'April 2099', 'key_date' => '2099-04-00', 'on_sale_date' => '2099-03-07', 'page_count' => '32.000', 'price' => '4.99 USD', 'notes' => '', 'cover' => 'https://files1.comics.org/img/gcd/covers_by_id/47/w400/47252.jpg', 'story_set' => array( array( 'type' => 'comic story', 'script' => 'Maya Chen (credited)', 'pencils' => 'Ari Bell (credited)', 'inks' => 'None', 'synopsis' => 'A fictional issue used to verify series and arc links.' ), array( 'type' => 'cover', 'pencils' => 'Devon Cole' ) ) );
$calls = 0;
add_filter( 'pre_http_request', static function ( $pre, $args, $request ) use ( &$calls, $query, $series_data, $issue_data, $base, $issue_id, $undated_id, $publisher_id, $url ) {
    if ( ComicSource::cover_url( $request ) ) {
        $body = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
        if ( ! empty( $args['stream'] ) ) { file_put_contents( $args['filename'], $body ); $body = ''; }
        return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'image/png' ), 'body' => $body );
    }
    if ( 'www.comics.org' !== wp_parse_url( $request, PHP_URL_HOST ) ) { return $pre; }
    ++$calls; $path = wp_parse_url( $request, PHP_URL_PATH );
    if ( '/api/series/name/' . rawurlencode( $query ) . '/' === $path ) { $data = array( 'count' => 1, 'next' => null, 'results' => array( $series_data ) ); }
    elseif ( '/api/series/' . $base . '/' === $path ) { $data = $series_data; }
    elseif ( '/api/issue/' . $issue_id . '/' === $path ) { $data = $issue_data; }
    elseif ( '/api/issue/' . $undated_id . '/' === $path ) { $data = array_replace( $issue_data, array( 'api_url' => $url( 'issue', $undated_id ), 'number' => '11', 'on_sale_date' => '' ) ); }
    elseif ( '/api/publisher/' . $publisher_id . '/' === $path ) { $data = array( 'api_url' => $url( 'publisher', $publisher_id ), 'name' => 'Signal Comics' ); }
    else { return new WP_Error( 'unexpected_fixture', 'Unexpected GCD test request.' ); }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( $data ) );
}, 10, 3 );
delete_site_transient( 'msc_open_rate_gcd' ); delete_site_transient( 'msc_open_backoff_gcd' );
$results = OpenSources::search( 'msc_comic', $query ); comic_check( $base === $results['items'][0]['id'], 'GCD title search uses the name-in-path API and returns typed series identities' );
comic_reject( static fn() => ComicSource::id( 'https://attacker.invalid/api/issue/1/', 'issue' ), 'Source links cannot change the permitted provider host' );
$preview = OpenImport::preview( 'gcd', $base, 'msc_comic' ); comic_check( $preview['image']['url'] === $issue_data['cover'], 'Series previews offer an actual issue cover from the start of the run' ); $saved = OpenImport::import( array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $preview['token'], 'fields' => array_keys( $preview['fields'] ), 'image' => '1' ) ); $book = $saved['record']; comic_drain();
comic_check( 'draft' === get_post_status( $store->mapping( $book['id'] )['post_id'] ), 'New imported Comic Books use the draft publication default' );
$second = OpenImport::preview( 'gcd', $base, 'msc_comic' ); comic_check( $second['target'] === $book['uuid'], 'Repeated imports reuse the series identity' );
$preview = OpenImport::preview( 'gcd', $issue_id, 'msc_comic_issue' ); comic_check( $preview['fields']['parent_comic'] === $book['uuid'], 'Issue previews resolve the correct imported parent series' );
comic_check( $preview['fields']['release_date'] === '2099-03-07' && $preview['fields']['cover_date'] === 'April 2099', 'On-sale and cover dates remain distinct' );
comic_check( $preview['fields']['writers'] === array( 'Maya Chen' ) && in_array( 'Ari Bell', $preview['fields']['artists'], true ), 'Source credits are retained as editable categories' );
$saved = OpenImport::import( array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $preview['token'], 'fields' => array_keys( $preview['fields'] ), 'image' => '1' ) ); $issue = $saved['record'];
comic_check( ! empty( $issue['data']['poster']['attachment_id'] ) && wp_attachment_is_image( $issue['data']['poster']['attachment_id'] ), 'GCD covers are downloaded into the media library and assigned to the issue' );
comic_check( str_contains( MSC\Attribution::image_html( $issue ), 'Grand Comics Database' ) && str_contains( MSC\Attribution::image_html( $issue ), 'copyright holders' ), 'Comic poster attribution uses artwork credits rather than the metadata licence' );
comic_check( ! ComicSource::cover_url( 'https://files1.comics.org.attacker.invalid/img/gcd/covers_by_id/47/w400/47252.jpg' ) && ! ComicSource::cover_url( 'https://files1.comics.org/other.jpg' ) && ! ComicSource::cover_url( 'https://files1.comics.org:443/img/gcd/covers_by_id/47/w400/47252.jpg' ), 'Cover URLs reject foreign hosts, arbitrary paths and custom ports' );
$original_cover = $issue['data']['poster'];
$bad_cover = static function ( $response, $args, $request ) { if ( ComicSource::cover_url( $request ) ) { if ( ! empty( $args['stream'] ) ) { file_put_contents( $args['filename'], 'not an image' ); } return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '' ); } return $response; };
add_filter( 'pre_http_request', $bad_cover, 20, 3 );
$retry = OpenImport::preview( 'gcd', $issue_id, 'msc_comic_issue' ); $retry = OpenImport::import( array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $retry['token'], 'fields' => array( 'title' ), 'image' => '1' ) );
remove_filter( 'pre_http_request', $bad_cover, 20 ); $issue = $retry['record'];
comic_check( $issue['data']['poster'] === $original_cover && ! empty( $retry['warnings'] ), 'A corrupt or failed cover download preserves the current image and still saves selected text' );
$again = OpenImport::preview( 'gcd', $issue_id, 'msc_comic_issue' ); comic_check( $again['target'] === $issue['uuid'], 'Repeated issue previews reuse the issue instead of creating duplicates' );
$before = $calls; ComicSource::details( $issue_id, 'msc_comic_issue' ); comic_check( $calls === $before, 'Provider responses are cached for repeated previews' );
$undated = ComicSource::details( $undated_id, 'msc_comic_issue' ); comic_check( ! isset( $undated['fields']['release_date'] ), 'A month-only cover date never fabricates a calendar day' );
$other = $store->create( 'msc_comic', array( 'title' => 'Second Comic ' . $base ) );
comic_reject( static fn() => OpenImport::preview( 'gcd', $issue_id, 'msc_comic_issue', '', $other['uuid'] ), 'An issue cannot import under a mismatched parent' );
$arc = $store->create( 'msc_comic_arc', array( 'title' => 'The Signal Arc ' . $base, 'expected_issues' => 3, 'content' => '<p>Fictional crossover arc.</p>' ) );
$book = $store->update( $book['id'], array( 'story_arcs' => array( $arc['uuid'] ) ), (int) $book['version'] );
comic_check( $store->resolved( $book['uuid'] )['data']['story_arcs'] === array( $arc['uuid'] ), 'Story arcs can be assigned directly to a Comic Book independently of individual issues' );
$issue = $store->update( $issue['id'], array( 'story_arc' => $arc['uuid'], 'arc_position' => 3 ), (int) $issue['version'] );
$early = $store->create( 'msc_comic_issue', array( 'title' => 'Signal #2', 'parent_comic' => $book['uuid'], 'issue_number' => '#2', 'story_arc' => $arc['uuid'], 'arc_position' => 1 ) );
$cross = $store->create( 'msc_comic_issue', array( 'title' => 'Second Comic #1', 'parent_comic' => $other['uuid'], 'issue_number' => '1', 'story_arc' => $arc['uuid'], 'arc_position' => 2 ) );
$draft = $store->create( 'msc_comic_issue', array( 'title' => 'Hidden draft issue', 'parent_comic' => $book['uuid'], 'issue_number' => '3' ) );
$private = $store->create( 'msc_comic_issue', array( 'title' => 'Hidden private issue', 'parent_comic' => $book['uuid'], 'issue_number' => '4' ) ); comic_publish( $private, 'private' );
$protected = $store->create( 'msc_comic_issue', array( 'title' => 'Hidden protected issue', 'parent_comic' => $book['uuid'], 'issue_number' => '5' ) ); comic_publish( $protected );
$writing = MSC\Materializer::$writing; MSC\Materializer::$writing = true; wp_update_post( array( 'ID' => $store->mapping( $protected['id'] )['post_id'], 'post_password' => 'fixture-only' ) ); MSC\Materializer::$writing = $writing;
Plugin::materializer()->sync( $protected['id'], get_current_blog_id() ); comic_check( 'fixture-only' === get_post_field( 'post_password', $store->mapping( $protected['id'] )['post_id'], 'raw' ), 'Synchronization preserves local password protection' );
foreach ( array( $book, $other, $arc, $issue, $early, $cross ) as $r ) { comic_publish( $r ); }
$book = Presentation::record( $book['uuid'] ); $arc = Presentation::record( $arc['uuid'] ); $issue = Presentation::record( $issue['uuid'] );
comic_check( array_column( Comics::issue_page( $book )['issues'], 'uuid' ) === array( $early['uuid'], $issue['uuid'] ), 'Series lists sort issue 2 before issue 10 and hide draft issues' );
comic_check( 5 === Comics::issue_page( $book, array(), true )['total'], 'Admin groups include drafts, private and protected issues while public groups exclude them' );
comic_check( array_column( Comics::issue_page( $arc )['issues'], 'uuid' ) === array( $early['uuid'], $cross['uuid'], $issue['uuid'] ), 'An arc orders issues across multiple Comic Books by arc position' );
comic_check( str_contains( Comics::arc_label( $issue ), 'Part 3 of 3' ), 'Issue pages distinguish arc position from published issue number' );
comic_check( 2 === Comics::issue_page( $book, array( 'comic_arc' => $arc['uuid'] ) )['total'], 'A series can filter its own issues by arc' );
$author = wp_insert_user( array( 'user_login' => 'comic-author-' . $base, 'user_pass' => wp_generate_password( 32 ), 'role' => 'author' ) );
if ( is_wp_error( $author ) ) { throw new RuntimeException( $author->get_error_message() ); } wp_set_current_user( $author );
comic_check( current_user_can( 'edit_posts' ) && ! Comics::admin_allowed() && 0 === Comics::issue_page( $book, array(), true )['total'], 'Native authors without shared-content permission cannot enumerate private comic issues' );
$die_handler = static fn() => static function ( $message ) { throw new RuntimeException( wp_strip_all_tags( $message ) ); };
add_filter( 'wp_die_handler', $die_handler ); ob_start(); comic_reject( static fn() => ComicAdmin::page(), 'The grouped admin page rejects users without shared-content capabilities' ); ob_end_clean(); remove_filter( 'wp_die_handler', $die_handler ); wp_set_current_user( 1 );
$local = $store->resolved( $issue['uuid'] ); $local = $store->local( $issue['id'], array( 'parent_comic' => $other['uuid'] ), array(), (int) $local['mapping']['local_version'] );
ob_start(); ComicAdmin::context( $store->resolved( $issue['uuid'] ), true ); $global_context = ob_get_clean();
ob_start(); ComicAdmin::context( $store->resolved( $issue['uuid'] ), false ); $local_context = ob_get_clean();
comic_check( str_contains( $global_context, esc_html( $book['data']['title'] ) ) && str_contains( $local_context, esc_html( $other['data']['title'] ) ), 'Global and local editors show the parent for the selected editing scope' );
comic_check( 1 === Comics::issue_page( $book )['total'] && 2 === Comics::issue_page( Presentation::record( $other['uuid'] ) )['total'], 'Grouping follows effective local parent overrides' );
$store->local( $issue['id'], array(), array( 'parent_comic' ), (int) $local['mapping']['local_version'] );
$events = ReleaseCalendar::data( '2099-03', 'msc_comic_issue' )['events']; $event = array_values( array_filter( $events, static fn( $event ) => $event['uuid'] === $issue['uuid'] ) )[0];
comic_check( $event['show_title'] === $book['data']['title'], 'Comic calendar cards carry their published parent series name' );
$editor = Acf::key( 'msc_comic_issue', 'parent_comic' ); comic_check( 'post_object' === acf_get_field( $editor )['type'], 'ACF exposes parent Comic Book as a searchable record relationship' );
ob_start(); get_template_part( 'parts/detail', 'comic', array( 'record' => $issue ) ); $html = ob_get_clean(); comic_check( str_contains( $html, 'Part 3 of 3' ) && str_contains( $html, 'Issue #10' ), 'The issue template renders both numbering systems' );
ob_start(); get_template_part( 'parts/detail', 'comic', array( 'record' => $book ) ); $html = ob_get_clean(); comic_check( str_contains( $html, 'aria-label="Assigned story arcs"' ) && str_contains( $html, esc_html( $arc['data']['title'] ) ), 'Comic Book pages display assigned published story arcs' ); comic_check( str_contains( $html, 'data-comic-browser' ) && ! str_contains( $html, 'Hidden draft issue' ), 'The series template provides a public issue browser without leaking drafts' );
$empty_arc = $store->create( 'msc_comic_arc', array( 'title' => 'Unreleased Arc ' . $base ) ); comic_publish( $empty_arc );
$book_current = $store->get( $book['uuid'] ); $book_current = $store->update( $book_current['id'], array( 'story_arcs' => array( $arc['uuid'], $empty_arc['uuid'] ) ), (int) $book_current['version'] ); comic_drain();
$book = Presentation::record( $book['uuid'] ); $_GET['comic_arc'] = $empty_arc['uuid'];
ob_start(); get_template_part( 'parts/detail', 'comic', array( 'record' => $book ) ); $html = ob_get_clean(); unset( $_GET['comic_arc'] );
comic_check( str_contains( $html, 'data-comic-browser' ) && str_contains( $html, 'No published issues are assigned' ), 'Selecting an assigned arc with no published issues retains the AJAX browser and its reset controls' );
$token = ComicImport::start( $book['uuid'], array( $undated_id ), false, true ); $job = OpenImport::batch_step( $token ); comic_check( 1 === $job['index'] && $job['results'][0]['status'] === 'Imported', 'The resumable batch importer handles Comic Issues through the existing progress endpoint' );
$existing = OpenImport::matches( 'msc_comic_issue', array( 'gcd' => $undated_id ) ); comic_check( ! empty( $existing[0]['data']['poster']['attachment_id'] ), 'Batch imports can download available covers for each selected issue' );
comic_check( 1 === count( $existing ), 'Batch imports retain source identity for duplicate checks' );
comic_reject( static fn() => ComicImport::start( $book['uuid'], array( $undated_id ), false ), 'Re-selecting an imported issue skips it unless refresh is requested' );
comic_check( 'post_object' === acf_get_field( Acf::key( 'msc_comic', 'story_arcs' ) )['type'] && ! empty( acf_get_field( Acf::key( 'msc_comic', 'story_arcs' ) )['multiple'] ), 'ACF offers a searchable multi-arc selector on Comic Books' );
$empty = $store->create( 'msc_comic', array( 'title' => 'Empty Comic ' . $base ) ); comic_publish( $empty ); ob_start(); get_template_part( 'parts/detail', 'comic', array( 'record' => Presentation::record( $empty['uuid'] ) ) ); $html = ob_get_clean(); comic_check( ! str_contains( $html, 'data-comic-browser' ), 'Empty public series hide the issue section' );
$pagination = $store->create( 'msc_comic', array( 'title' => 'Pagination Comic ' . $base ) );
for ( $n = 1; $n <= 51; ++$n ) { $new = $store->create( 'msc_comic_issue', array( 'title' => 'Pagination #' . $n, 'parent_comic' => $pagination['uuid'], 'issue_number' => (string) $n ) ); Plugin::materializer()->sync( $new['id'], get_current_blog_id() ); comic_publish( $new ); }
comic_publish( $pagination ); $paged = Comics::issue_page( Presentation::record( $pagination['uuid'] ), array( 'comic_page' => 2 ) ); comic_check( 2 === $paged['pages'] && 1 === count( $paged['issues'] ) && '51' === $paged['issues'][0]['data']['issue_number'], 'Issue lists paginate beyond fifty records without truncation or lexical ordering' );
$info = array( 'book' => Presentation::url( $book ), 'arc' => Presentation::url( $arc ), 'issue' => Presentation::url( Presentation::record( $issue['uuid'] ) ), 'admin' => ComicAdmin::url( $book ), 'source' => ComicAdmin::url( $book, true ), 'query' => $query, 'empty_arc' => $empty_arc['uuid'], 'pagination' => Presentation::url( Presentation::record( $pagination['uuid'] ) ), 'calendar' => MSC\NetworkSites::calendar_url() . '?calendar_month=2099-03&release_type=msc_comic_issue', 'acf' => admin_url( 'admin.php?page=msc-acf&record=' . $issue['uuid'] ) );
echo 'COMIC_BROWSER_JSON:' . wp_json_encode( $info ) . "\n";
restore_current_blog(); WP_CLI::success( $GLOBALS['comic_checks'] . ' comic schema, source, relationship, visibility, batch and calendar checks passed.' );
