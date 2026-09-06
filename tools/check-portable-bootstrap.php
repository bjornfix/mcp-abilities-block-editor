<?php
/** Check plugin loading without a browser runtime or global save interception. */
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
$hooks = array();
function add_action( string $name, ...$args ): void {
	$GLOBALS['hooks'][] = $name;
}
function add_filter( string $name, ...$args ): void {
	$GLOBALS['hooks'][] = $name;
}
function did_action( string $name ): int {
	return 0;
}

require_once dirname( __DIR__ ) . '/mcp-abilities-block-editor.php';

if ( array( 'wp_abilities_api_categories_init', 'wp_abilities_api_init' ) !== $hooks ) {
	throw new RuntimeException( 'The public plugin must register abilities without intercepting native saves.' );
}
if ( ! function_exists( 'mcp_abilities_gutenberg_validate_content_write_policy' ) ) {
	throw new RuntimeException( 'The optional site write-policy Interface is missing.' );
}
echo "Portable plugin bootstrap passed; native saves are not intercepted.\n";
