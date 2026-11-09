<?php
/**
 * Prove that a formatting change did not change any PHP code.
 *
 * Compares the syntax tree of every tracked PHP file in the working tree with the
 * same file at a Git revision. Comments, positions and literal styles (quotes,
 * array() versus []) are ignored; everything else, including inline HTML, must match.
 *
 * Usage: php tools/ast-equivalence.php <revision> [path ...]
 *
 * @package MultisiteSharedContent
 */

$autoload = getenv( 'MSC_TOOLS_AUTOLOAD' ) ?: dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, "Run composer install first.\n" );
	exit( 2 );
}
require $autoload;

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

$revision = $argv[1] ?? '';
if ( '' === $revision ) {
	fwrite( STDERR, "Usage: php tools/ast-equivalence.php <revision> [path ...]\n" );
	exit( 2 );
}
$paths = array_slice( $argv, 2 );

$list  = 'git ls-files -z -- ' . ( $paths ? implode( ' ', array_map( 'escapeshellarg', $paths ) ) : "'*.php'" );
$files = array_filter(
	explode( "\0", (string) shell_exec( $list ) ),
	static fn( $file ) => str_ends_with( $file, '.php' )
);

$parser  = ( new ParserFactory() )->createForHostVersion();
$dumper  = new NodeDumper(
	array(
		'dumpComments'  => false,
		'dumpPositions' => false,
	)
);
$dump    = static fn( string $code ) => $dumper->dump( $parser->parse( $code ) );
$changed = 0;
$checked = 0;

foreach ( $files as $file ) {
	$before = shell_exec( 'git show ' . escapeshellarg( $revision . ':' . $file ) . ' 2>/dev/null' );
	if ( null === $before || false === $before ) {
		continue; // New file: nothing to compare.
	}
	++$checked;
	try {
		if ( $dump( $before ) !== $dump( (string) file_get_contents( $file ) ) ) {
			++$changed;
			echo "CHANGED: $file\n";
		}
	} catch ( Throwable $e ) {
		++$changed;
		echo "ERROR: $file: {$e->getMessage()}\n";
	}
}

echo "$checked files compared, $changed differ.\n";
exit( $changed ? 1 : 0 );
