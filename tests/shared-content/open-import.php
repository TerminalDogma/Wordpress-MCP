<?php
/** Real WordPress storage and provider-contract coverage; all fixtures are additive. */
use MSC\{OpenClient,OpenSources,OpenImport,OpenAdmin,SourceError,Attribution,Plugin,Queue,Database};
if ( ! defined( 'WP_CLI' ) || ! is_multisite() || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Requires a disposable local multisite.' ); }
define( 'MSC_OPEN_TEST_FIXTURES', true ); require '/msc-tests/open-fixtures.php';
$GLOBALS['msc_open_checks'] = 0;
function open_check( $value, string $message ): void { ++$GLOBALS['msc_open_checks']; if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); } WP_CLI::log( 'PASS: ' . $message ); }
function open_reject( callable $fn, string $message, ?int $status = null ): void { $error = null; try { $fn(); } catch ( Throwable $e ) { $error = $e; } open_check( $error && ( null === $status || $error instanceof SourceError && $status === $error->status ), $message ); }
function open_rates(): void { foreach ( array( 'tvmaze', 'wikidata', 'wikipedia', 'commons' ) as $provider ) { delete_site_transient( 'msc_open_rate_' . $provider ); delete_site_transient( 'msc_open_backoff_' . $provider ); } }
function open_preview( string $provider, string $id, string $type, string $target = '', string $parent = '' ): array { open_rates(); return OpenImport::preview( $provider, $id, $type, $target, $parent ); }
function open_request( array $s, ?array $fields = null, array $roles = array(), bool $image = false ): array { return array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $s['token'], 'fields' => $fields ?? array_keys( $s['fields'] ), 'roles' => $roles, 'image' => $image ? '1' : '' ); }
function open_drain(): void { for ( $i = 0; $i < 100 && Queue::status()['pending']; ++$i ) { $r = Queue::run( 1000 ); if ( $r['errors'] || ! $r['processed'] ) { throw new RuntimeException( 'Queue needs recovery.' ); } } }
function open_count( string $type ): int { global $wpdb; return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Database::table( 'records' ) . ' WHERE type=%s', $type ) ); }
wp_set_current_user( 1 ); $seed_before = get_network_option( get_current_network_id(), 'msc_open_fixture_seed', false ); $seed = random_int( 500000000, 899999000 ); update_network_option( get_current_network_id(), 'msc_open_fixture_seed', $seed );
$sites = array(); foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( in_array( $site->path, array( '/site-a/', '/site-b/', '/site-c/' ), true ) ) { $sites[] = (int) $site->blog_id; } } [ $a, $b, $c ] = $sites; switch_to_blog( $a );
try {
    open_rates(); $before = MSC_Open_Fixtures::$requests;
    $shows = OpenSources::search( 'msc_episode', 'Open Show ' . $seed ); $again = OpenSources::search( 'msc_episode', 'Open Show ' . $seed );
    open_check( $shows['items'][0]['id'] === (string) ( $seed + 1 ), 'Episode search finds Shows rather than unrelated title matches' );
    open_check( $shows === $again && MSC_Open_Fixtures::$requests === $before + 1, 'Repeated API searches use the shared response cache' );
    open_check( 1 === count( OpenSources::search( 'msc_movie', 'Open Film ' . $seed )['items'] ), 'Wikidata Movie search accepts film subclasses and excludes People' );
    open_check( 2 === count( OpenSources::search( 'msc_person', 'Alex ' . $seed )['items'] ), 'People search checks the human class and retains namesakes separately' );
    open_check( 'tvmaze' === OpenSources::search( 'msc_person', 'Alex ' . $seed, 'tvmaze' )['items'][0]['provider'], 'TVmaze is an alternate keyless TV People source' );
    open_reject( static fn() => OpenSources::search( 'msc_movie', '' ), 'Empty searches are rejected', 400 );
    open_reject( static fn() => OpenClient::get( 'other' ), 'Unlisted source hosts are rejected', 400 );
    open_reject( static fn() => OpenClient::get( 'tvmaze', '../admin' ), 'Source paths cannot escape the API endpoint', 400 );
    open_reject( static fn() => OpenImport::preview( 'tvmaze', (string) ( $seed + 11 ), 'msc_episode' ), 'Episodes cannot be imported before their matching parent Show', 400 );
    $show_preview = open_preview( 'tvmaze', (string) ( $seed + 1 ), 'msc_show' );
    open_check( null === $show_preview['image'] && ! isset( $show_preview['fields']['poster'] ), 'TVmaze studio images never become commercially cleared imports' );
    open_check( ! str_contains( $show_preview['fields']['content'], '<script' ), 'Provider descriptions are sanitized before preview and storage' );
    $show = OpenImport::import( open_request( $show_preview, null, array_keys( $show_preview['roles'] ) ) )['record'];
    open_check( $show['data']['premiere_date'] === '2020-01-01' && $show['data']['number_of_seasons'] === 1, 'Show fields retain premiere date and actual season count' );
    open_check( 1 === count( $show['data']['actors'] ) && $show['data']['actors'] === $show['data']['directors'], 'Stable TVmaze Person IDs reuse one Person across credit roles' );
    open_check( Plugin::store()->resolved( $show['uuid'] )['mapping']['status'] === 'draft', 'New shared imports follow the draft publication default' );
    $episodes = OpenSources::episodes( (string) ( $seed + 1 ) );
    open_check( array_column( $episodes, 'id' ) === array_map( 'strval', array( $seed + 14, $seed + 11, $seed + 12, $seed + 13, $seed + 15 ) ), 'Episode lists sort by season/number, with unnumbered specials retained' );
    $ep = open_preview( 'tvmaze', (string) ( $seed + 11 ), 'msc_episode' );
    open_check( $ep['fields']['parent_show'] === $show['uuid'] && $ep['fields']['season_number'] === 1 && $ep['fields']['episode_number'] === 1, 'Episode details resolve their own verified Show and exact numbering' );
    open_check( $ep['fields']['release_date'] === '2020-01-01' && $ep['fields']['air_time'] === '22:00' && $ep['fields']['time_zone'] === 'America/New_York' && (float) $ep['fields']['runtime'] === 42.0, 'Air date, local air time, timezone and runtime map without a UTC date shift' );
    open_check( isset( $ep['roles']['actors'], $ep['roles']['directors'] ), 'Episode guest cast and crew are available as reviewed Person links' );
    $special = open_preview( 'tvmaze', (string) ( $seed + 13 ), 'msc_episode' );
    open_check( ! isset( $special['fields']['episode_number'], $special['fields']['release_date'], $special['fields']['runtime'] ), 'Unknown episode numbers, dates and runtime remain unset' );
    $zero = open_preview( 'tvmaze', (string) ( $seed + 14 ), 'msc_episode' );
    open_check( 0 === $zero['fields']['season_number'] && 0 === $zero['fields']['episode_number'] && 0.0 === (float) $zero['fields']['runtime'], 'Explicit season zero, episode zero and zero runtime are preserved' );
    $bad = open_request( $ep ); $bad['nonce'] = 'forged'; open_reject( static fn() => OpenImport::import( $bad ), 'Imports require a valid CSRF nonce', 403 );
    $bad = open_request( $ep ); $bad['fields'][] = 'forged'; open_reject( static fn() => OpenImport::import( $bad ), 'Import fields must match the saved server-side preview', 400 );
    $bad = open_request( $ep ); $bad['fields'] = array( array( 'title' ) ); open_reject( static fn() => OpenImport::import( $bad ), 'Nested forged selections are rejected', 400 );
    $bad = open_request( $ep, array( 'runtime' ) ); open_reject( static fn() => OpenImport::import( $bad ), 'New Episode imports require their verified parent and title', 400 );
    switch_to_blog( $b ); open_reject( static fn() => OpenImport::import( open_request( $ep ) ), 'Preview tokens are bound to the originating site', 403 ); restore_current_blog();
    $saved = OpenImport::import( open_request( $ep, null, array_keys( $ep['roles'] ) ) ); $episode = $saved['record'];
    open_check( $episode['data']['parent_show'] === $show['uuid'] && $episode['data']['external_ids']['tvmaze'] === (string) ( $seed + 11 ), 'Episode imports store permanent source identity and a shared parent UUID' );
    $before = open_count( 'msc_episode' ); $replay = OpenImport::import( open_request( $ep ) ); open_check( $replay['record']['uuid'] === $episode['uuid'] && $before === open_count( 'msc_episode' ), 'Replayed creates are idempotent and do not duplicate Episodes' );
    open_reject( static fn() => OpenImport::batch_start( $show['uuid'], array( (string) ( $seed + 99 ) ), false ), 'Season batches reject episode IDs outside the selected Show', 400 );
    $job = OpenImport::batch_start( $show['uuid'], array_map( 'strval', array( $seed + 11, $seed + 12, $seed + 13, $seed + 14 ) ), false );
    open_check( 3 === count( OpenImport::batch( $job )['items'] ), 'Season imports skip existing source IDs unless refresh is requested' );
    for ( $i = 0; $i < 3; ++$i ) { open_rates(); $state = OpenImport::batch_step( $job ); open_check( $state['index'] === $i + 1 && count( $state['results'] ) === $i + 1, 'Batch progress is saved after each Episode' ); }
    $before = open_count( 'msc_episode' ); OpenImport::batch_step( $job ); open_check( $before === open_count( 'msc_episode' ), 'Resuming a completed batch creates no duplicates' );
    open_check( ! array_filter( $state['results'], static fn( $row ) => 'Saved' !== $row['status'] ), 'Every selected new Episode has an explicit successful result' );
    $job = OpenImport::batch_start( $show['uuid'], array( (string) ( $seed + 11 ) ), true ); $r = Plugin::store()->get( $episode['uuid'] ); Plugin::store()->update( $r['uuid'], array( 'seo_title' => 'Concurrent editor' ), (int) $r['version'] );
    $state = OpenImport::batch_step( $job ); open_check( str_starts_with( $state['results'][0]['status'], 'Needs review:' ), 'Batch refresh detects edits made after the season was reviewed' );
    open_drain(); switch_to_blog( $b ); $r = Plugin::store()->resolved( $episode['uuid'] ); Plugin::store()->local( $r['uuid'], array( 'runtime' => 999, 'content' => '<p>Local copy.</p>' ), array(), (int) $r['mapping']['local_version'] ); restore_current_blog();
    $fresh = open_preview( 'tvmaze', (string) ( $seed + 11 ), 'msc_episode' ); OpenImport::import( open_request( $fresh, array( 'runtime', 'content' ) ) ); open_drain();
    open_check( 42 === msc_get_field( $episode['uuid'], 'runtime', $a ) && 999 === msc_get_field( $episode['uuid'], 'runtime', $b ), 'Global Episode refresh preserves per-site runtime overrides' );
    $stale = open_preview( 'tvmaze', (string) ( $seed + 11 ), 'msc_episode' ); $r = Plugin::store()->get( $episode['uuid'] ); Plugin::store()->update( $r['uuid'], array( 'seo_title' => 'Another edit' ), (int) $r['version'] );
    open_reject( static fn() => OpenImport::import( open_request( $stale, array( 'runtime' ) ) ), 'Stale individual previews cannot overwrite concurrent global edits', 409 );
    $groups = \MSC\Relationships::seasons( $show['uuid'], 0 );
    open_check( array_column( $groups[1], 'uuid' )[0] === $episode['uuid'], 'Materialized season queries put numbered Episodes before unnumbered specials' );
    $unknown = Plugin::store()->create( 'msc_episode', array( 'title' => 'Unknown season fixture', 'parent_show' => $show['uuid'] ) );
    $groups = \MSC\Relationships::seasons( $show['uuid'], 0 );
    open_check( isset( $groups['unknown'] ) && $groups['unknown'][0]['uuid'] === $unknown['uuid'] && array_key_last( $groups ) === 'unknown', 'Absent seasons are grouped separately instead of being invented as season zero' );
    $wrong_show = Plugin::store()->create( 'msc_show', array( 'title' => 'Wrong parent fixture', 'external_ids' => array( 'tvmaze' => (string) ( $seed + 99 ) ) ) );
    open_reject( static fn() => open_preview( 'tvmaze', (string) ( $seed + 11 ), 'msc_episode', '', $wrong_show['uuid'] ), 'Individual previews reject a parent with another TVmaze Show identity', 400 );
    $legacy_ep = Plugin::store()->create( 'msc_episode', array( 'title' => 'Legacy episode fixture', 'parent_show' => $show['uuid'], 'season_number' => 8, 'episode_number' => 9 ) );
    open_reject( static fn() => open_preview( 'tvmaze', (string) ( $seed + 13 ), 'msc_episode', $legacy_ep['uuid'] ), 'An explicit migration target cannot steal an Episode ID from another shared record', 409 );
    $migration = open_preview( 'tvmaze', (string) ( $seed + 15 ), 'msc_episode', $legacy_ep['uuid'] );
    $migrated = OpenImport::import( open_request( $migration ) )['record'];
    open_check( $migrated['uuid'] === $legacy_ep['uuid'] && $migrated['data']['season_number'] === 2 && $migrated['data']['episode_number'] === 1 && $migrated['data']['external_ids']['tvmaze'] === (string) ( $seed + 15 ), 'An explicitly connected legacy Episode is corrected in place without creating a duplicate' );
    $movie_preview = open_preview( 'wikidata', 'Q' . ( $seed + 101 ), 'msc_movie' );
    open_check( 120.0 === (float) $movie_preview['fields']['runtime'] && '2001-02-03' === $movie_preview['fields']['release_date'], 'Wikidata runtime units convert to minutes and full release dates are retained' );
    open_check( 'United States' === $movie_preview['fields']['country'] && array( 'Drama' ) === $movie_preview['fields']['genres'], 'Movie country/language/genre entity IDs resolve to editable taxonomy names' );
    open_check( 'CC BY-SA 4.0' === $movie_preview['field_sources']['content']['license'] && 'CC0 1.0' === $movie_preview['source']['license'], 'Wikipedia prose and Wikidata facts retain their separate licences' );
    open_check( $movie_preview['image']['source']['attribution'] === 'Fixture Photographer', 'Commons author markup is converted to a safe retained credit' );
    $movie = OpenImport::import( open_request( $movie_preview, null, array_keys( $movie_preview['roles'] ), true ) )['record'];
    open_check( 2 === count( $movie['data']['actors'] ) && $movie['data']['actors'][0] !== $movie['data']['actors'][1], 'People with identical names and different Wikidata IDs stay distinct' );
    open_check( $movie['data']['directors'][0] === $movie['data']['actors'][0], 'One Wikidata Person links across multiple roles without duplicates' );
    $ref = $movie['data']['poster']; open_check( $ref['site_id'] === $a && wp_attachment_is_image( $ref['attachment_id'] ), 'Opted-in Commons images are saved as real owner-scoped media' );
    open_check( str_contains( get_post( $ref['attachment_id'] )->post_excerpt, 'creativecommons.org/licenses/by-sa/4.0/' ) && get_post_meta( $ref['attachment_id'], '_msc_open_source', true )['attribution'] === 'Fixture Photographer', 'Media captions and metadata retain the source, author, licence and changes' );
    open_check( str_contains( Attribution::render( Plugin::store()->resolved( $movie['uuid'] ) ), 'Wikipedia contributors' ) && str_contains( Attribution::image_html( Plugin::store()->resolved( $movie['uuid'] ) ), 'Fixture Photographer' ), 'The effective Movie text and image expose their correct attribution' );
    $person = open_preview( 'wikidata', 'Q' . ( $seed + 102 ), 'msc_person' );
    open_check( $person['target'] === $movie['data']['actors'][0], 'Enriching a credited Person reuses their source identity' );
    $person_record = OpenImport::import( open_request( $person ) )['record'];
    open_check( $person_record['data']['birth_date'] === '1980-04-05' && str_contains( $person_record['data']['content'], 'Biography' ) && $person_record['data']['external_ids']['imdb'] === 'nm' . ( $seed + 102 ), 'People enrichment imports birth date, biography, categories and a verified IMDb Person ID' );
    $partial = open_preview( 'wikidata', 'Q' . ( $seed + 103 ), 'msc_person' ); open_check( ! isset( $partial['fields']['birth_date'] ) && null === $partial['image'], 'Year-only birth dates and NC images are not converted into usable exact dates or images' );
    open_rates(); open_check( null === OpenSources::commons( 'Open ND.png' ), 'No-derivatives image licences are excluded' ); open_check( null === OpenSources::commons( 'Restricted.png' ), 'Images reporting additional restrictions require manual review' );
    $fresh = open_preview( 'wikidata', 'Q' . ( $seed + 101 ), 'msc_movie' ); MSC_Open_Fixtures::$bad_image = true; $bad_image = OpenImport::import( open_request( $fresh, array( 'runtime' ), array(), true ) ); MSC_Open_Fixtures::$bad_image = false;
    open_check( $bad_image['record']['data']['poster'] === $ref && (bool) $bad_image['warnings'], 'A failed image download preserves the current asset and saves valid selected facts' );
    // A competing writer during image download must roll back nested Person creation as well.
    $race_preview = open_preview( 'wikidata', 'Q' . ( $seed + 101 ), 'msc_movie' ); $race = null;
    $race = static function ( $pre, $args, $url ) use ( $movie, &$race ) { if ( str_contains( $url, 'msc-open-fixture.png' ) ) { remove_filter( 'pre_http_request', $race, 1 ); $r = Plugin::store()->get( $movie['uuid'] ); Plugin::store()->update( $r['uuid'], array( 'seo_description' => 'Keep concurrent edit' ), (int) $r['version'] ); } return $pre; }; add_filter( 'pre_http_request', $race, 1, 3 );
    $before = count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1 ) ) );
    try { open_reject( static fn() => OpenImport::import( open_request( $race_preview, array( 'runtime' ), array(), true ) ), 'Concurrent edits during media download reject the stale import' ); } finally { remove_filter( 'pre_http_request', $race, 1 ); }
    open_check( $before === count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1 ) ) ), 'A failed import removes only its newly downloaded attachment' );
    $r = Plugin::store()->get( $movie['uuid'] ); Plugin::store()->update( $r['uuid'], array( 'title' => 'Curated title', 'external_ids' => $r['data']['external_ids'] + array( 'custom' => 'Keep' ), 'additional_metadata' => $r['data']['additional_metadata'] + array( 'custom' => 'Keep' ) ), (int) $r['version'] );
    $fresh = open_preview( 'wikidata', 'Q' . ( $seed + 101 ), 'msc_movie' ); $updated = OpenImport::import( open_request( $fresh, array( 'runtime' ) ) )['record'];
    open_check( $updated['data']['title'] === 'Curated title' && $updated['data']['external_ids']['custom'] === 'Keep' && $updated['data']['additional_metadata']['custom'] === 'Keep', 'Selective refresh preserves curated fields, other IDs and custom metadata' );
    $r = Plugin::store()->resolved( $movie['uuid'] ); Plugin::store()->local( $r['uuid'], array( 'content' => '<p>Authored locally.</p>', 'excerpt' => 'Local synopsis' ), array(), (int) $r['mapping']['local_version'] );
    open_check( ! str_contains( Attribution::render( Plugin::store()->resolved( $movie['uuid'] ) ), 'Wikipedia contributors' ), 'Source credit is not falsely attached to locally authored replacement prose' );
    $get = $_GET; $_GET = array( 'page' => 'msc-import', 'kind' => 'msc_person', 'provider' => 'wikidata', 'id' => 'Q' . ( $seed + 102 ), '_wpnonce' => wp_create_nonce( 'msc_open_search' ) ); ob_start(); OpenAdmin::page(); $html = ob_get_clean(); $_GET = $get;
    open_check( str_contains( $html, 'Import selected global fields' ) && str_contains( $html, 'Licensed image' ) && str_contains( $html, 'Wikipedia contributors' ), 'Real admin previews expose selective People import, image previews and source licences' );
    open_check( ! str_contains( $html, 'name="fields[]" value="title" checked' ), 'Existing global records start with import fields unchecked' );
    $uid = wp_insert_user( array( 'user_login' => 'open-editor-' . $seed, 'user_pass' => wp_generate_password(), 'role' => 'editor' ) ); add_user_to_blog( $a, (int) $uid, 'editor' ); wp_set_current_user( (int) $uid );
    open_check( ! OpenImport::allowed(), 'Local-only editors cannot perform global imports' ); open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'Open Show' ) ), 'Cached APIs are also protected from local-only editors', 403 ); wp_set_current_user( 1 );
    open_rates(); open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'malformed' ) ), 'Unreadable provider responses have an actionable error', 502 );
    open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'offline' ) ), 'Transport failures leave saved content intact', 502 );
    open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'quota' ) ), 'Provider rate limits request a retry rather than a false successful import', 429 );
    open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'new-query' ) ), 'Retry-After is enforced across the network', 429 );
    open_check( $shows === OpenSources::search( 'msc_episode', 'Open Show ' . $seed ), 'Cached search results remain available during source cooldown' );
    open_rates(); for ( $i = 0; $i < 12; ++$i ) { OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'budget-' . $seed . '-' . $i ) ); }
    open_reject( static fn() => OpenClient::get( 'tvmaze', 'search/shows', array( 'q' => 'budget-extra-' . $seed ) ), 'A shared request budget prevents unbounded upstream traffic', 429 );
    WP_CLI::success( $GLOBALS['msc_open_checks'] . ' open-source import, Episode, identity, licence, media, permission and concurrency checks passed.' );
} finally {
    wp_set_current_user( 1 ); open_rates(); restore_current_blog();
    if ( false === $seed_before ) { delete_network_option( get_current_network_id(), 'msc_open_fixture_seed' ); } else { update_network_option( get_current_network_id(), 'msc_open_fixture_seed', $seed_before ); }
}
