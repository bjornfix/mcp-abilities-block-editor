<?php
/**
 * Plugin Name: MCP Abilities - Block Editor
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-block-editor
 * Description: WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.
 * Version: 0.2.0
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.9
 * Requires PHP: 8.0
 *
 * @package MCP_Abilities_Block_Editor
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Abilities API is available.
 */
function mcp_abilities_gutenberg_check_dependencies(): bool {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p><strong>MCP Abilities - Block Editor</strong> requires the <a href="https://github.com/WordPress/abilities-api">Abilities API</a> plugin to be installed and activated.</p></div>';
			}
		);
		return false;
	}

	return true;
}

/**
 * Permission callback for Gutenberg abilities.
 */
function mcp_abilities_gutenberg_permission_callback(): bool {
	return current_user_can( 'edit_posts' );
}

/**
 * Register the plugin's ability category.
 */
function mcp_abilities_gutenberg_register_category(): void {
	$args = array(
		'label'       => 'Block Editor',
		'description' => 'Abilities for Gutenberg and block-editor authoring workflows.',
	);

	if ( doing_action( 'wp_abilities_api_categories_init' ) ) {
		wp_register_ability_category( 'block-editor', $args );
		return;
	}

	$registry = class_exists( 'WP_Ability_Categories_Registry' ) ? WP_Ability_Categories_Registry::get_instance() : null;
	if ( $registry && ! $registry->is_registered( 'block-editor' ) ) {
		$registry->register( 'block-editor', $args );
	}
}

/**
 * Register an ability safely even if the registry was initialized before this plugin loaded.
 *
 * @param string               $name Ability name.
 * @param array<string,mixed>  $args Ability args.
 * @return void
 */
function mcp_abilities_gutenberg_register_ability( string $name, array $args ): void {
	if ( doing_action( 'wp_abilities_api_init' ) ) {
		wp_register_ability( $name, $args );
		return;
	}

	$registry = class_exists( 'WP_Abilities_Registry' ) ? WP_Abilities_Registry::get_instance() : null;
	if ( $registry && ! $registry->is_registered( $name ) ) {
		$registry->register( $name, $args );
	}
}

/**
 * Normalize a Gutenberg parsed block tree for MCP output.
 *
 * @param array $block Parsed block from `parse_blocks()`.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_normalize_block( array $block ): array {
	$normalized_inner_blocks = array();
	foreach ( $block['innerBlocks'] ?? array() as $inner_block ) {
		if ( is_array( $inner_block ) ) {
			$normalized_inner_blocks[] = mcp_abilities_gutenberg_normalize_block( $inner_block );
		}
	}

	$normalized_inner_content = array();
	foreach ( $block['innerContent'] ?? array() as $item ) {
		$normalized_inner_content[] = is_null( $item ) ? null : (string) $item;
	}

	return array(
		'block_name'    => isset( $block['blockName'] ) ? (string) $block['blockName'] : '',
		'attrs'         => isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array(),
		'inner_blocks'  => $normalized_inner_blocks,
		'inner_html'    => isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '',
		'inner_content' => $normalized_inner_content,
	);
}

/**
 * Normalize a list of blocks for MCP output.
 *
 * @param array $blocks Parsed blocks.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_normalize_blocks( array $blocks ): array {
	$normalized = array();

	foreach ( $blocks as $block ) {
		if ( is_array( $block ) ) {
			$normalized[] = mcp_abilities_gutenberg_normalize_block( $block );
		}
	}

	return $normalized;
}

/**
 * Convert a normalized MCP block tree back into WordPress block format.
 *
 * @param array $block Input block.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_denormalize_block( array $block ): array {
	$block_name = '';
	if ( isset( $block['block_name'] ) && is_string( $block['block_name'] ) ) {
		$block_name = $block['block_name'];
	} elseif ( isset( $block['blockName'] ) && is_string( $block['blockName'] ) ) {
		$block_name = $block['blockName'];
	}

	$attrs = array();
	if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
		$attrs = $block['attrs'];
	}

	$inner_html = '';
	if ( isset( $block['inner_html'] ) && is_string( $block['inner_html'] ) ) {
		$inner_html = $block['inner_html'];
	} elseif ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
		$inner_html = $block['innerHTML'];
	}

	$inner_blocks_input = array();
	if ( isset( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
		$inner_blocks_input = $block['inner_blocks'];
	} elseif ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
		$inner_blocks_input = $block['innerBlocks'];
	}

	$inner_blocks = array();
	foreach ( $inner_blocks_input as $inner_block ) {
		if ( is_array( $inner_block ) ) {
			$inner_blocks[] = mcp_abilities_gutenberg_denormalize_block( $inner_block );
		}
	}

	$inner_content_input = array();
	if ( isset( $block['inner_content'] ) && is_array( $block['inner_content'] ) ) {
		$inner_content_input = $block['inner_content'];
	} elseif ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
		$inner_content_input = $block['innerContent'];
	}

	$inner_content = array();
	foreach ( $inner_content_input as $item ) {
		$inner_content[] = is_null( $item ) ? null : (string) $item;
	}

	return array(
		'blockName'    => '' !== $block_name ? $block_name : null,
		'attrs'        => $attrs,
		'innerBlocks'  => $inner_blocks,
		'innerHTML'    => $inner_html,
		'innerContent' => $inner_content,
	);
}

/**
 * Convert a list of blocks into WordPress block format.
 *
 * @param mixed $blocks Input blocks.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function mcp_abilities_gutenberg_denormalize_blocks( $blocks ) {
	if ( ! is_array( $blocks ) ) {
		return new WP_Error( 'mcp_gutenberg_invalid_blocks', 'blocks must be an array.' );
	}

	$normalized = array();
	foreach ( $blocks as $index => $block ) {
		if ( ! is_array( $block ) ) {
			return new WP_Error( 'mcp_gutenberg_invalid_block', sprintf( 'Block at index %d must be an object/array.', (int) $index ) );
		}

		$normalized[] = mcp_abilities_gutenberg_denormalize_block( $block );
	}

	return $normalized;
}

/**
 * Build a compact content summary.
 *
 * @param string $content Post content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_content_summary( string $content ): array {
	$parsed_blocks = parse_blocks( $content );
	$rendered      = do_blocks( $content );

	return array(
		'has_blocks'      => has_blocks( $content ),
		'block_count'     => count( $parsed_blocks ),
		'word_count'      => str_word_count( wp_strip_all_tags( $content ) ),
		'character_count' => mb_strlen( wp_strip_all_tags( $content ) ),
		'rendered_html'   => $rendered,
	);
}

/**
 * Get block guidance for common content scenarios.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_block_guidance_catalog(): array {
	return array(
		array(
			'scenario'     => 'Plain body copy or a standard text section',
			'best_block'   => 'core/paragraph',
			'alternatives' => array( 'core/heading', 'core/list' ),
			'use_when'     => 'Use for normal prose, introductions, supporting details, and most article text.',
			'avoid_when'   => 'Avoid when the content is actually a heading, list, quote, callout, or button.',
			'notes'        => 'Paragraph should be the default text block. Do not fake headings with bold paragraphs.',
		),
		array(
			'scenario'     => 'Section title or content hierarchy',
			'best_block'   => 'core/heading',
			'alternatives' => array( 'core/paragraph' ),
			'use_when'     => 'Use for titles and subheadings that define page structure.',
			'avoid_when'   => 'Avoid for decorative large text that is not actually a heading in the document outline.',
			'notes'        => 'Pick the heading level based on hierarchy, not visual size alone.',
		),
		array(
			'scenario'     => 'Steps, checklists, feature lists, or grouped bullets',
			'best_block'   => 'core/list',
			'alternatives' => array( 'core/paragraph', 'core/columns' ),
			'use_when'     => 'Use when items are parallel, sequential, or scan-oriented.',
			'avoid_when'   => 'Avoid when each item needs rich nested layout; use columns or groups then.',
			'notes'        => 'Prefer ordered lists for steps and unordered lists for collections.',
		),
		array(
			'scenario'     => 'Standalone quote, testimonial, or cited statement',
			'best_block'   => 'core/quote',
			'alternatives' => array( 'core/pullquote', 'core/paragraph' ),
			'use_when'     => 'Use when the quoted wording itself matters and should be semantically distinct.',
			'avoid_when'   => 'Avoid for normal emphasis or marketing copy that is not a real quotation.',
			'notes'        => 'Use pullquote only when the visual emphasis is intentional and the theme supports it well.',
		),
		array(
			'scenario'     => 'Primary call to action',
			'best_block'   => 'core/buttons',
			'alternatives' => array( 'core/button' ),
			'use_when'     => 'Use for actions like contact, buy, book, download, or sign up.',
			'avoid_when'   => 'Avoid embedding button-like links inside paragraph text for primary actions.',
			'notes'        => 'Use the buttons container even for one button so layout stays extensible.',
		),
		array(
			'scenario'     => 'Image with optional caption',
			'best_block'   => 'core/image',
			'alternatives' => array( 'core/gallery', 'core/media-text' ),
			'use_when'     => 'Use for a single visual asset that should stand on its own.',
			'avoid_when'   => 'Avoid when the image is tightly paired with explanatory text; use media-text then.',
			'notes'        => 'Prefer captions for credit or essential explanatory context, not duplicate alt text.',
		),
		array(
			'scenario'     => 'Image and text side by side',
			'best_block'   => 'core/media-text',
			'alternatives' => array( 'core/columns', 'core/image', 'core/paragraph' ),
			'use_when'     => 'Use when one media item and one text region belong together as a single unit.',
			'avoid_when'   => 'Avoid when the layout needs more than two content regions or complex nesting.',
			'notes'        => 'Media-text is usually cleaner than manually balancing image and paragraph blocks in columns.',
		),
		array(
			'scenario'     => 'Multi-column comparison or side-by-side sections',
			'best_block'   => 'core/columns',
			'alternatives' => array( 'core/group', 'core/media-text' ),
			'use_when'     => 'Use for comparisons, paired offers, or short parallel sections.',
			'avoid_when'   => 'Avoid very dense or deeply nested content that collapses poorly on mobile.',
			'notes'        => 'Keep column content balanced and stack-friendly.',
		),
		array(
			'scenario'     => 'Grouped section with shared background, padding, or layout wrapper',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/columns', 'core/cover' ),
			'use_when'     => 'Use when several blocks belong together semantically or visually.',
			'avoid_when'   => 'Avoid adding groups with no styling or structural purpose.',
			'notes'        => 'Group is the safe general-purpose wrapper block.',
		),
		array(
			'scenario'     => 'Hero section or text over a visual background',
			'best_block'   => 'core/cover',
			'alternatives' => array( 'core/group', 'core/image' ),
			'use_when'     => 'Use for banners, hero areas, and visually led intro sections.',
			'avoid_when'   => 'Avoid for long-form text blocks where readability depends on complex overlays.',
			'notes'        => 'Check contrast carefully; cover blocks are easy to make unreadable.',
		),
		array(
			'scenario'     => 'Separated pieces of content or layout rhythm',
			'best_block'   => 'core/separator',
			'alternatives' => array( 'core/spacer' ),
			'use_when'     => 'Use when you need a visible section break.',
			'avoid_when'   => 'Avoid using separators just to add empty space.',
			'notes'        => 'Use spacer for whitespace and separator for actual visual division.',
		),
		array(
			'scenario'     => 'Vertical whitespace only',
			'best_block'   => 'core/spacer',
			'alternatives' => array( 'core/group' ),
			'use_when'     => 'Use for intentional spacing between sections where theme spacing is insufficient.',
			'avoid_when'   => 'Avoid stacking many spacers instead of fixing the surrounding layout.',
			'notes'        => 'Use sparingly; excess spacers usually signal layout problems elsewhere.',
		),
		array(
			'scenario'     => 'Reusable structured data such as FAQ, how-to, or custom plugin output',
			'best_block'   => 'Use the plugin or custom block that owns the feature',
			'alternatives' => array( 'core/group', 'core/paragraph' ),
			'use_when'     => 'Use when semantics or rendering rely on a specific block from a plugin or theme.',
			'avoid_when'   => 'Avoid recreating complex plugin output with plain paragraphs and headings.',
			'notes'        => 'If a feature has its own block, prefer that over manual imitation.',
		),
	);
}

/**
 * Filter block guidance entries by a loose scenario match.
 *
 * @param string $scenario Free-form scenario string.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_find_block_guidance( string $scenario ): array {
	$scenario = strtolower( trim( $scenario ) );
	$catalog  = mcp_abilities_gutenberg_block_guidance_catalog();

	if ( '' === $scenario ) {
		return $catalog;
	}

	$matches = array();
	foreach ( $catalog as $entry ) {
		$haystack = strtolower(
			implode(
				' ',
				array(
					(string) ( $entry['scenario'] ?? '' ),
					(string) ( $entry['best_block'] ?? '' ),
					(string) ( $entry['use_when'] ?? '' ),
					(string) ( $entry['avoid_when'] ?? '' ),
					(string) ( $entry['notes'] ?? '' ),
					implode( ' ', array_map( 'strval', $entry['alternatives'] ?? array() ) ),
				)
			)
		);

		if ( false !== strpos( $haystack, $scenario ) ) {
			$matches[] = $entry;
		}
	}

	return ! empty( $matches ) ? $matches : $catalog;
}

/**
 * Get an editable post by ID.
 *
 * @param int $post_id Post ID.
 * @return WP_Post|WP_Error
 */
function mcp_abilities_gutenberg_get_editable_post( int $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'mcp_gutenberg_post_not_found', 'Post not found.' );
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'mcp_gutenberg_forbidden', 'You do not have permission to edit this post.' );
	}

	return $post;
}

/**
 * Return theme context relevant to block authoring.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_theme_context(): array {
	$theme = wp_get_theme();

	return array(
		'name'           => $theme->get( 'Name' ),
		'stylesheet'     => $theme->get_stylesheet(),
		'template'       => $theme->get_template(),
		'version'        => $theme->get( 'Version' ),
		'is_block_theme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : false,
	);
}

/**
 * Return theme style presets and global style context.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_style_guide(): array {
	$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
	$styles   = function_exists( 'wp_get_global_styles' ) ? wp_get_global_styles() : array();

	return array(
		'palette'      => $settings['color']['palette']['theme'] ?? array(),
		'gradients'    => $settings['color']['gradients']['theme'] ?? array(),
		'font_sizes'   => $settings['typography']['fontSizes']['theme'] ?? array(),
		'font_families'=> $settings['typography']['fontFamilies']['theme'] ?? array(),
		'spacing_sizes'=> $settings['spacing']['spacingSizes']['theme'] ?? array(),
		'layout'       => $settings['layout'] ?? array(),
		'styles'       => $styles,
	);
}

/**
 * List block types with a compact shape.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_block_catalog(): array {
	$registry = WP_Block_Type_Registry::get_instance();
	$types    = $registry->get_all_registered();
	$catalog  = array();

	foreach ( $types as $block_type ) {
		$catalog[] = array(
			'name'            => (string) $block_type->name,
			'title'           => (string) $block_type->title,
			'description'     => (string) $block_type->description,
			'category'        => is_string( $block_type->category ) ? $block_type->category : '',
			'parent'          => is_array( $block_type->parent ) ? array_values( $block_type->parent ) : array(),
			'styles'          => is_array( $block_type->styles ) ? $block_type->styles : array(),
			'example'         => is_array( $block_type->example ) ? $block_type->example : array(),
			'supports'        => is_array( $block_type->supports ) ? $block_type->supports : array(),
			'keywords'        => is_array( $block_type->keywords ) ? array_values( $block_type->keywords ) : array(),
			'uses_context'    => is_array( $block_type->uses_context ) ? array_values( $block_type->uses_context ) : array(),
			'provides_context'=> is_array( $block_type->provides_context ) ? $block_type->provides_context : array(),
		);
	}

	usort(
		$catalog,
		static function ( array $a, array $b ): int {
			return strcmp( $a['name'], $b['name'] );
		}
	);

	return $catalog;
}

/**
 * List registered block patterns.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_pattern_catalog(): array {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return array();
	}

	$registry = WP_Block_Patterns_Registry::get_instance();
	$patterns = $registry->get_all_registered();
	$catalog  = array();

	foreach ( $patterns as $name => $pattern ) {
		$catalog[] = array(
			'name'         => (string) $name,
			'title'        => (string) ( $pattern['title'] ?? '' ),
			'description'  => (string) ( $pattern['description'] ?? '' ),
			'categories'   => array_values( is_array( $pattern['categories'] ?? null ) ? $pattern['categories'] : array() ),
			'keywords'     => array_values( is_array( $pattern['keywords'] ?? null ) ? $pattern['keywords'] : array() ),
			'block_types'  => array_values( is_array( $pattern['blockTypes'] ?? null ) ? $pattern['blockTypes'] : array() ),
			'post_types'   => array_values( is_array( $pattern['postTypes'] ?? null ) ? $pattern['postTypes'] : array() ),
			'inserter'     => (bool) ( $pattern['inserter'] ?? true ),
		);
	}

	usort(
		$catalog,
		static function ( array $a, array $b ): int {
			return strcmp( $a['name'], $b['name'] );
		}
	);

	return $catalog;
}

/**
 * Build validation results for block content.
 *
 * @param string $content Content to validate.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_validate_content( string $content ): array {
	$parsed_blocks       = parse_blocks( $content );
	$normalized          = mcp_abilities_gutenberg_normalize_blocks( $parsed_blocks );
	$roundtrip_content   = serialize_blocks( $parsed_blocks );
	$roundtrip_normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $roundtrip_content ) );

	$top_level_names = array();
	foreach ( $normalized as $block ) {
		$name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
		if ( '' !== $name ) {
			$top_level_names[] = $name;
		}
	}

	$warnings = array();
	if ( empty( $normalized ) ) {
		$warnings[] = 'Content contains no Gutenberg blocks.';
	}
	if ( ! in_array( 'core/heading', $top_level_names, true ) ) {
		$warnings[] = 'No top-level heading block found.';
	}
	if ( ! in_array( 'core/buttons', $top_level_names, true ) ) {
		$warnings[] = 'No top-level buttons block found.';
	}
	if ( count( $normalized ) < 3 ) {
		$warnings[] = 'Very few top-level blocks; page structure may be too shallow for a landing page.';
	}

	return array(
		'summary'                 => mcp_abilities_gutenberg_content_summary( $content ),
		'roundtrip_equal'         => $normalized === $roundtrip_normalized,
		'top_level_block_names'   => $top_level_names,
		'top_level_block_count'   => count( $normalized ),
		'warnings'                => $warnings,
	);
}

/**
 * Return reusable page recipes.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_page_recipes(): array {
	return array(
		array(
			'slug'        => 'landing-page',
			'label'       => 'Landing Page',
			'description' => 'A conversion-focused one-page structure with hero, proof, offer, details, and CTA.',
			'sections'    => array(
				'hero',
				'offer-grid',
				'why-choose-us',
				'process-or-story',
				'testimonial',
				'final-cta',
			),
		),
		array(
			'slug'        => 'service-page',
			'label'       => 'Service Page',
			'description' => 'A clear service overview with scope, benefits, process, FAQ, and CTA.',
			'sections'    => array(
				'hero',
				'service-summary',
				'benefits',
				'process',
				'faq',
				'final-cta',
			),
		),
		array(
			'slug'        => 'menu-or-product-page',
			'label'       => 'Menu or Product Page',
			'description' => 'A structured layout for food, drink, retail, or product collections.',
			'sections'    => array(
				'hero',
				'featured-items',
				'details',
				'testimonial',
				'visit-or-order',
			),
		),
	);
}

/**
 * Build a compact landing page blueprint.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_build_landing_page_blueprint( array $input ): array {
	$business_name = isset( $input['business_name'] ) ? (string) $input['business_name'] : 'Local Business';
	$industry      = isset( $input['industry'] ) ? (string) $input['industry'] : 'business';
	$tone          = isset( $input['tone'] ) ? (string) $input['tone'] : 'warm, premium, and confident';
	$cta_primary   = isset( $input['primary_cta_text'] ) ? (string) $input['primary_cta_text'] : 'Order Today';
	$cta_secondary = isset( $input['secondary_cta_text'] ) ? (string) $input['secondary_cta_text'] : 'View Menu';

	return array(
		'business_name' => $business_name,
		'industry'      => $industry,
		'tone'          => $tone,
		'sections'      => array(
			array(
				'slug'              => 'hero',
				'label'             => 'Hero',
				'goal'              => 'Create immediate brand recognition and a clear value proposition.',
				'recommended_block' => 'core/group',
				'supporting_blocks' => array( 'core/heading', 'core/paragraph', 'core/buttons' ),
				'cta'               => array( $cta_primary, $cta_secondary ),
			),
			array(
				'slug'              => 'offer-grid',
				'label'             => 'Offer Grid',
				'goal'              => 'Show signature products or categories at a glance.',
				'recommended_block' => 'core/columns',
				'supporting_blocks' => array( 'core/group', 'core/heading', 'core/paragraph' ),
			),
			array(
				'slug'              => 'why-choose-us',
				'label'             => 'Why Choose Us',
				'goal'              => 'Explain what makes the brand feel distinctive and trustworthy.',
				'recommended_block' => 'core/group',
				'supporting_blocks' => array( 'core/heading', 'core/list', 'core/paragraph' ),
			),
			array(
				'slug'              => 'process-or-story',
				'label'             => 'Process or Story',
				'goal'              => 'Add personality and concrete proof behind the offer.',
				'recommended_block' => 'core/columns',
				'supporting_blocks' => array( 'core/heading', 'core/paragraph', 'core/list' ),
			),
			array(
				'slug'              => 'testimonial',
				'label'             => 'Testimonial',
				'goal'              => 'Add social proof in a distinct visual block.',
				'recommended_block' => 'core/quote',
				'supporting_blocks' => array( 'core/group' ),
			),
			array(
				'slug'              => 'final-cta',
				'label'             => 'Final CTA',
				'goal'              => 'Close the page with a clear action.',
				'recommended_block' => 'core/group',
				'supporting_blocks' => array( 'core/heading', 'core/paragraph', 'core/buttons' ),
			),
		),
	);
}

/**
 * Build a heading block string.
 *
 * @param string $content Heading content.
 * @param int    $level Heading level.
 * @param array  $attrs Additional attributes.
 * @return string
 */
function mcp_abilities_gutenberg_heading_block( string $content, int $level = 2, array $attrs = array() ): string {
	$attrs = array_merge( array( 'level' => $level ), $attrs );
	$json  = wp_json_encode( $attrs );
	return '<!-- wp:heading ' . $json . ' --><h' . $level . ' class="wp-block-heading">' . esc_html( $content ) . '</h' . $level . '><!-- /wp:heading -->';
}

/**
 * Build a paragraph block string.
 *
 * @param string $content Paragraph content.
 * @param array  $attrs Additional attributes.
 * @return string
 */
function mcp_abilities_gutenberg_paragraph_block( string $content, array $attrs = array() ): string {
	$json = ! empty( $attrs ) ? ' ' . wp_json_encode( $attrs ) : '';
	return '<!-- wp:paragraph' . $json . ' --><p>' . wp_kses_post( $content ) . '</p><!-- /wp:paragraph -->';
}

/**
 * Generate landing page block content.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_generate_landing_page_payload( array $input ): array {
	$business_name = isset( $input['business_name'] ) ? trim( (string) $input['business_name'] ) : 'Local Business';
	$industry      = isset( $input['industry'] ) ? trim( (string) $input['industry'] ) : 'business';
	$tone          = isset( $input['tone'] ) ? trim( (string) $input['tone'] ) : 'warm, premium, and confident';
	$slug          = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $business_name );
	$primary_cta   = isset( $input['primary_cta_text'] ) ? trim( (string) $input['primary_cta_text'] ) : 'Order Today';
	$secondary_cta = isset( $input['secondary_cta_text'] ) ? trim( (string) $input['secondary_cta_text'] ) : 'View Menu';
	$offerings     = isset( $input['offerings'] ) && is_array( $input['offerings'] ) ? array_values( array_filter( array_map( 'strval', $input['offerings'] ) ) ) : array(
		'Slow-fermented sourdough',
		'Butter-rich laminated pastries',
		'Seasonal cakes and weekend treats',
	);

	$blueprint = mcp_abilities_gutenberg_build_landing_page_blueprint(
		array(
			'business_name'       => $business_name,
			'industry'            => $industry,
			'tone'                => $tone,
			'primary_cta_text'    => $primary_cta,
			'secondary_cta_text'  => $secondary_cta,
		)
	);

	$offer_columns = '';
	foreach ( array_slice( $offerings, 0, 3 ) as $offering ) {
		$offer_columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem","padding":{"top":"1.5rem","right":"1.5rem","bottom":"1.5rem","left":"1.5rem"}},"border":{"radius":"18px"}},"backgroundColor":"base-2","layout":{"type":"constrained"}} --><div class="wp-block-group has-base-2-background-color has-background" style="border-radius:18px;padding-top:1.5rem;padding-right:1.5rem;padding-bottom:1.5rem;padding-left:1.5rem">'
			. mcp_abilities_gutenberg_heading_block( $offering, 3 )
			. mcp_abilities_gutenberg_paragraph_block( 'Made in small batches with a focus on texture, aroma, and a clean finish.' )
			. '</div><!-- /wp:group --></div><!-- /wp:column -->';
	}

	$content  = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem","left":"min(6vw,4rem)","right":"min(6vw,4rem)"},"blockGap":"1.5rem"},"color":{"gradient":"linear-gradient(135deg,rgb(24,18,16) 0%,rgb(70,46,33) 42%,rgb(198,150,93) 100%)"}},"textColor":"base","layout":{"type":"constrained"}} --><div class="wp-block-group has-base-color has-text-color has-background" style="background:linear-gradient(135deg,rgb(24,18,16) 0%,rgb(70,46,33) 42%,rgb(198,150,93) 100%);padding-top:5rem;padding-right:min(6vw,4rem);padding-bottom:5rem;padding-left:min(6vw,4rem)">'
		. mcp_abilities_gutenberg_paragraph_block( 'Bakes worth planning your morning around', array( 'fontSize' => 'small' ) )
		. mcp_abilities_gutenberg_heading_block( $business_name, 1, array( 'style' => array( 'typography' => array( 'fontSize' => 'clamp(3rem,8vw,6.5rem)', 'lineHeight' => '0.95' ) ) ) )
		. mcp_abilities_gutenberg_paragraph_block( esc_html( $business_name ) . ' is a ' . esc_html( $tone ) . ' ' . esc_html( $industry ) . ' brand built around early ovens, crisp crusts, and pastries that feel like an event, not an afterthought.', array( 'style' => array( 'typography' => array( 'fontSize' => '1.15rem' ) ) ) )
		. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"}} --><div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"base","textColor":"contrast","style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-base-background-color has-text-color has-background wp-element-button" style="border-radius:999px">' . esc_html( $primary_cta ) . '</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline","style":{"border":{"radius":"999px"}}} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $secondary_cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1.5rem","padding":{"left":"min(6vw,4rem)","right":"min(6vw,4rem)"}}}} --><div class="wp-block-group" style="padding-right:min(6vw,4rem);padding-left:min(6vw,4rem)">'
		. mcp_abilities_gutenberg_heading_block( 'What to come in for first', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'A sleek landing page still needs fast scanning. This section keeps the offer concrete before the copy gets romantic.' )
		. '<!-- wp:columns {"style":{"spacing":{"blockGap":"1.25rem"}}} --><div class="wp-block-columns">' . $offer_columns . '</div><!-- /wp:columns -->'
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"56px"} --><div style="height:56px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:group {"backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"3rem","right":"min(6vw,4rem)","bottom":"3rem","left":"min(6vw,4rem)"},"blockGap":"1.25rem"}},"layout":{"type":"constrained"}} --><div class="wp-block-group has-base-2-background-color has-background" style="padding-top:3rem;padding-right:min(6vw,4rem);padding-bottom:3rem;padding-left:min(6vw,4rem)">'
		. mcp_abilities_gutenberg_heading_block( 'Why people remember ' . $business_name, 2 )
		. '<!-- wp:list --><ul><li>Small-batch baking that feels deliberate instead of industrial.</li><li>A product mix built for daily bread, weekend pastries, and impulse dessert runs.</li><li>Warm visual identity and copy that can flex between neighborhood charm and premium positioning.</li></ul><!-- /wp:list -->'
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"56px"} --><div style="height:56px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:columns {"style":{"spacing":{"blockGap":"2rem","padding":{"left":"min(6vw,4rem)","right":"min(6vw,4rem)"}}} --><div class="wp-block-columns" style="padding-right:min(6vw,4rem);padding-left:min(6vw,4rem)"><!-- wp:column --><div class="wp-block-column">'
		. mcp_abilities_gutenberg_heading_block( 'Baked before sunrise', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'This section gives the brand a pulse. It is where the page stops sounding generic and starts sounding lived-in.' )
		. '</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:list {"ordered":true} --><ol><li>Mix and proof dough slowly for flavor first.</li><li>Bake in tight daily batches so the page promise stays believable.</li><li>Present the counter like a curated collection, not a pile of products.</li></ol><!-- /wp:list --></div><!-- /wp:column --></div><!-- /wp:columns -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:quote {"className":"is-style-large","style":{"spacing":{"padding":{"left":"min(6vw,4rem)","right":"min(6vw,4rem)"}}}} --><blockquote class="wp-block-quote is-style-large" style="padding-right:min(6vw,4rem);padding-left:min(6vw,4rem)"><p>The kind of bakery that makes one loaf feel like a plan for the whole day.</p><cite>Suggested testimonial placeholder</cite></blockquote><!-- /wp:quote -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:separator {"backgroundColor":"contrast-3"} --><hr class="wp-block-separator has-text-color has-contrast-3-color has-alpha-channel-opacity has-contrast-3-background-color has-background"/><!-- /wp:separator -->';
	$content .= '<!-- wp:group {"style":{"spacing":{"padding":{"top":"3rem","bottom":"3rem","left":"min(6vw,4rem)","right":"min(6vw,4rem)"},"blockGap":"1rem"}},"layout":{"type":"constrained"}} --><div class="wp-block-group" style="padding-top:3rem;padding-right:min(6vw,4rem);padding-bottom:3rem;padding-left:min(6vw,4rem)">'
		. mcp_abilities_gutenberg_heading_block( 'Ready to make ' . $business_name . ' the stop people talk about?', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'Use this final call to action for ordering, preorders, catering requests, or location details once the operational pieces are confirmed.' )
		. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"}} --><div class="wp-block-buttons"><!-- wp:button {"style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $primary_cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '</div><!-- /wp:group -->';

	$title = isset( $input['title'] ) && '' !== trim( (string) $input['title'] ) ? trim( (string) $input['title'] ) : $business_name;

	return array(
		'title'     => $title,
		'slug'      => $slug,
		'content'   => $content,
		'blocks'    => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
		'summary'   => mcp_abilities_gutenberg_content_summary( $content ),
		'blueprint' => $blueprint,
	);
}

/**
 * Create a page from content or blocks.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_create_page_from_input( array $input ): array {
	$content = null;
	if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
		$content = $input['content'];
	} elseif ( isset( $input['blocks'] ) ) {
		$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] );
		if ( is_wp_error( $blocks ) ) {
			return array(
				'success' => false,
				'message' => $blocks->get_error_message(),
			);
		}

		$content = serialize_blocks( $blocks );
	}

	if ( null === $content ) {
		return array(
			'success' => false,
			'message' => 'Provide either content or blocks.',
		);
	}

	$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : 'Untitled Page';
	$slug  = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $title );
	$status = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'draft';

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => $status,
				'post_content' => $content,
			)
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return array(
			'success' => false,
			'message' => $post_id->get_error_message(),
		);
	}

	$post = get_post( (int) $post_id );

	return array(
		'success' => true,
		'message' => 'Page created successfully.',
		'post'    => array(
			'id'       => (int) $post_id,
			'type'     => $post ? (string) $post->post_type : 'page',
			'status'   => $post ? (string) $post->post_status : $status,
			'slug'     => $post ? (string) $post->post_name : $slug,
			'title'    => $post ? get_the_title( $post ) : $title,
			'url'      => get_permalink( (int) $post_id ),
			'modified' => $post ? (string) $post->post_modified_gmt : '',
		),
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Register Gutenberg abilities.
 */
function mcp_abilities_gutenberg_register_abilities(): void {
	if ( ! mcp_abilities_gutenberg_check_dependencies() ) {
		return;
	}

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/get-theme-context',
		array(
			'label'               => 'Get Block Theme Context',
			'description'         => 'Return theme details relevant to block-editor authoring.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'theme'   => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'theme'   => mcp_abilities_gutenberg_get_theme_context(),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/get-style-guide',
		array(
			'label'               => 'Get Block Style Guide',
			'description'         => 'Return theme palette, gradients, typography, spacing, and global styles for block authoring.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'style_guide' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success'     => true,
					'style_guide' => mcp_abilities_gutenberg_get_style_guide(),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/list-available-blocks',
		array(
			'label'               => 'List Available Blocks',
			'description'         => 'Return the registered block types that can be used on the site.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'search' => array(
						'type'        => 'string',
						'description' => 'Optional search term for block names or titles.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'count'   => array( 'type' => 'integer' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$search = isset( $input['search'] ) ? strtolower( trim( (string) $input['search'] ) ) : '';
				$blocks = mcp_abilities_gutenberg_get_block_catalog();

				if ( '' !== $search ) {
					$blocks = array_values(
						array_filter(
							$blocks,
							static function ( array $block ) use ( $search ): bool {
								$haystack = strtolower( implode( ' ', array( (string) $block['name'], (string) $block['title'], (string) $block['description'] ) ) );
								return false !== strpos( $haystack, $search );
							}
						)
					);
				}

				return array(
					'success' => true,
					'count'   => count( $blocks ),
					'blocks'  => $blocks,
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/list-patterns',
		array(
			'label'               => 'List Block Patterns',
			'description'         => 'Return registered block patterns available on the site.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'count'    => array( 'type' => 'integer' ),
					'patterns' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				$patterns = mcp_abilities_gutenberg_get_pattern_catalog();
				return array(
					'success'  => true,
					'count'    => count( $patterns ),
					'patterns' => $patterns,
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/block-guidance',
		array(
			'label'               => 'Get Gutenberg Block Guidance',
			'description'         => 'Recommend which Gutenberg block to use for common content scenarios.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'scenario' => array(
						'type'        => 'string',
						'description' => 'Optional free-form scenario such as hero, CTA, quote, comparison, or body copy.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'         => array( 'type' => 'boolean' ),
					'message'         => array( 'type' => 'string' ),
					'scenario'        => array( 'type' => 'string' ),
					'matched_entries' => array( 'type' => 'integer' ),
					'guidance'        => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$scenario = isset( $input['scenario'] ) ? (string) $input['scenario'] : '';
				$guidance = mcp_abilities_gutenberg_find_block_guidance( $scenario );

				return array(
					'success'         => true,
					'message'         => '' !== trim( $scenario ) ? 'Matching block guidance returned.' : 'Full block guidance catalog returned.',
					'scenario'        => $scenario,
					'matched_entries' => count( $guidance ),
					'guidance'        => $guidance,
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/get-page-recipes',
		array(
			'label'               => 'Get Page Recipes',
			'description'         => 'Return reusable page structure recipes for landing pages and similar layouts.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'recipes' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'recipes' => mcp_abilities_gutenberg_get_page_recipes(),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/generate-landing-page',
		array(
			'label'               => 'Generate Landing Page Blocks',
			'description'         => 'Generate a landing-page blueprint plus ready-to-save Gutenberg content for a business.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'business_name' ),
				'properties' => array(
					'business_name' => array(
						'type'        => 'string',
						'description' => 'Business name.',
					),
					'industry' => array(
						'type'        => 'string',
						'description' => 'Industry or business type.',
					),
					'tone' => array(
						'type'        => 'string',
						'description' => 'Desired tone for the page copy.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Optional page title override.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional page slug.',
					),
					'primary_cta_text' => array(
						'type'        => 'string',
						'description' => 'Primary button label.',
					),
					'secondary_cta_text' => array(
						'type'        => 'string',
						'description' => 'Secondary button label.',
					),
					'offerings' => array(
						'type'        => 'array',
						'description' => 'Optional list of featured offerings.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'title'     => array( 'type' => 'string' ),
					'slug'      => array( 'type' => 'string' ),
					'blueprint' => array( 'type' => 'object' ),
					'content'   => array( 'type' => 'string' ),
					'summary'   => array( 'type' => 'object' ),
					'blocks'    => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$payload = mcp_abilities_gutenberg_generate_landing_page_payload( is_array( $input ) ? $input : array() );
				return array_merge( array( 'success' => true ), $payload );
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/validate-content',
		array(
			'label'               => 'Validate Gutenberg Content',
			'description'         => 'Validate Gutenberg content for block presence, round-trip stability, and basic landing-page structure.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to validate instead of supplying content directly.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'message'    => array( 'type' => 'string' ),
					'validation' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$content = '';
				if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
					$content = $input['content'];
				} elseif ( isset( $input['post_id'] ) ) {
					$post = mcp_abilities_gutenberg_get_editable_post( (int) $input['post_id'] );
					if ( is_wp_error( $post ) ) {
						return array(
							'success' => false,
							'message' => $post->get_error_message(),
						);
					}
					$content = (string) $post->post_content;
				} else {
					return array(
						'success' => false,
						'message' => 'Provide content or post_id.',
					);
				}

				return array(
					'success'    => true,
					'message'    => 'Validation completed.',
					'validation' => mcp_abilities_gutenberg_validate_content( $content ),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/parse-content',
		array(
			'label'               => 'Parse Gutenberg Content',
			'description'         => 'Parse raw post content into a normalized Gutenberg block tree.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'content' ),
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw post content, ideally Gutenberg block markup.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'content' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$content = isset( $input['content'] ) ? (string) $input['content'] : '';
				return array(
					'success' => true,
					'message' => 'Content parsed successfully.',
					'summary' => mcp_abilities_gutenberg_content_summary( $content ),
					'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
					'content' => $content,
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/serialize-blocks',
		array(
			'label'               => 'Serialize Gutenberg Blocks',
			'description'         => 'Serialize a normalized Gutenberg block tree into valid block markup.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'blocks' ),
				'properties'           => array(
					'blocks' => array(
						'type'        => 'array',
						'description' => 'Normalized blocks from this plugin or native parse_blocks-style arrays.',
						'items'       => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );
				if ( is_wp_error( $blocks ) ) {
					return array(
						'success' => false,
						'message' => $blocks->get_error_message(),
					);
				}

				$content = serialize_blocks( $blocks );

				return array(
					'success' => true,
					'message' => 'Blocks serialized successfully.',
					'content' => $content,
					'summary' => mcp_abilities_gutenberg_content_summary( $content ),
					'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/get-post-blocks',
		array(
			'label'               => 'Get Post Gutenberg Blocks',
			'description'         => 'Load a post or page and return its raw content plus normalized Gutenberg blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'post'    => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
				if ( $post_id <= 0 ) {
					return array(
						'success' => false,
						'message' => 'post_id is required.',
					);
				}

				$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
				if ( is_wp_error( $post ) ) {
					return array(
						'success' => false,
						'message' => $post->get_error_message(),
					);
				}

				$content = (string) $post->post_content;

				return array(
					'success' => true,
					'message' => 'Post blocks loaded successfully.',
					'post'    => array(
						'id'       => (int) $post->ID,
						'type'     => (string) $post->post_type,
						'status'   => (string) $post->post_status,
						'slug'     => (string) $post->post_name,
						'title'    => get_the_title( $post ),
						'url'      => get_permalink( $post ),
						'modified' => (string) $post->post_modified_gmt,
					),
					'content' => $content,
					'summary' => mcp_abilities_gutenberg_content_summary( $content ),
					'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/create-page-from-blocks',
		array(
			'label'               => 'Create Page From Gutenberg Blocks',
			'description'         => 'Create a new page using raw content or normalized Gutenberg blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title' => array(
						'type'        => 'string',
						'description' => 'Page title.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional page slug.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Page status.',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to save.',
					),
					'blocks' => array(
						'type'        => 'array',
						'description' => 'Normalized blocks from this plugin or native parse_blocks-style arrays.',
						'items'       => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'post'    => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_create_page_from_input( is_array( $input ) ? $input : array() );
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/create-landing-page',
		array(
			'label'               => 'Create Landing Page',
			'description'         => 'Generate and create a landing page for a business in one step.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'business_name' ),
				'properties' => array(
					'business_name' => array(
						'type'        => 'string',
						'description' => 'Business name.',
					),
					'industry' => array(
						'type'        => 'string',
						'description' => 'Industry or business type.',
					),
					'tone' => array(
						'type'        => 'string',
						'description' => 'Desired tone for the page copy.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Optional page title.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional page slug.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Page status.',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
					),
					'primary_cta_text' => array(
						'type'        => 'string',
						'description' => 'Primary button label.',
					),
					'secondary_cta_text' => array(
						'type'        => 'string',
						'description' => 'Secondary button label.',
					),
					'offerings' => array(
						'type'        => 'array',
						'description' => 'Optional list of featured offerings.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'message'   => array( 'type' => 'string' ),
					'post'      => array( 'type' => 'object' ),
					'content'   => array( 'type' => 'string' ),
					'summary'   => array( 'type' => 'object' ),
					'blocks'    => array( 'type' => 'array' ),
					'blueprint' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$payload = mcp_abilities_gutenberg_generate_landing_page_payload( is_array( $input ) ? $input : array() );
				$create  = mcp_abilities_gutenberg_create_page_from_input(
					array(
						'title'   => $payload['title'],
						'slug'    => $payload['slug'],
						'status'  => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
						'content' => $payload['content'],
					)
				);

				if ( ! empty( $create['success'] ) ) {
					$create['blueprint'] = $payload['blueprint'];
				}

				return $create;
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);

	mcp_abilities_gutenberg_register_ability(
		'gutenberg/update-post-blocks',
		array(
			'label'               => 'Update Post Gutenberg Blocks',
			'description'         => 'Update a post or page using normalized Gutenberg blocks or raw content.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post or page ID.',
					),
					'blocks' => array(
						'type'        => 'array',
						'description' => 'Normalized blocks from this plugin or native parse_blocks-style arrays.',
						'items'       => array( 'type' => 'object' ),
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw content to save. If omitted, `blocks` will be serialized.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Optional post title update.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Optional post status update.',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'post'    => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
				if ( $post_id <= 0 ) {
					return array(
						'success' => false,
						'message' => 'post_id is required.',
					);
				}

				$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
				if ( is_wp_error( $post ) ) {
					return array(
						'success' => false,
						'message' => $post->get_error_message(),
					);
				}

				$content = null;
				if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
					$content = $input['content'];
				} elseif ( array_key_exists( 'blocks', $input ) ) {
					$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] );
					if ( is_wp_error( $blocks ) ) {
						return array(
							'success' => false,
							'message' => $blocks->get_error_message(),
						);
					}

					$content = serialize_blocks( $blocks );
				}

				if ( null === $content ) {
					return array(
						'success' => false,
						'message' => 'Provide either content or blocks.',
					);
				}

				$update_args = array(
					'ID'           => $post_id,
					'post_content' => $content,
				);

				if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
					$update_args['post_title'] = $input['title'];
				}

				if ( isset( $input['status'] ) && is_string( $input['status'] ) ) {
					$update_args['post_status'] = $input['status'];
				}

				$result = wp_update_post( wp_slash( $update_args ), true );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}

				$updated_post    = get_post( $post_id );
				$updated_content = $updated_post ? (string) $updated_post->post_content : $content;

				return array(
					'success' => true,
					'message' => 'Post updated successfully.',
					'post'    => array(
						'id'       => $post_id,
						'type'     => $updated_post ? (string) $updated_post->post_type : (string) $post->post_type,
						'status'   => $updated_post ? (string) $updated_post->post_status : (string) $post->post_status,
						'slug'     => $updated_post ? (string) $updated_post->post_name : (string) $post->post_name,
						'title'    => $updated_post ? get_the_title( $updated_post ) : get_the_title( $post ),
						'url'      => $updated_post ? get_permalink( $updated_post ) : get_permalink( $post ),
						'modified' => $updated_post ? (string) $updated_post->post_modified_gmt : (string) $post->post_modified_gmt,
					),
					'content' => $updated_content,
					'summary' => mcp_abilities_gutenberg_content_summary( $updated_content ),
					'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $updated_content ) ),
				);
			},
			'permission_callback' => 'mcp_abilities_gutenberg_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'mcp_abilities_gutenberg_register_category' );
add_action( 'wp_abilities_api_init', 'mcp_abilities_gutenberg_register_abilities' );

if ( did_action( 'init' ) ) {
	mcp_abilities_gutenberg_register_category();
	mcp_abilities_gutenberg_register_abilities();
}
