<?php
/** Runtime contract for the provider-neutral design-context hierarchy seam. */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['design_context_response'] = array(
	'stylesheets' => array(
		array(
			'source' => 'fixture-provider',
			'css'    => '.fixture-surface{background:#fff;border:1px solid #ddd;border-radius:12px;padding:24px}.fixture-feature{background:#111;color:#fff;border-radius:12px;padding:24px}',
		),
	),
);

function apply_filters( string $name, $value, ...$args ) {
	unset( $args );
	return 'mcp_block_editor_design_context' === $name ? $GLOBALS['design_context_response'] : $value;
}

function sanitize_html_class( string $value ): string {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) ?? '';
}

function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

function wp_list_pluck( array $items, string $field ): array {
	return array_map(
		static function ( $item ) use ( $field ) {
			return is_array( $item ) ? ( $item[ $field ] ?? null ) : null;
		},
		$items
	);
}

function wp_json_encode( $value ): string {
	return (string) json_encode( $value );
}

function parse_blocks( string $content ): array {
	unset( $content );
	return $GLOBALS['design_fixture_blocks'] ?? array();
}

function mcp_abilities_gutenberg_normalize_blocks( array $blocks ): array {
	return $blocks;
}

function mcp_abilities_gutenberg_content_summary( string $content ): array {
	unset( $content );
	return array(
		'has_blocks'      => true,
		'block_count'     => count( $GLOBALS['design_fixture_blocks'] ?? array() ),
		'word_count'      => 0,
		'character_count' => 0,
		'rendered_html'   => '',
	);
}

function mcp_abilities_gutenberg_validate_content( string $content ): array {
	unset( $content );
	return array(
		'top_level_block_count' => count( $GLOBALS['design_fixture_blocks'] ?? array() ),
		'layout_risks'          => array(
			'issues'           => array(),
			'content_measures' => array(),
		),
		'embedded_css'         => array(),
	);
}

require_once dirname( __DIR__ ) . '/includes/content-analysis.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$heading = static function ( int $level, string $text ): array {
	return array(
		'block_name'    => 'fixture/text',
		'attrs'         => array( 'tagName' => 'h' . $level ),
		'inner_blocks'  => array(),
		'inner_html'    => sprintf( '<h%d>%s</h%d>', $level, $text, $level ),
		'inner_content' => array(),
	);
};

$module = static function ( string $class_name, array $children ): array {
	return array(
		'block_name'    => 'fixture/element',
		'attrs'         => array(
			'tagName'   => 'div',
			'className' => $class_name,
		),
		'inner_blocks'  => $children,
		'inner_html'    => '<div class="' . $class_name . '"></div>',
		'inner_content' => array(),
	);
};

$document = static function ( string $lead_class ) use ( $heading, $module ): array {
	return array(
		$module(
			'fixture-layout',
			array(
				$module( $lead_class, array( $heading( 2, 'Section lead' ) ) ),
				$module( 'fixture-surface', array( $heading( 3, 'First item' ) ) ),
				$module( 'fixture-surface', array( $heading( 3, 'Second item' ) ) ),
			)
		),
	);
};

$context = mcp_abilities_gutenberg_collect_design_context( '', $document( 'fixture-surface' ) );
$assert( 1 === $context['external_stylesheet_count'], 'The external stylesheet Adapter was not visible through the design-context Interface.' );

$red_issues = mcp_abilities_gutenberg_collect_section_lead_peer_treatment_issues(
	$document( 'fixture-surface' ),
	$context['stylesheets']
);
$assert( 1 === count( $red_issues ), 'The repeated peer-card treatment did not produce a red design result.' );
$assert( 'section_lead_peer_treatment_risk' === $red_issues[0]['type'], 'The public issue type changed.' );
$assert( mcp_abilities_gutenberg_is_blocking_design_issue( $red_issues[0]['type'] ), 'The hierarchy issue is not blocking static design acceptance.' );

$GLOBALS['design_fixture_blocks'] = $document( 'fixture-surface' );
$red_evaluation = mcp_abilities_gutenberg_evaluate_design( 'fixture-document' );
$assert( false === $red_evaluation['passes_static_checks'], 'The public design evaluator allowed the red hierarchy fixture.' );
$assert( in_array( 'section_lead_peer_treatment_risk', $red_evaluation['blocking_issue_types'], true ), 'The public design evaluator did not expose the blocking hierarchy issue.' );

$open_issues = mcp_abilities_gutenberg_collect_section_lead_peer_treatment_issues(
	$document( 'fixture-lead' ),
	$context['stylesheets']
);
$assert( array() === $open_issues, 'An open section lead did not produce a green design result.' );

$feature_issues = mcp_abilities_gutenberg_collect_section_lead_peer_treatment_issues(
	$document( 'fixture-feature' ),
	$context['stylesheets']
);
$assert( array() === $feature_issues, 'A visibly differentiated section lead did not produce a green design result.' );

$GLOBALS['design_fixture_blocks'] = $document( 'fixture-lead' );
$green_evaluation = mcp_abilities_gutenberg_evaluate_design( 'fixture-document' );
$assert( true === $green_evaluation['passes_static_checks'], 'The public design evaluator rejected the green hierarchy fixture.' );

$GLOBALS['design_context_response'] = new stdClass();
$embedded_context = mcp_abilities_gutenberg_collect_design_context(
	'',
	array(),
	array(
		array(
			'source' => 'embedded-fixture',
			'css'    => '.embedded{background:#fff}',
		),
	)
);
$assert( 1 === count( $embedded_context['stylesheets'] ), 'Invalid Adapter output removed the block document\'s embedded design evidence.' );
$assert( 0 === $embedded_context['external_stylesheet_count'], 'Invalid Adapter output was counted as external design evidence.' );

echo "Provider-neutral design-context hierarchy checks passed.\n";
