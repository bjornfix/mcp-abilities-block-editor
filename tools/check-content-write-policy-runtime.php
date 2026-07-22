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
function apply_filters( string $name, $value, ...$args ) {
	if ( 'mcp_abilities_gutenberg_design_markup_markers' === $name && ! empty( $GLOBALS['invalid_marker_adapter'] ) ) {
		return new WP_Error( 'invalid_adapter_output', 'Fixture invalid marker response.' );
	}
	if ( 'mcp_abilities_gutenberg_content_write_preflight' !== $name || empty( $GLOBALS['site_write_policy_enabled'] ) ) {
		return $value;
	}
	$GLOBALS['site_write_policy_calls']++;
	$context = is_array( $args[0] ?? null ) ? $args[0] : array();
	if ( 'page' !== (string) ( $context['post_type'] ?? '' ) || 'gutenberg/update-post-blocks' !== (string) ( $context['ability'] ?? '' ) ) {
		throw new RuntimeException( 'The neutral Block Document Write context lost caller intent.' );
	}
	return new WP_Error( 'site_policy_rejected', 'Rejected by fixture site policy.' );
}

require_once dirname( __DIR__ ) . '/includes/content-analysis.php';

$GLOBALS['invalid_marker_adapter'] = true;
$built_in_markers = mcp_abilities_gutenberg_detect_design_markup_markers( '<!-- wp:generateblocks/container --><div></div><!-- /wp:generateblocks/container -->' );
if ( ! in_array( 'generateblocks', $built_in_markers, true ) ) {
	throw new RuntimeException( 'Invalid Adapter output discarded built-in guarded design evidence.' );
}
$GLOBALS['invalid_marker_adapter'] = false;

$page = new WP_Post();
$result = mcp_abilities_gutenberg_validate_content_write_policy(
	$page,
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
	'publish',
	'<!-- page without site Adapter -->',
	array(),
	'gutenberg/update-post-blocks'
);
if ( true !== $without_adapter || 1 !== $GLOBALS['site_write_policy_calls'] ) {
	throw new RuntimeException( 'The public Block Document Write Module is not neutral without a site Adapter.' );
}

fwrite( STDOUT, "Neutral Block Document Write policy runtime passed.\n" );
