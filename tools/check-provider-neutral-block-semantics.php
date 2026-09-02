<?php
/** Runtime contract for provider-neutral block semantics. */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );

function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

function sanitize_html_class( string $value ): string {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) ?? '';
}

require_once dirname( __DIR__ ) . '/includes/content-analysis.php';

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$blocks = array(
	array(
		'block_name'   => 'provider/layout',
		'attrs'        => array( 'tagName' => 'div' ),
		'inner_html'   => '<div></div>',
		'inner_blocks' => array(
			array(
				'block_name'   => 'provider/text',
				'attrs'        => array( 'tagName' => 'h1' ),
				'inner_html'   => '<h1 class="provider-text">A semantic heading</h1>',
				'inner_blocks' => array(),
			),
			array(
				'block_name'   => 'provider/text',
				'attrs'        => array(
					'tagName'        => 'a',
					'className'      => 'button',
					'globalClasses'  => array( 'service-button--primary' ),
					'htmlAttributes' => array( 'href' => '/contact/?a=1&b=2' ),
				),
				'inner_html'   => '<a class="provider-text service-button--primary button" href="/contact/?a=1&amp;b=2">Contact us</a>',
				'inner_blocks' => array(),
			),
			array(
				'block_name'   => 'provider/media',
				'attrs'        => array(
					'tagName'        => 'img',
					'htmlAttributes' => array(
						'src' => '/image.webp',
						'alt' => 'Useful image',
					),
				),
				'inner_html'   => '<img src="/image.webp" alt="Useful image">',
				'inner_blocks' => array(),
			),
		),
	),
);

$outline = mcp_abilities_gutenberg_collect_outline( $blocks );
$assert( 1 === count( $outline ), 'Provider heading was not collected exactly once.' );
$assert( 1 === $outline[0]['level'] && 'A semantic heading' === $outline[0]['text'], 'Provider heading semantics were changed.' );
$assert( mcp_abilities_gutenberg_has_semantic_action( $blocks ), 'Provider button-style link was not recognized.' );

$links = mcp_abilities_gutenberg_collect_links( $blocks );
$assert( array( '/contact/?a=1&b=2' ) === $links, 'Provider link destination was not normalized exactly once.' );

$media = mcp_abilities_gutenberg_collect_media_refs( $blocks );
$assert( 1 === count( $media ), 'Provider media was not collected exactly once.' );
$assert( '/image.webp' === $media[0]['url'] && 'Useful image' === $media[0]['alt'], 'Provider media semantics were changed.' );

$copy_text = mcp_abilities_gutenberg_copy_plain_text( '<h2>Section heading</h2><p>A short paragraph follows.</p>' );
$assert( 'Section heading A short paragraph follows.' === $copy_text, 'Copy plain text projection joined adjacent block text.' );

echo "Provider-neutral block semantic checks passed.\n";
