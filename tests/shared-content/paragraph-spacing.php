<?php
use MSC\{OpenSources,OpenImport,Plugin};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() || '1' !== getenv( 'MSC_DISPOSABLE_TESTS' ) ) { throw new RuntimeException( 'Disposable local tests required.' ); }
wp_set_current_user( 1 ); $GLOBALS['paragraph_checks'] = 0;
function paragraph_check( $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['paragraph_checks']; WP_CLI::log( 'PASS: ' . $message ); }
$text = "Alfred Hitchcock directed films including Rear Window (1954) and Psycho (1960).\nHitchcock's other notable films include Rope (1948), Strangers on a Train (1951), Dial M for Murder (1954), To Catch a Thief (1955), The Trouble with Harry (1955), Vertigo (1958), North by Northwest (1959), The Birds (1963), Marnie (1964) and Frenzy (1972).\nIn 2012, Hitchcock's psychological thriller Vertigo, starring James Stewart, displaced Citizen Kane as the British Film Institute's greatest film ever made.";
$fresh = OpenSources::wikipedia_paragraphs( $text );
paragraph_check( 3 === substr_count( $fresh, '<p>' ) && ! str_contains( $fresh, '<br' ), 'Wikipedia single-newline paragraphs become separate paragraph elements' );
paragraph_check( $fresh === OpenSources::wikipedia_paragraphs( str_replace( "\n", "\r\n\r\n", $text ) ), 'Single/blank Windows and Unix paragraph separators produce the same markup' );
$legacy = wpautop( esc_html( $text ) ); $record = Plugin::store()->create( 'msc_person', array( 'title' => 'Biography paragraph spacing ' . substr( wp_generate_uuid4(), 0, 8 ), 'content' => $legacy, 'additional_metadata' => array( 'open_import' => array( 'fields' => array( 'content' => array( 'hash' => OpenImport::hash( $legacy ), 'source' => OpenSources::source( 'wikipedia', 'https://en.wikipedia.org/wiki/Alfred_Hitchcock' ) ) ) ) ) ) );
$html = msc_screen_overview( $record ); paragraph_check( 3 === substr_count( $html, '<p>' ) && ! str_contains( $html, '<br' ), 'Previously imported biographies gain real paragraphs without reimporting' );
paragraph_check( Plugin::store()->get( $record['id'] )['data']['content'] === $legacy, 'Display repair preserves stored content and source hashes' );
$edited = $record; $edited['data']['content'] = str_replace( 'Alfred Hitchcock', 'Edited biography', $legacy ); paragraph_check( str_contains( msc_screen_overview( $edited ), 'catalog-paragraph-break' ), 'Edited overviews retain visible breaks without matching import metadata' );
paragraph_check( ! str_contains( OpenSources::wikipedia_paragraphs( "<script>alert(1)</script>\nSecond paragraph." ), '<script>' ), 'Source paragraph conversion still escapes plaintext markup' );
$cases = array(
    '<p><strong>First</strong><br>Second<br />Third</p>',
    '<p><strong>First<br />Second<br>Third</strong></p>',
    '<p>First<br><br />Second<br /><br>Third</p>',
    "First\nSecond\nThird",
);
foreach ( $cases as $case ) { $sample = array( 'data' => array( 'content' => $case ) ); $out = msc_screen_overview( $sample ); paragraph_check( 2 === substr_count( $out, 'class="catalog-paragraph-break"' ) + substr_count( $out, '<p>' ) - 1, 'Unmarked movie overview breaks receive consistent spacing regardless of source' ); }
$structured = '<pre>First<br>Second</pre><blockquote>First<br>Second</blockquote><ul><li>First<br>Second</li></ul><table><tr><td>First<br>Second</td></tr></table>';
paragraph_check( $structured === msc_screen_overview_breaks( $structured ), 'Code, quotations, lists and tables keep their explicit line breaks' );
paragraph_check( '<p><strong>First<span class="catalog-paragraph-break" aria-hidden="true"></span>\nSecond</strong></p>' === str_replace( "\n", '\n', msc_screen_overview_breaks( '<p><strong>First<br>Second</strong></p>' ) ), 'Inline emphasis remains intact across legacy overview breaks' );
if ( ! defined( 'MSC_OMDB_API_KEY' ) ) { define( 'MSC_OMDB_API_KEY', 'paragraph-fixture-key' ); }
$fixture_id = 'tt' . random_int( 9000000, 9999999 ); $plot = "First paragraph.\nSecond paragraph.\nThird paragraph.";
$http_fixture = static function ( $pre, $args, $url ) use ( $fixture_id, $plot ) { if ( 'www.omdbapi.com' !== wp_parse_url( $url, PHP_URL_HOST ) || ! str_contains( $url, $fixture_id ) ) { return $pre; } return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'Response' => 'True', 'imdbID' => $fixture_id, 'Title' => 'Film paragraph fixture', 'Type' => 'movie', 'Plot' => $plot ) ) ); };
add_filter( 'pre_http_request', $http_fixture, 10, 3 ); try { $details = \MSC\OmdbClient::details( $fixture_id ); paragraph_check( $plot === $details['Plot'], 'OMDb plot sanitization preserves paragraph newlines for future imports' ); } finally { remove_filter( 'pre_http_request', $http_fixture, 10 ); }
$film_text = array( "Avengers: Doomsday is an upcoming American superhero film based on the Marvel Comics superhero team the Avengers.", "Two new Avengers films, The Kang Dynasty and Secret Wars, were announced in July 2022. The revised film features Doctor Doom.", "Avengers: Doomsday is scheduled to be released in the United States on December 18, 2026." );
$markers = array(); foreach ( $film_text as $index => $paragraph ) { $markers[] = '<span id="film-paragraph-' . $index . '">' . esc_html( $paragraph ) . '</span>'; }
$variants = array( 'edited-br' => '<p><strong>' . implode( '<br />', $markers ) . '</strong></p>', 'plain-newlines' => implode( "\n", $markers ), 'editor-wrappers' => '<div><p>' . implode( '</p></div><div><p>', $markers ) . '</p></div>', 'normal-paragraphs' => '<p>' . implode( '</p><p>', $markers ) . '</p>' );
$film_urls = array();
foreach ( $variants as $label => $value ) {
    $movie = Plugin::store()->create( 'msc_movie', array( 'title' => 'Avengers Doomsday overview ' . $label . ' ' . substr( wp_generate_uuid4(), 0, 6 ), 'content' => $value ) );
    Plugin::materializer()->sync( (int) $movie['id'], get_current_blog_id() ); $m = Plugin::store()->mapping( (int) $movie['id'] ); Plugin::store()->local( (int) $movie['id'], array(), array(), (int) $m['local_version'], 'publish' ); Plugin::materializer()->sync( (int) $movie['id'], get_current_blog_id() );
    $film_urls[] = array( 'label' => $label, 'url' => get_permalink( $m['post_id'] ) );
}
echo 'FILM_PARAGRAPH_BROWSER_JSON:' . wp_json_encode( $film_urls ) . "\n";
Plugin::materializer()->sync( (int) $record['id'], get_current_blog_id() ); $map = Plugin::store()->mapping( (int) $record['id'] ); Plugin::store()->local( (int) $record['id'], array(), array(), (int) $map['local_version'], 'publish' ); Plugin::materializer()->sync( (int) $record['id'], get_current_blog_id() );
echo 'PARAGRAPH_BROWSER_JSON:' . wp_json_encode( array( 'url' => get_permalink( $map['post_id'] ) ) ) . "\n";
WP_CLI::success( $GLOBALS['paragraph_checks'] . ' paragraph formatting checks passed.' );
