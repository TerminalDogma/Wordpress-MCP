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
$edited = $record; $edited['data']['content'] = str_replace( 'Alfred Hitchcock', 'Edited biography', $legacy ); paragraph_check( str_contains( msc_screen_overview( $edited ), '<br' ), 'Edited/manual hard breaks retain their original formatting' );
paragraph_check( ! str_contains( OpenSources::wikipedia_paragraphs( "<script>alert(1)</script>\nSecond paragraph." ), '<script>' ), 'Source paragraph conversion still escapes plaintext markup' );
Plugin::materializer()->sync( (int) $record['id'], get_current_blog_id() ); $map = Plugin::store()->mapping( (int) $record['id'] ); Plugin::store()->local( (int) $record['id'], array(), array(), (int) $map['local_version'], 'publish' ); Plugin::materializer()->sync( (int) $record['id'], get_current_blog_id() );
echo 'PARAGRAPH_BROWSER_JSON:' . wp_json_encode( array( 'url' => get_permalink( $map['post_id'] ) ) ) . "\n";
WP_CLI::success( $GLOBALS['paragraph_checks'] . ' paragraph formatting checks passed.' );
