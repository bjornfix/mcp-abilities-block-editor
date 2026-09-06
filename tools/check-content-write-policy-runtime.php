<?php
/**
 * Standalone regression for the neutral Block Document Write policy seam.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

final class WP_Post {
	public int $ID = 42;
	public string $post_type = 'page';
	public string $post_status = 'publish';
}

final class WP_Error {
	public function __construct( public string $code, public string $message, public $data = null ) {}
}

$GLOBALS['site_write_policy_enabled'] = true;
$GLOBALS['site_write_policy_calls']   = 0;

function add_filter( ...$args ): void { unset( $args ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
function parse_blocks( string $content ): array { unset( $content ); return array(); }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function apply_filters( string $name, $value, ...$args ) {
	if ( 'mcp_content_design_markup_markers' === $name && ! empty( $GLOBALS['invalid_marker_adapter'] ) ) {
		return new WP_Error( 'invalid_adapter_output', 'Fixture invalid marker response.' );
	}
	if ( 'mcp_content_write_preflight' !== $name || empty( $GLOBALS['site_write_policy_enabled'] ) ) {
		return $value;
	}
	$GLOBALS['site_write_policy_calls']++;
	$context = is_array( $args[0] ?? null ) ? $args[0] : array();
	if ( 'page' !== (string) ( $context['post_type'] ?? '' ) || 'gutenberg/update-post-blocks' !== (string) ( $context['ability'] ?? '' ) ) {
		throw new RuntimeException( 'The neutral Block Document Write context lost caller intent.' );
	}
	return new WP_Error( 'site_policy_rejected', 'Rejected by fixture site policy.' );
}

require_once dirname( __DIR__ ) . '/includes/core-blocks.php';
require_once dirname( __DIR__ ) . '/includes/content-analysis.php';

$valid_markup = mcp_abilities_gutenberg_assert_valid_gutenberg_content( '<!-- wp:fixture/text --><p class="fixture-text">A valid paragraph.</p><!-- /wp:fixture/text -->' );
if ( true !== $valid_markup ) {
	throw new RuntimeException( 'Valid block text markup was rejected.' );
}

$valid_link = mcp_abilities_gutenberg_assert_valid_gutenberg_content( '<!-- wp:fixture/text --><a class="fixture-text button" href="https://example.com/">A valid link</a><!-- /wp:fixture/text -->' );
if ( true !== $valid_link ) {
	throw new RuntimeException( 'Valid quoted link attributes were rejected as malformed markup.' );
}

$malformed_markup = mcp_abilities_gutenberg_assert_valid_gutenberg_content( '<!-- wp:fixture/text --><p class="fixture-text"Fixture text/p><!-- /wp:fixture/text -->' );
if ( ! $malformed_markup instanceof WP_Error || false === strpos( $malformed_markup->message, 'malformed' ) ) {
	throw new RuntimeException( 'Malformed translated text markup was not blocked at the Gutenberg write Interface.' );
}

$GLOBALS['invalid_marker_adapter'] = true;
$built_in_markers = mcp_abilities_gutenberg_detect_design_markup_markers( '<!-- wp:columns --><div></div><!-- /wp:columns -->' );
if ( ! in_array( 'core-layout', $built_in_markers, true ) ) {
	throw new RuntimeException( 'Invalid Adapter output discarded built-in guarded design evidence.' );
}
$GLOBALS['invalid_marker_adapter'] = false;

$page = new WP_Post();
$result = mcp_abilities_gutenberg_validate_content_write_policy(
	$page,
	'page',
	'publish',
	'<!-- proposed page -->',
	array(),
	'gutenberg/update-post-blocks'
);
if ( ! $result instanceof WP_Error || 1 !== $GLOBALS['site_write_policy_calls'] ) {
	throw new RuntimeException( 'The Block Document Write Module did not preserve a site Adapter rejection.' );
}

$GLOBALS['site_write_policy_enabled'] = false;
$without_adapter = mcp_abilities_gutenberg_validate_content_write_policy(
	$page,
	'page',
	'publish',
	'<!-- page without site Adapter -->',
	array(),
	'gutenberg/update-post-blocks'
);
if ( true !== $without_adapter || 1 !== $GLOBALS['site_write_policy_calls'] ) {
	throw new RuntimeException( 'The public Block Document Write Module is not neutral without a site Adapter.' );
}

fwrite( STDOUT, "Neutral Block Document Write policy runtime passed.\n" );
