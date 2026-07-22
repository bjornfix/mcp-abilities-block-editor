<?php
/** Static caller-identity contract for page creation abilities. */

declare( strict_types=1 );

$abilities  = file_get_contents( dirname( __DIR__ ) . '/includes/abilities.php' );
$generation = file_get_contents( dirname( __DIR__ ) . '/includes/generation.php' );
if ( false === $abilities || false === $generation ) {
	throw new RuntimeException( 'Unable to read ability registrations.' );
}
$source = $abilities . "\n" . $generation;
$bootstrap = file_get_contents( dirname( __DIR__ ) . '/mcp-abilities-block-editor.php' );
if ( false === $bootstrap || 1 !== preg_match( '/\$args\[\x27category\x27\]\s*=\s*\x27block-editor\x27/', $bootstrap ) ) {
	throw new RuntimeException( 'Central ability registrar does not supply the owned category default.' );
}

foreach ( array( 'gutenberg/create-page-from-pattern', 'gutenberg/create-landing-page', 'gutenberg/create-template', 'gutenberg/update-template', 'gutenberg/create-template-part', 'gutenberg/update-template-part', 'gutenberg/create-navigation', 'gutenberg/update-navigation', 'gutenberg/create-synced-pattern', 'gutenberg/update-synced-pattern' ) as $ability ) {
	if ( 1 !== preg_match( "/'content_write_ability'\\s*=>\\s*'" . preg_quote( $ability, '/' ) . "'/", $source ) ) {
		throw new RuntimeException( "Page writer does not forward exact caller identity: {$ability}." );
	}
}

fwrite( STDOUT, "Page-write caller identity contract passed.\n" );
