<?php
/**
 * Plugin Name: MCP Abilities - Block Editor
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-block-editor
 * Description: WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.
 * Version: 0.20.2
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

	if ( ! empty( $inner_blocks ) ) {
		$null_count = 0;
		foreach ( $inner_content as $item ) {
			if ( null === $item ) {
				$null_count++;
			}
		}

		if ( $null_count !== count( $inner_blocks ) ) {
			$wrappers = array_values(
				array_filter(
					$inner_content,
					static function ( $item ): bool {
						return is_string( $item ) && '' !== $item;
					}
				)
			);

			if ( count( $wrappers ) >= 2 ) {
				$inner_content = array_merge(
					array( (string) $wrappers[0] ),
					array_fill( 0, count( $inner_blocks ), null ),
					array( (string) $wrappers[ count( $wrappers ) - 1 ] )
				);
			} elseif ( 1 === count( $wrappers ) ) {
				$inner_content = array_merge(
					array( (string) $wrappers[0] ),
					array_fill( 0, count( $inner_blocks ), null )
				);
			} else {
				$inner_content = array_fill( 0, count( $inner_blocks ), null );
			}
		}
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
 * Return changed dotted attribute paths between two attr arrays.
 *
 * @param array<string,mixed> $before Before attrs.
 * @param array<string,mixed> $after  After attrs.
 * @return array<int,string>
 */
function mcp_abilities_gutenberg_get_changed_attr_paths( array $before, array $after ): array {
	$changed = array();
	$keys    = array_values( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) );

	foreach ( $keys as $key ) {
		$key_exists_before = array_key_exists( $key, $before );
		$key_exists_after  = array_key_exists( $key, $after );
		$key_name          = is_int( $key ) ? (string) $key : sanitize_key( (string) $key );

		if ( ! $key_exists_before || ! $key_exists_after ) {
			$changed[] = $key_name;
			continue;
		}

		$before_value = $before[ $key ];
		$after_value  = $after[ $key ];

		if ( is_array( $before_value ) && is_array( $after_value ) ) {
			$nested = mcp_abilities_gutenberg_get_changed_attr_paths( $before_value, $after_value );
			foreach ( $nested as $nested_key ) {
				$changed[] = $key_name . '.' . $nested_key;
			}
			continue;
		}

		if ( $before_value !== $after_value ) {
			$changed[] = $key_name;
		}
	}

	return array_values( array_unique( $changed ) );
}

/**
 * Determine whether an attr path is editor-only and safe without static markup regeneration.
 *
 * @param string $path Dotted attr path.
 * @return bool
 */
function mcp_abilities_gutenberg_is_editor_only_attr_path( string $path ): bool {
	$editor_only_roots = array(
		'lock',
		'templateLock',
		'allowedBlocks',
		'metadata',
	);

	foreach ( $editor_only_roots as $root ) {
		if ( $path === $root || str_starts_with( $path, $root . '.' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a normalized block persists saved markup.
 *
 * @param array<string,mixed> $block Normalized block.
 * @return bool
 */
function mcp_abilities_gutenberg_block_persists_markup( array $block ): bool {
	$inner_html = isset( $block['inner_html'] ) ? trim( (string) $block['inner_html'] ) : '';
	if ( '' !== $inner_html ) {
		return true;
	}

	$inner_content = isset( $block['inner_content'] ) && is_array( $block['inner_content'] ) ? $block['inner_content'] : array();
	foreach ( $inner_content as $chunk ) {
		if ( is_string( $chunk ) && '' !== trim( $chunk ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Evaluate whether a static block attr change can leave saved markup stale.
 *
 * @param array<string,mixed>   $before Before normalized block.
 * @param array<string,mixed>   $after  After normalized block.
 * @param array<int,int|string> $path   Block path.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_static_render_risks_for_block( array $before, array $after, array $path ): array {
	$before_name = isset( $before['block_name'] ) ? (string) $before['block_name'] : '';
	$after_name  = isset( $after['block_name'] ) ? (string) $after['block_name'] : '';
	if ( '' === $after_name || $before_name !== $after_name ) {
		return array();
	}

	if ( ! mcp_abilities_gutenberg_block_persists_markup( $before ) && ! mcp_abilities_gutenberg_block_persists_markup( $after ) ) {
		return array();
	}

	$before_attrs = isset( $before['attrs'] ) && is_array( $before['attrs'] ) ? $before['attrs'] : array();
	$after_attrs  = isset( $after['attrs'] ) && is_array( $after['attrs'] ) ? $after['attrs'] : array();
	$changed      = mcp_abilities_gutenberg_get_changed_attr_paths( $before_attrs, $after_attrs );
	if ( empty( $changed ) ) {
		return array();
	}

	$risky = array_values(
		array_filter(
			$changed,
			static function ( string $attr_path ): bool {
				return ! mcp_abilities_gutenberg_is_editor_only_attr_path( $attr_path );
			}
		)
	);

	if ( empty( $risky ) ) {
		return array();
	}

	return array(
		array(
			'type'              => 'static_markup_stale_risk',
			'severity'          => 'error',
			'path'              => array_values( $path ),
			'block_name'        => $after_name,
			'changed_attrs'     => $risky,
			'editor_only_attrs' => array_values( array_diff( $changed, $risky ) ),
			'message'           => 'Static block attrs changed without regenerating saved HTML; frontend rendering may stay stale.',
		),
	);
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
			'scenario'     => 'Short proof labels, service tags, or metadata that are not clickable',
			'best_block'   => 'core/paragraph',
			'alternatives' => array( 'core/list', 'core/group' ),
			'use_when'     => 'Use for short non-interactive proof points such as scope labels, guarantees, or service metadata that sit near a hero or intro.',
			'avoid_when'   => 'Avoid styling inert labels to look like buttons, pills, or controls when nothing happens on click.',
			'notes'        => 'If the items are not links or controls, keep them visually quieter than the real CTA. If they take up meaningful space, make them earn it with a short proof strip or concise supporting line rather than naked label chips.',
		),
		array(
			'scenario'     => 'Supportive proof row directly under a CTA cluster',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/columns', 'core/list' ),
			'use_when'     => 'Use for short supporting proof or reassurance that belongs immediately under hero copy or buttons.',
			'avoid_when'   => 'Avoid leaving a large empty gap between the CTA cluster and the support row unless it is intentionally becoming a new section.',
			'notes'        => 'Treat the proof row as part of the same selling moment. Keep the transition compact, and only use a larger gap when the support row has a clearly separate surface or section identity.',
		),
		array(
			'scenario'     => 'Adjacent full-width sections that should visually touch',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/cover', 'core/group' ),
			'use_when'     => 'Use when stacking a hero, band, strip, or other full-width sections with no intended seam between them.',
			'avoid_when'   => 'Avoid relying on the default flow gap between adjacent full-width sections when the design expects edge-to-edge continuity.',
			'notes'        => 'In flow layouts, WordPress can inject a default seam between stacked sections. If the visual intent is a continuous transition, neutralize that seam explicitly instead of letting a bright strip appear by accident.',
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
			'scenario'     => 'Keeping a coherent content width rhythm across a multi-section page',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/cover', 'core/columns' ),
			'use_when'     => 'Use a top-level group or repeated section groups to keep intro panels, cards, quotes, reusable rows, and CTA sections on one primary content measure.',
			'avoid_when'   => 'Avoid mixing several arbitrary fixed widths for adjacent sections unless the contrast is a deliberate editorial effect.',
			'notes'        => 'Pick one main content width for interior sections. Let only heroes, strips, and intentional full-bleed sections break that measure. If the page shell is already full width, neutralize alignfull breakout margins instead of stacking another breakout trick on top.',
		),
		array(
			'scenario'     => 'Breaking a page out of repetitive all-card composition',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/separator', 'core/cover', 'core/paragraph' ),
			'use_when'     => 'Use open groups, text bands, dividers, and occasional full-bleed sections to vary rhythm between contained modules.',
			'avoid_when'   => 'Avoid wrapping every content beat in another rounded panel, especially when several adjacent sections already have backgrounds, borders, or shadows.',
			'notes'        => 'Pages usually feel more designed when only a few moments are card-like. Keep some sections open on the page background so the layout can breathe.',
		),
		array(
			'scenario'     => 'Keeping a repeated row coherent without one random boxed sibling',
			'best_block'   => 'core/columns',
			'alternatives' => array( 'core/group', 'core/separator' ),
			'use_when'     => 'Use columns for parallel items, but keep the sibling treatment family consistent across the row unless one item is intentionally a featured spotlight.',
			'avoid_when'   => 'Avoid turning only one column into a contained card while adjacent siblings stay open unless the featured state is unmistakable.',
			'notes'        => 'Mixed open-versus-boxed siblings often read as unfinished AI output. Either keep the whole row open, contain the full set, or make the spotlight item clearly dominant.',
		),
		array(
			'scenario'     => 'Keeping balanced vertical spacing between sections',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/separator', 'core/spacer' ),
			'use_when'     => 'Use section groups with a small shared spacing token set so the page has a repeatable vertical rhythm.',
			'avoid_when'   => 'Avoid solving every gap with a different spacer height, padding value, or one-off margin unless the break in rhythm is clearly intentional.',
			'notes'        => 'Balanced pages usually reuse a few section distances, such as one compact gap and one generous gap. Too many unrelated values make the page feel lopsided even when the blocks themselves are fine.',
		),
		array(
			'scenario'     => 'Editorial split layout with one dominant column and one smaller support column',
			'best_block'   => 'core/columns',
			'alternatives' => array( 'core/group', 'core/media-text' ),
			'use_when'     => 'Use for sections where one side carries the main heading or statement and the other side carries shorter supporting copy, notes, or meta context.',
			'avoid_when'   => 'Avoid leaving the smaller support column top-aligned by default when it is visibly shorter, or attaching the divider to a nested text wrapper that stops halfway down the section.',
			'notes'        => 'Keep the full section on the shared page width. If one side is much shorter, center that support column vertically so it feels placed rather than abandoned. If you use a divider, anchor it to the column or section structure so it runs the intended full height instead of ending at the text block.',
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

	if ( defined( 'WP_CLI' ) && WP_CLI && 0 === get_current_user_id() ) {
		$administrators = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => array( 'ID' ),
			)
		);
		if ( ! empty( $administrators[0]->ID ) ) {
			wp_set_current_user( (int) $administrators[0]->ID );
		}
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
 * @return array<string,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_registered_patterns_map(): array {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return array();
	}

	$registry = WP_Block_Patterns_Registry::get_instance();

	try {
		$reflection = new ReflectionClass( $registry );
		if ( $reflection->hasProperty( 'registered_patterns' ) ) {
			$property = $reflection->getProperty( 'registered_patterns' );
			$property->setAccessible( true );
			$value = $property->getValue( $registry );
			if ( is_array( $value ) ) {
				return $value;
			}
		}
	} catch ( ReflectionException $exception ) {
	}

	$fallback = array();
	foreach ( $registry->get_all_registered() as $index => $pattern ) {
		if ( is_array( $pattern ) ) {
			$fallback[ (string) $index ] = $pattern;
		}
	}

	return $fallback;
}

/**
 * List registered block patterns.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_pattern_catalog(): array {
	$patterns = mcp_abilities_gutenberg_get_registered_patterns_map();
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
 * Return compact block categories derived from registered block types.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_block_categories(): array {
	$catalog    = mcp_abilities_gutenberg_get_block_catalog();
	$categories = array();

	foreach ( $catalog as $block ) {
		$slug = isset( $block['category'] ) ? (string) $block['category'] : '';
		if ( '' === $slug ) {
			continue;
		}

		if ( ! isset( $categories[ $slug ] ) ) {
			$categories[ $slug ] = array(
				'slug'        => $slug,
				'label'       => ucwords( str_replace( '-', ' ', $slug ) ),
				'block_count' => 0,
				'blocks'      => array(),
			);
		}

		$categories[ $slug ]['block_count']++;
		$categories[ $slug ]['blocks'][] = (string) $block['name'];
	}

	$values = array_values( $categories );
	usort(
		$values,
		static function ( array $a, array $b ): int {
			return strcmp( $a['slug'], $b['slug'] );
		}
	);

	return $values;
}

/**
 * Return a single block type definition.
 *
 * @param string $name Block name.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_block_details( string $name ) {
	$registry   = WP_Block_Type_Registry::get_instance();
	$block_type = $registry->get_registered( $name );

	if ( ! $block_type ) {
		return new WP_Error( 'mcp_gutenberg_block_not_found', 'Block type not found.' );
	}

	return array(
		'name'             => (string) $block_type->name,
		'title'            => (string) $block_type->title,
		'description'      => (string) $block_type->description,
		'category'         => is_string( $block_type->category ) ? $block_type->category : '',
		'parent'           => is_array( $block_type->parent ) ? array_values( $block_type->parent ) : array(),
		'ancestor'         => is_array( $block_type->ancestor ) ? array_values( $block_type->ancestor ) : array(),
		'allowed_blocks'   => is_array( $block_type->allowed_blocks ) ? array_values( $block_type->allowed_blocks ) : array(),
		'attributes'       => is_array( $block_type->attributes ) ? $block_type->attributes : array(),
		'styles'           => is_array( $block_type->styles ) ? $block_type->styles : array(),
		'supports'         => is_array( $block_type->supports ) ? $block_type->supports : array(),
		'selectors'        => is_array( $block_type->selectors ) ? $block_type->selectors : array(),
		'keywords'         => is_array( $block_type->keywords ) ? array_values( $block_type->keywords ) : array(),
		'example'          => is_array( $block_type->example ) ? $block_type->example : array(),
		'render_callback'  => is_callable( $block_type->render_callback ),
		'uses_context'     => is_array( $block_type->uses_context ) ? array_values( $block_type->uses_context ) : array(),
		'provides_context' => is_array( $block_type->provides_context ) ? $block_type->provides_context : array(),
	);
}

/**
 * Return a registered block pattern with optional raw content.
 *
 * @param string $name Pattern name.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_pattern_details( string $name ) {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return new WP_Error( 'mcp_gutenberg_patterns_unavailable', 'Block patterns registry is unavailable.' );
	}

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( $name );

	if ( ! is_array( $pattern ) ) {
		$patterns = mcp_abilities_gutenberg_get_registered_patterns_map();
		$pattern  = isset( $patterns[ $name ] ) && is_array( $patterns[ $name ] ) ? $patterns[ $name ] : null;
	}

	if ( ! is_array( $pattern ) ) {
		return new WP_Error( 'mcp_gutenberg_pattern_not_found', 'Pattern not found.' );
	}

	$content = isset( $pattern['content'] ) ? (string) $pattern['content'] : '';

	return array(
		'name'        => $name,
		'title'       => (string) ( $pattern['title'] ?? '' ),
		'description' => (string) ( $pattern['description'] ?? '' ),
		'categories'  => array_values( is_array( $pattern['categories'] ?? null ) ? $pattern['categories'] : array() ),
		'keywords'    => array_values( is_array( $pattern['keywords'] ?? null ) ? $pattern['keywords'] : array() ),
		'block_types' => array_values( is_array( $pattern['blockTypes'] ?? null ) ? $pattern['blockTypes'] : array() ),
		'post_types'  => array_values( is_array( $pattern['postTypes'] ?? null ) ? $pattern['postTypes'] : array() ),
		'inserter'    => (bool) ( $pattern['inserter'] ?? true ),
		'content'     => $content,
		'summary'     => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'      => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Return wp_template or wp_template_part posts.
 *
 * @param string $post_type Post type to load.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_template_entities( string $post_type ): array {
	$posts = get_posts(
		array(
			'post_type'              => $post_type,
			'post_status'            => array( 'publish', 'draft', 'auto-draft' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( $posts as $post ) {
		$items[] = array(
			'id'       => (int) $post->ID,
			'slug'     => (string) $post->post_name,
			'title'    => get_the_title( $post ),
			'status'   => (string) $post->post_status,
			'type'     => (string) $post->post_type,
			'theme'    => (string) get_post_meta( $post->ID, 'theme', true ),
			'area'     => (string) get_post_meta( $post->ID, 'area', true ),
			'modified' => (string) $post->post_modified_gmt,
		);
	}

	return $items;
}

/**
 * Get a single template or template part.
 *
 * @param string $post_type Post type to load.
 * @param array  $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_template_entity( string $post_type, array $input ) {
	$post = null;

	if ( isset( $input['post_id'] ) ) {
		$post = get_post( (int) $input['post_id'] );
	} elseif ( isset( $input['slug'] ) ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'name'                   => sanitize_title( (string) $input['slug'] ),
				'post_status'            => array( 'publish', 'draft', 'auto-draft' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$post = ! empty( $posts ) ? $posts[0] : null;
	}

	if ( ! $post instanceof WP_Post || $post_type !== $post->post_type ) {
		return new WP_Error( 'mcp_gutenberg_template_not_found', 'Requested template entity not found.' );
	}

	$content = (string) $post->post_content;

	return array(
		'id'       => (int) $post->ID,
		'slug'     => (string) $post->post_name,
		'title'    => get_the_title( $post ),
		'status'   => (string) $post->post_status,
		'type'     => (string) $post->post_type,
		'theme'    => (string) get_post_meta( $post->ID, 'theme', true ),
		'area'     => (string) get_post_meta( $post->ID, 'area', true ),
		'modified' => (string) $post->post_modified_gmt,
		'content'  => $content,
		'summary'  => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'   => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Create or update a template entity.
 *
 * @param string $post_type Post type.
 * @param array  $input Input payload.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_save_template_entity( string $post_type, array $input ): array {
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

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}

	$title  = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : 'Untitled';
	$slug   = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $title );
	$status = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'publish';
	$theme  = isset( $input['theme'] ) ? sanitize_key( (string) $input['theme'] ) : wp_get_theme()->get_stylesheet();
	$area   = isset( $input['area'] ) ? sanitize_key( (string) $input['area'] ) : '';
	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

	$postarr = array(
		'post_type'    => $post_type,
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => $status,
		'post_content' => $content,
	);

	if ( $post_id > 0 ) {
		$post = get_post( $post_id );
		if ( ! $post || $post_type !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => 'Template entity not found.',
			);
		}
		$postarr['ID'] = $post_id;
		$result        = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$result = wp_insert_post( wp_slash( $postarr ), true );
		$post_id = is_wp_error( $result ) ? 0 : (int) $result;
	}

	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	if ( '' !== $theme ) {
		update_post_meta( $post_id, 'theme', $theme );
	}
	if ( 'wp_template_part' === $post_type && '' !== $area ) {
		update_post_meta( $post_id, 'area', $area );
	}

	$saved = get_post( $post_id );

	return array(
		'success' => true,
		'message' => $postarr['ID'] ?? null ? 'Template entity updated successfully.' : 'Template entity created successfully.',
		'entity'  => array(
			'id'       => (int) $post_id,
			'slug'     => $saved ? (string) $saved->post_name : $slug,
			'title'    => $saved ? get_the_title( $saved ) : $title,
			'status'   => $saved ? (string) $saved->post_status : $status,
			'type'     => $saved ? (string) $saved->post_type : $post_type,
			'theme'    => (string) get_post_meta( $post_id, 'theme', true ),
			'area'     => (string) get_post_meta( $post_id, 'area', true ),
			'modified' => $saved ? (string) $saved->post_modified_gmt : '',
		),
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Return reusable synced patterns (`wp_block`) entities.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_synced_patterns(): array {
	$posts = get_posts(
		array(
			'post_type'              => 'wp_block',
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( $posts as $post ) {
		$items[] = array(
			'id'       => (int) $post->ID,
			'slug'     => (string) $post->post_name,
			'title'    => get_the_title( $post ),
			'status'   => (string) $post->post_status,
			'modified' => (string) $post->post_modified_gmt,
		);
	}

	return $items;
}

/**
 * Get a synced pattern by ID or slug.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_synced_pattern( array $input ) {
	$post = null;
	if ( isset( $input['post_id'] ) ) {
		$post = get_post( (int) $input['post_id'] );
	} elseif ( isset( $input['slug'] ) ) {
		$posts = get_posts(
			array(
				'post_type'              => 'wp_block',
				'name'                   => sanitize_title( (string) $input['slug'] ),
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$post = ! empty( $posts ) ? $posts[0] : null;
	}

	if ( ! $post instanceof WP_Post || 'wp_block' !== $post->post_type ) {
		return new WP_Error( 'mcp_gutenberg_synced_pattern_not_found', 'Synced pattern not found.' );
	}

	$content = (string) $post->post_content;

	return array(
		'id'       => (int) $post->ID,
		'slug'     => (string) $post->post_name,
		'title'    => get_the_title( $post ),
		'status'   => (string) $post->post_status,
		'modified' => (string) $post->post_modified_gmt,
		'content'  => $content,
		'summary'  => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'   => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Create or update a synced pattern (`wp_block`).
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_save_synced_pattern( array $input ): array {
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

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}

	$title   = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : 'Untitled Synced Pattern';
	$slug    = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $title );
	$status  = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'publish';
	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

	$postarr = array(
		'post_type'    => 'wp_block',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => $status,
		'post_content' => $content,
	);

	if ( $post_id > 0 ) {
		$post = get_post( $post_id );
		if ( ! $post || 'wp_block' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => 'Synced pattern not found.',
			);
		}
		$postarr['ID'] = $post_id;
		$result        = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$result  = wp_insert_post( wp_slash( $postarr ), true );
		$post_id = is_wp_error( $result ) ? 0 : (int) $result;
	}

	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	$saved = get_post( $post_id );

	return array(
		'success' => true,
		'message' => $postarr['ID'] ?? null ? 'Synced pattern updated successfully.' : 'Synced pattern created successfully.',
		'pattern' => array(
			'id'       => (int) $post_id,
			'slug'     => $saved ? (string) $saved->post_name : $slug,
			'title'    => $saved ? get_the_title( $saved ) : $title,
			'status'   => $saved ? (string) $saved->post_status : $status,
			'modified' => $saved ? (string) $saved->post_modified_gmt : '',
		),
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Extract a block subtree into a synced pattern and optionally replace the source with a pattern reference.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_extract_synced_pattern( array $input ): array {
	$path = mcp_abilities_gutenberg_normalize_block_path( $input['path'] ?? null );
	if ( is_wp_error( $path ) ) {
		return array(
			'success' => false,
			'message' => $path->get_error_message(),
		);
	}

	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$post    = null;
	if ( $post_id > 0 ) {
		$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return array(
				'success' => false,
				'message' => $post->get_error_message(),
			);
		}
		$normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( (string) $post->post_content ) );
	} else {
		$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return array(
				'success' => false,
				'message' => $blocks->get_error_message(),
			);
		}
		$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	}

	$target_block = mcp_abilities_gutenberg_get_block_by_path( $normalized, $path );
	if ( is_wp_error( $target_block ) ) {
		return array(
			'success' => false,
			'message' => $target_block->get_error_message(),
		);
	}

	$save = mcp_abilities_gutenberg_save_synced_pattern(
		array(
			'title'  => isset( $input['title'] ) ? (string) $input['title'] : 'Extracted Synced Pattern',
			'slug'   => isset( $input['slug'] ) ? (string) $input['slug'] : '',
			'status' => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
			'blocks' => array( $target_block ),
		)
	);

	if ( empty( $save['success'] ) ) {
		return $save;
	}

	$replace_source = ! empty( $input['replace_source'] );
	$mutated        = $normalized;
	if ( $replace_source ) {
		$ref_id = (int) ( $save['pattern']['id'] ?? 0 );
		$mutated = mcp_abilities_gutenberg_mutate_blocks_at_path(
			$normalized,
			$path,
			static function () use ( $ref_id ): array {
				return array(
					'block_name'    => 'core/block',
					'attrs'         => array( 'ref' => $ref_id ),
					'inner_blocks'  => array(),
					'inner_html'    => '',
					'inner_content' => array(),
				);
			}
		);

		if ( is_wp_error( $mutated ) ) {
			return array(
				'success' => false,
				'message' => $mutated->get_error_message(),
			);
		}

		$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
		if ( is_wp_error( $denormalized ) ) {
			return array(
				'success' => false,
				'message' => $denormalized->get_error_message(),
			);
		}

		$content = serialize_blocks( $denormalized );
		$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
		if ( is_wp_error( $layout_guard ) ) {
			return array(
				'success' => false,
				'message' => $layout_guard->get_error_message(),
			);
		}
		if ( $post instanceof WP_Post ) {
			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => (int) $post->ID,
						'post_content' => $content,
					)
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}
		}
	}

	$response = array(
		'success'      => true,
		'message'      => $replace_source ? 'Synced pattern extracted and source replaced with pattern reference.' : 'Synced pattern extracted successfully.',
		'pattern'      => $save['pattern'] ?? array(),
		'extracted'    => array(
			'path'    => $path,
			'block'   => $target_block,
			'content' => $save['content'] ?? '',
			'summary' => $save['summary'] ?? array(),
		),
		'post'         => $post instanceof WP_Post ? array(
			'id'       => (int) $post->ID,
			'type'     => (string) $post->post_type,
			'status'   => (string) $post->post_status,
			'slug'     => (string) $post->post_name,
			'title'    => get_the_title( $post ),
			'url'      => get_permalink( $post ),
			'modified' => (string) $post->post_modified_gmt,
		) : null,
		'replaced'     => $replace_source,
		'content'      => $replace_source ? serialize_blocks( mcp_abilities_gutenberg_denormalize_blocks( $mutated ) ) : '',
		'summary'      => $replace_source ? mcp_abilities_gutenberg_content_summary( serialize_blocks( mcp_abilities_gutenberg_denormalize_blocks( $mutated ) ) ) : array(),
		'blocks'       => $replace_source ? $mutated : array(),
	);

	return $response;
}

/**
 * Insert a synced pattern reference block into an existing post.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_insert_synced_pattern_into_post( array $input ): array {
	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$post    = mcp_abilities_gutenberg_get_editable_post( $post_id );
	if ( is_wp_error( $post ) ) {
		return array(
			'success' => false,
			'message' => $post->get_error_message(),
		);
	}

	$pattern_input = array();
	if ( isset( $input['pattern_id'] ) ) {
		$pattern_input['post_id'] = (int) $input['pattern_id'];
	}
	if ( isset( $input['slug'] ) ) {
		$pattern_input['slug'] = (string) $input['slug'];
	}

	$pattern = mcp_abilities_gutenberg_get_synced_pattern( $pattern_input );
	if ( is_wp_error( $pattern ) ) {
		return array(
			'success' => false,
			'message' => $pattern->get_error_message(),
		);
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( (string) $post->post_content ) );
	$parent     = isset( $input['parent_path'] ) ? mcp_abilities_gutenberg_normalize_block_path( $input['parent_path'] ) : array();
	if ( is_wp_error( $parent ) ) {
		return array(
			'success' => false,
			'message' => $parent->get_error_message(),
		);
	}

	$position = isset( $input['position'] ) ? (int) $input['position'] : PHP_INT_MAX;
	$ref_block = array(
		'block_name'    => 'core/block',
		'attrs'         => array( 'ref' => (int) $pattern['id'] ),
		'inner_blocks'  => array(),
		'inner_html'    => '',
		'inner_content' => array(),
	);

	$mutated = mcp_abilities_gutenberg_insert_block_at_path( $normalized, $parent, $ref_block, $position );
	if ( is_wp_error( $mutated ) ) {
		return array(
			'success' => false,
			'message' => $mutated->get_error_message(),
		);
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return array(
			'success' => false,
			'message' => $denormalized->get_error_message(),
		);
	}

	$content = serialize_blocks( $denormalized );
	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}
	$result  = wp_update_post(
		wp_slash(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $content,
			)
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	$updated_post = get_post( (int) $post->ID );

	return array(
		'success' => true,
		'message' => 'Synced pattern reference inserted successfully.',
		'post'    => array(
			'id'       => (int) $post->ID,
			'type'     => $updated_post ? (string) $updated_post->post_type : (string) $post->post_type,
			'status'   => $updated_post ? (string) $updated_post->post_status : (string) $post->post_status,
			'slug'     => $updated_post ? (string) $updated_post->post_name : (string) $post->post_name,
			'title'    => $updated_post ? get_the_title( $updated_post ) : get_the_title( $post ),
			'url'      => $updated_post ? get_permalink( $updated_post ) : get_permalink( $post ),
			'modified' => $updated_post ? (string) $updated_post->post_modified_gmt : (string) $post->post_modified_gmt,
		),
		'pattern' => $pattern,
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => $mutated,
	);
}

/**
 * Return wp_navigation entities.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_navigation_entities(): array {
	$posts = get_posts(
		array(
			'post_type'              => 'wp_navigation',
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( $posts as $post ) {
		$content = (string) $post->post_content;
		$blocks  = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) );
		$usage   = mcp_abilities_gutenberg_collect_block_usage( $blocks );
		$items[] = array(
			'id'          => (int) $post->ID,
			'slug'        => (string) $post->post_name,
			'title'       => get_the_title( $post ),
			'status'      => (string) $post->post_status,
			'modified'    => (string) $post->post_modified_gmt,
			'block_count' => count( $blocks ),
			'items'       => (int) ( $usage['core/navigation-link'] ?? 0 ) + (int) ( $usage['core/navigation-submenu'] ?? 0 ),
		);
	}

	return $items;
}

/**
 * Get a navigation entity by ID or slug.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_navigation_entity( array $input ) {
	$post = null;
	if ( isset( $input['post_id'] ) ) {
		$post = get_post( (int) $input['post_id'] );
	} elseif ( isset( $input['slug'] ) ) {
		$posts = get_posts(
			array(
				'post_type'              => 'wp_navigation',
				'name'                   => sanitize_title( (string) $input['slug'] ),
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$post = ! empty( $posts ) ? $posts[0] : null;
	}

	if ( ! $post instanceof WP_Post || 'wp_navigation' !== $post->post_type ) {
		return new WP_Error( 'mcp_gutenberg_navigation_not_found', 'Navigation not found.' );
	}

	$content = (string) $post->post_content;
	$blocks  = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) );

	return array(
		'id'       => (int) $post->ID,
		'slug'     => (string) $post->post_name,
		'title'    => get_the_title( $post ),
		'status'   => (string) $post->post_status,
		'modified' => (string) $post->post_modified_gmt,
		'content'  => $content,
		'summary'  => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'   => $blocks,
	);
}

/**
 * Create or update a navigation entity.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_save_navigation_entity( array $input ): array {
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

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}

	$title   = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : 'Untitled Navigation';
	$slug    = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $title );
	$status  = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'publish';
	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

	$postarr = array(
		'post_type'    => 'wp_navigation',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => $status,
		'post_content' => $content,
	);

	if ( $post_id > 0 ) {
		$post = get_post( $post_id );
		if ( ! $post || 'wp_navigation' !== $post->post_type ) {
			return array(
				'success' => false,
				'message' => 'Navigation not found.',
			);
		}
		$postarr['ID'] = $post_id;
		$result        = wp_update_post( wp_slash( $postarr ), true );
	} else {
		$result  = wp_insert_post( wp_slash( $postarr ), true );
		$post_id = is_wp_error( $result ) ? 0 : (int) $result;
	}

	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	$saved = get_post( $post_id );

	return array(
		'success' => true,
		'message' => $postarr['ID'] ?? null ? 'Navigation updated successfully.' : 'Navigation created successfully.',
		'navigation' => array(
			'id'       => (int) $post_id,
			'slug'     => $saved ? (string) $saved->post_name : $slug,
			'title'    => $saved ? get_the_title( $saved ) : $title,
			'status'   => $saved ? (string) $saved->post_status : $status,
			'modified' => $saved ? (string) $saved->post_modified_gmt : '',
		),
		'content'    => $content,
		'summary'    => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'     => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Return a site-editor oriented summary for the active theme.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_site_editor_summary(): array {
	return array(
		'theme'              => mcp_abilities_gutenberg_get_theme_context(),
		'style_book'         => mcp_abilities_gutenberg_get_style_book_summary(),
		'template_count'     => count( mcp_abilities_gutenberg_get_template_entities( 'wp_template' ) ),
		'template_part_count'=> count( mcp_abilities_gutenberg_get_template_entities( 'wp_template_part' ) ),
		'navigation_count'   => count( mcp_abilities_gutenberg_get_navigation_entities() ),
		'synced_pattern_count' => count( mcp_abilities_gutenberg_get_synced_patterns() ),
	);
}

/**
 * Collect site-editor references from a block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param string                         $source_type Source entity type.
 * @param int                            $source_id Source entity ID.
 * @param string                         $source_slug Source entity slug.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_site_editor_references( array $blocks, string $source_type, int $source_id, string $source_slug ): array {
	$references = array();

	$walker = static function ( array $nodes ) use ( &$walker, &$references, $source_type, $source_id, $source_slug ): void {
		foreach ( $nodes as $node ) {
			$name  = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();

			if ( 'core/template-part' === $name ) {
				$references[] = array(
					'kind'         => 'template_part',
					'source_type'  => $source_type,
					'source_id'    => $source_id,
					'source_slug'  => $source_slug,
					'block_name'   => $name,
					'target_slug'  => isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '',
					'target_theme' => isset( $attrs['theme'] ) ? (string) $attrs['theme'] : '',
					'target_area'  => isset( $attrs['area'] ) ? (string) $attrs['area'] : '',
					'attrs'        => $attrs,
				);
			}

			if ( 'core/navigation' === $name ) {
				$references[] = array(
					'kind'         => 'navigation',
					'source_type'  => $source_type,
					'source_id'    => $source_id,
					'source_slug'  => $source_slug,
					'block_name'   => $name,
					'target_ref'   => isset( $attrs['ref'] ) ? (int) $attrs['ref'] : 0,
					'target_slug'  => isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '',
					'attrs'        => $attrs,
				);
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$walker( $node['inner_blocks'] );
			}
		}
	};

	$walker( $blocks );

	return $references;
}

/**
 * Return a site-editor reference graph for templates and template parts.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_site_editor_reference_graph(): array {
	$entities = array_merge(
		mcp_abilities_gutenberg_get_template_entities( 'wp_template' ),
		mcp_abilities_gutenberg_get_template_entities( 'wp_template_part' )
	);

	$references = array();
	foreach ( $entities as $entity ) {
		$post_id = isset( $entity['id'] ) ? (int) $entity['id'] : 0;
		$type    = isset( $entity['type'] ) ? (string) $entity['type'] : '';
		$slug    = isset( $entity['slug'] ) ? (string) $entity['slug'] : '';
		if ( $post_id <= 0 || '' === $type ) {
			continue;
		}

		$full = mcp_abilities_gutenberg_get_template_entity( $type, array( 'post_id' => $post_id ) );
		if ( is_wp_error( $full ) ) {
			continue;
		}

		$blocks = is_array( $full['blocks'] ?? null ) ? $full['blocks'] : array();
		$references = array_merge(
			$references,
			mcp_abilities_gutenberg_collect_site_editor_references( $blocks, $type, $post_id, $slug )
		);
	}

	return array(
		'entity_count'    => count( $entities ),
		'reference_count' => count( $references ),
		'references'      => $references,
	);
}

/**
 * Find navigation usage across templates and template parts.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_find_navigation_usage( array $input ) {
	$graph      = mcp_abilities_gutenberg_get_site_editor_reference_graph();
	$references = is_array( $graph['references'] ?? null ) ? $graph['references'] : array();
	$navigation = null;

	if ( isset( $input['post_id'] ) ) {
		$navigation = mcp_abilities_gutenberg_get_navigation_entity(
			array(
				'post_id' => (int) $input['post_id'],
			)
		);
	} elseif ( isset( $input['slug'] ) ) {
		$navigation = mcp_abilities_gutenberg_get_navigation_entity(
			array(
				'slug' => (string) $input['slug'],
			)
		);
	}

	if ( is_wp_error( $navigation ) ) {
		return $navigation;
	}

	$target_id   = is_array( $navigation ) ? (int) ( $navigation['id'] ?? 0 ) : 0;
	$target_slug = is_array( $navigation ) ? (string) ( $navigation['slug'] ?? '' ) : '';

	$matches = array_values(
		array_filter(
			$references,
			static function ( array $reference ) use ( $target_id, $target_slug ): bool {
				if ( 'navigation' !== (string) ( $reference['kind'] ?? '' ) ) {
					return false;
				}

				$ref_id   = isset( $reference['target_ref'] ) ? (int) $reference['target_ref'] : 0;
				$ref_slug = isset( $reference['target_slug'] ) ? (string) $reference['target_slug'] : '';

				return ( $target_id > 0 && $ref_id === $target_id ) || ( '' !== $target_slug && $ref_slug === $target_slug );
			}
		)
	);

	return array(
		'navigation' => $navigation,
		'matches'    => $matches,
		'match_count'=> count( $matches ),
	);
}

/**
 * Find template-part usage across templates and template parts.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_find_template_part_usage( array $input ) {
	$graph      = mcp_abilities_gutenberg_get_site_editor_reference_graph();
	$references = is_array( $graph['references'] ?? null ) ? $graph['references'] : array();
	$template_part = null;
	$target_slug   = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
	$target_theme  = isset( $input['theme'] ) ? sanitize_key( (string) $input['theme'] ) : '';

	if ( isset( $input['post_id'] ) ) {
		$template_part = mcp_abilities_gutenberg_get_template_entity(
			'wp_template_part',
			array(
				'post_id' => (int) $input['post_id'],
			)
		);
	} elseif ( isset( $input['slug'] ) ) {
		$template_part = mcp_abilities_gutenberg_get_template_entity(
			'wp_template_part',
			array(
				'slug' => $target_slug,
			)
		);
	}

	if ( is_wp_error( $template_part ) && '' === $target_slug ) {
		return $template_part;
	}

	if ( is_array( $template_part ) ) {
		$target_slug  = (string) ( $template_part['slug'] ?? $target_slug );
		$target_theme = (string) ( $template_part['theme'] ?? $target_theme );
	}

	$matches = array_values(
		array_filter(
			$references,
			static function ( array $reference ) use ( $target_slug, $target_theme ): bool {
				if ( 'template_part' !== (string) ( $reference['kind'] ?? '' ) ) {
					return false;
				}

				$ref_slug  = isset( $reference['target_slug'] ) ? (string) $reference['target_slug'] : '';
				$ref_theme = isset( $reference['target_theme'] ) ? (string) $reference['target_theme'] : '';

				if ( '' === $target_slug || $ref_slug !== $target_slug ) {
					return false;
				}

				return '' === $target_theme || '' === $ref_theme || $ref_theme === $target_theme;
			}
		)
	);

	return array(
		'template_part' => is_wp_error( $template_part ) ? array(
			'slug'  => $target_slug,
			'theme' => $target_theme,
		) : $template_part,
		'matches'       => $matches,
		'match_count'   => count( $matches ),
	);
}

/**
 * Collect synced-pattern references from a block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param string                         $source_type Source entity type.
 * @param int                            $source_id Source entity ID.
 * @param string                         $source_slug Source entity slug.
 * @param string                         $source_title Source entity title.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_synced_pattern_references( array $blocks, string $source_type, int $source_id, string $source_slug, string $source_title ): array {
	$references = array();

	$walker = static function ( array $nodes, array $path = array() ) use ( &$walker, &$references, $source_type, $source_id, $source_slug, $source_title ): void {
		foreach ( $nodes as $index => $node ) {
			$current_path = array_merge( $path, array( $index ) );
			$name         = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			$attrs        = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();

			if ( 'core/block' === $name ) {
				$references[] = array(
					'kind'         => 'synced_pattern',
					'source_type'  => $source_type,
					'source_id'    => $source_id,
					'source_slug'  => $source_slug,
					'source_title' => $source_title,
					'block_name'   => $name,
					'path'         => $current_path,
					'target_ref'   => isset( $attrs['ref'] ) ? (int) $attrs['ref'] : 0,
					'attrs'        => $attrs,
				);
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$walker( $node['inner_blocks'], $current_path );
			}
		}
	};

	$walker( $blocks );

	return $references;
}

/**
 * Return a synced-pattern reference graph across common block entities.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_synced_pattern_reference_graph(): array {
	$post_types = array( 'page', 'post', 'wp_template', 'wp_template_part', 'wp_navigation' );
	$posts      = get_posts(
		array(
			'post_type'              => $post_types,
			'post_status'            => array( 'publish', 'draft' ),
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$references = array();
	foreach ( $posts as $post ) {
		$content = (string) $post->post_content;
		if ( '' === $content || false === strpos( $content, '<!-- wp:block' ) ) {
			continue;
		}

		$blocks = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) );
		$references = array_merge(
			$references,
			mcp_abilities_gutenberg_collect_synced_pattern_references(
				$blocks,
				(string) $post->post_type,
				(int) $post->ID,
				(string) $post->post_name,
				get_the_title( $post )
			)
		);
	}

	return array(
		'entity_count'    => count( $posts ),
		'reference_count' => count( $references ),
		'references'      => $references,
	);
}

/**
 * Find synced-pattern usage across posts, pages, templates, parts, and navigations.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_find_synced_pattern_usage( array $input ) {
	$graph      = mcp_abilities_gutenberg_get_synced_pattern_reference_graph();
	$references = is_array( $graph['references'] ?? null ) ? $graph['references'] : array();
	$pattern    = null;

	if ( isset( $input['post_id'] ) ) {
		$pattern = mcp_abilities_gutenberg_get_synced_pattern(
			array(
				'post_id' => (int) $input['post_id'],
			)
		);
	} elseif ( isset( $input['slug'] ) ) {
		$pattern = mcp_abilities_gutenberg_get_synced_pattern(
			array(
				'slug' => (string) $input['slug'],
			)
		);
	}

	if ( is_wp_error( $pattern ) ) {
		return $pattern;
	}

	$target_id = is_array( $pattern ) ? (int) ( $pattern['id'] ?? 0 ) : 0;
	if ( $target_id <= 0 ) {
		return new WP_Error( 'mcp_gutenberg_synced_pattern_not_found', 'Synced pattern not found.' );
	}

	$matches = array_values(
		array_filter(
			$references,
			static function ( array $reference ) use ( $target_id ): bool {
				return 'synced_pattern' === (string) ( $reference['kind'] ?? '' ) && (int) ( $reference['target_ref'] ?? 0 ) === $target_id;
			}
		)
	);

	return array(
		'pattern'     => $pattern,
		'matches'     => $matches,
		'match_count' => count( $matches ),
	);
}

/**
 * Return compact media catalog data.
 *
 * @param array<string,mixed> $input Input query options.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_media_catalog( array $input ): array {
	$search = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
	$limit  = isset( $input['limit'] ) ? max( 1, min( 50, (int) $input['limit'] ) ) : 20;

	$posts = get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => $limit,
			's'                      => $search,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$items = array();
	foreach ( $posts as $post ) {
		$items[] = array(
			'id'         => (int) $post->ID,
			'title'      => get_the_title( $post ),
			'slug'       => (string) $post->post_name,
			'mime_type'  => (string) $post->post_mime_type,
			'alt'        => (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'caption'    => (string) $post->post_excerpt,
			'description'=> (string) $post->post_content,
			'url'        => (string) wp_get_attachment_url( $post->ID ),
			'thumbnail'  => (string) wp_get_attachment_image_url( $post->ID, 'medium' ),
			'metadata'   => wp_get_attachment_metadata( $post->ID ) ?: array(),
		);
	}

	return $items;
}

/**
 * Collect heading outline from a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_outline( array $blocks ): array {
	$outline = array();

	foreach ( $blocks as $block ) {
		$name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
		if ( 'core/heading' === $name ) {
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$text  = trim( wp_strip_all_tags( (string) ( $block['inner_html'] ?? '' ) ) );
			$outline[] = array(
				'level' => isset( $attrs['level'] ) ? (int) $attrs['level'] : 2,
				'text'  => $text,
			);
		}

		if ( ! empty( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
			$outline = array_merge( $outline, mcp_abilities_gutenberg_collect_outline( $block['inner_blocks'] ) );
		}
	}

	return $outline;
}

/**
 * Collect links from a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @return array<int,string>
 */
function mcp_abilities_gutenberg_collect_links( array $blocks ): array {
	$links = array();

	foreach ( $blocks as $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		foreach ( array( 'url', 'href' ) as $key ) {
			if ( ! empty( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
				$links[] = $attrs[ $key ];
			}
		}

		$inner_html = isset( $block['inner_html'] ) ? (string) $block['inner_html'] : '';
		if ( '' !== $inner_html && preg_match_all( '/href=[\'"]([^\'"]+)[\'"]/', $inner_html, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$links[] = (string) $url;
			}
		}

		if ( ! empty( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
			$links = array_merge( $links, mcp_abilities_gutenberg_collect_links( $block['inner_blocks'] ) );
		}
	}

	return array_values( array_unique( array_filter( array_map( 'strval', $links ) ) ) );
}

/**
 * Collect media references from a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_media_refs( array $blocks ): array {
	$items = array();

	foreach ( $blocks as $block ) {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$name  = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';

		if ( in_array( $name, array( 'core/image', 'core/cover', 'core/media-text', 'core/gallery' ), true ) ) {
			$item = array(
				'block_name'     => $name,
				'attachment_id'  => isset( $attrs['id'] ) ? (int) $attrs['id'] : 0,
				'url'            => isset( $attrs['url'] ) ? (string) $attrs['url'] : '',
				'media_link'     => isset( $attrs['linkDestination'] ) ? (string) $attrs['linkDestination'] : '',
				'alt'            => isset( $attrs['alt'] ) ? (string) $attrs['alt'] : '',
			);
			$items[] = $item;
		}

		if ( ! empty( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
			$items = array_merge( $items, mcp_abilities_gutenberg_collect_media_refs( $block['inner_blocks'] ) );
		}
	}

	return $items;
}

/**
 * Count block usage recursively.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @return array<string,int>
 */
function mcp_abilities_gutenberg_collect_block_usage( array $blocks ): array {
	$usage = array();

	foreach ( $blocks as $block ) {
		$name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
		if ( '' !== $name ) {
			$usage[ $name ] = isset( $usage[ $name ] ) ? $usage[ $name ] + 1 : 1;
		}

		if ( ! empty( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
			foreach ( mcp_abilities_gutenberg_collect_block_usage( $block['inner_blocks'] ) as $inner_name => $count ) {
				$usage[ $inner_name ] = isset( $usage[ $inner_name ] ) ? $usage[ $inner_name ] + $count : $count;
			}
		}
	}

	ksort( $usage );

	return $usage;
}

/**
 * Build a richer analysis payload for block content.
 *
 * @param string $content Content to inspect.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_analyze_content( string $content ): array {
	$normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) );

	return array(
		'summary'     => mcp_abilities_gutenberg_content_summary( $content ),
		'validation'  => mcp_abilities_gutenberg_validate_content( $content ),
		'outline'     => mcp_abilities_gutenberg_collect_outline( $normalized ),
		'links'       => mcp_abilities_gutenberg_collect_links( $normalized ),
		'media'       => mcp_abilities_gutenberg_collect_media_refs( $normalized ),
		'block_usage' => mcp_abilities_gutenberg_collect_block_usage( $normalized ),
		'blocks'      => $normalized,
	);
}

/**
 * Check whether a DOM element has meaningful rendered content.
 *
 * @param DOMElement $element Element to inspect.
 * @return bool
 */
function mcp_abilities_gutenberg_dom_element_has_meaningful_content( DOMElement $element ): bool {
	$meaningful_tags = array( 'img', 'picture', 'video', 'iframe', 'canvas', 'svg', 'form', 'input', 'textarea', 'select', 'button' );
	$tag_name        = strtolower( $element->tagName );

	if ( in_array( $tag_name, $meaningful_tags, true ) ) {
		return true;
	}

	foreach ( $meaningful_tags as $meaningful_tag ) {
		if ( $element->getElementsByTagName( $meaningful_tag )->length > 0 ) {
			return true;
		}
	}

	return '' !== trim( preg_replace( '/\s+/u', ' ', (string) $element->textContent ) );
}

/**
 * Return a short CSS-like selector for an element.
 *
 * @param DOMElement $element Element to describe.
 * @return string
 */
function mcp_abilities_gutenberg_describe_dom_element( DOMElement $element ): string {
	$selector = strtolower( $element->tagName );
	$id       = $element->getAttribute( 'id' );
	if ( '' !== $id ) {
		$selector .= '#' . $id;
	}

	$class_name = trim( preg_replace( '/\s+/u', ' ', $element->getAttribute( 'class' ) ) );
	if ( '' !== $class_name ) {
		$classes = array_slice(
			array_values(
				array_filter(
					explode( ' ', $class_name ),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			),
			0,
			3
		);
		foreach ( $classes as $class ) {
			$selector .= '.' . $class;
		}
	}

	return $selector;
}

/**
 * Return significant direct child elements.
 *
 * @param DOMElement $element Parent element.
 * @return array<int,DOMElement>
 */
function mcp_abilities_gutenberg_get_significant_child_elements( DOMElement $element ): array {
	$children = array();
	foreach ( $element->childNodes as $child ) {
		if ( ! $child instanceof DOMElement ) {
			continue;
		}
		if ( ! mcp_abilities_gutenberg_dom_element_has_meaningful_content( $child ) ) {
			continue;
		}
		$children[] = $child;
	}

	return $children;
}

/**
 * Return the previous significant element sibling.
 *
 * @param DOMElement $element Element to inspect.
 * @return DOMElement|null
 */
function mcp_abilities_gutenberg_get_previous_significant_element_sibling( DOMElement $element ): ?DOMElement {
	$node = $element->previousSibling;
	while ( $node ) {
		if ( $node instanceof DOMElement && mcp_abilities_gutenberg_dom_element_has_meaningful_content( $node ) ) {
			return $node;
		}
		$node = $node->previousSibling;
	}

	return null;
}

/**
 * Build a compact CSS snippet for diagnostics.
 *
 * @param string $snippet Raw CSS snippet.
 * @return string
 */
function mcp_abilities_gutenberg_compact_css_snippet( string $snippet ): string {
	$snippet = trim( preg_replace( '/\s+/u', ' ', $snippet ) );

	if ( strlen( $snippet ) > 160 ) {
		$snippet = substr( $snippet, 0, 157 ) . '...';
	}

	return $snippet;
}

/**
 * Parse a CSS color token into RGB components.
 *
 * @param string $value CSS color token.
 * @return array{r:int,g:int,b:int}|null
 */
function mcp_abilities_gutenberg_parse_css_color( string $value ) {
	$value = strtolower( trim( preg_replace( '/\s*!important\s*$/i', '', $value ) ) );
	if ( '' === $value ) {
		return null;
	}

	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $matches ) ) {
		$hex = strtolower( $matches[1] );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	if ( preg_match( '/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value, $matches ) ) {
		return array(
			'r' => max( 0, min( 255, (int) $matches[1] ) ),
			'g' => max( 0, min( 255, (int) $matches[2] ) ),
			'b' => max( 0, min( 255, (int) $matches[3] ) ),
		);
	}

	return null;
}

/**
 * Convert simple CSS/Gutenberg spacing values into approximate pixels.
 *
 * @param string $value Raw value.
 * @return float|null
 */
function mcp_abilities_gutenberg_parse_spacing_value_to_px( string $value ): ?float {
	$value = strtolower( trim( $value ) );
	if ( '' === $value ) {
		return null;
	}

	if ( preg_match( '/^([0-9]+(?:\.[0-9]+)?)(px|rem)$/', $value, $matches ) ) {
		$amount = (float) $matches[1];
		$unit   = (string) $matches[2];

		return 'rem' === $unit ? $amount * 16 : $amount;
	}

	return null;
}

/**
 * Extract color stops from a CSS gradient or background string.
 *
 * @param string $value Background value.
 * @return array<int,array{r:int,g:int,b:int}>
 */
function mcp_abilities_gutenberg_extract_css_color_stops( string $value ): array {
	$colors = array();

	if ( preg_match_all( '/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b|rgba?\([^)]+\)/i', $value, $matches ) ) {
		foreach ( $matches[0] as $token ) {
			$rgb = mcp_abilities_gutenberg_parse_css_color( (string) $token );
			if ( is_array( $rgb ) ) {
				$colors[] = $rgb;
			}
		}
	}

	return $colors;
}

/**
 * Extract meaningful background color stops for readability checks, ignoring very transparent decoration.
 *
 * @param string $value Background value.
 * @return array<int,array{r:int,g:int,b:int}>
 */
function mcp_abilities_gutenberg_extract_css_readability_color_stops( string $value ): array {
	$colors = array();

	if ( ! preg_match_all( '/#(?:[0-9a-f]{3}|[0-9a-f]{6})\b|rgba?\([^)]+\)/i', $value, $matches ) ) {
		return $colors;
	}

	foreach ( $matches[0] as $token ) {
		$token = trim( (string) $token );
		if ( preg_match( '/^rgba\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*(0|1|0?\.\d+)\s*\)$/i', $token, $rgba ) ) {
			$alpha = (float) $rgba[4];
			if ( $alpha < 0.2 ) {
				continue;
			}
		}

		$rgb = mcp_abilities_gutenberg_parse_css_color( $token );
		if ( is_array( $rgb ) ) {
			$colors[] = $rgb;
		}
	}

	return $colors;
}

/**
 * Normalize a hero selector so shell duplicates collapse to one comparable form.
 *
 * @param string $selector Raw selector.
 * @return string
 */
function mcp_abilities_gutenberg_canonicalize_hero_selector( string $selector ): string {
	$selector = strtolower( trim( $selector ) );
	$selector = str_replace( '.page-content>', '>', $selector );
	$selector = str_replace( '.entry-content>', '>', $selector );
	$selector = preg_replace( '/\s+/', ' ', $selector );
	return trim( (string) $selector );
}

/**
 * Count selector complexity roughly by descendant depth/combinators.
 *
 * @param string $selector CSS selector.
 * @return int
 */
function mcp_abilities_gutenberg_measure_selector_complexity( string $selector ): int {
	$selector = preg_replace( '/\s+/', ' ', trim( $selector ) );
	if ( '' === $selector ) {
		return 0;
	}

	return substr_count( $selector, ' ' ) + substr_count( $selector, '>' ) + substr_count( $selector, '+' ) + substr_count( $selector, '~' );
}

/**
 * Convert sRGB channel to linear light.
 *
 * @param float $channel Channel value 0-1.
 * @return float
 */
function mcp_abilities_gutenberg_linearize_channel( float $channel ): float {
	return ( $channel <= 0.03928 ) ? ( $channel / 12.92 ) : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
}

/**
 * Compute relative luminance for an RGB color.
 *
 * @param array{r:int,g:int,b:int} $rgb RGB values.
 * @return float
 */
function mcp_abilities_gutenberg_relative_luminance( array $rgb ): float {
	$r = mcp_abilities_gutenberg_linearize_channel( $rgb['r'] / 255 );
	$g = mcp_abilities_gutenberg_linearize_channel( $rgb['g'] / 255 );
	$b = mcp_abilities_gutenberg_linearize_channel( $rgb['b'] / 255 );

	return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Compute WCAG contrast ratio between two colors.
 *
 * @param array{r:int,g:int,b:int} $foreground Foreground RGB.
 * @param array{r:int,g:int,b:int} $background Background RGB.
 * @return float
 */
function mcp_abilities_gutenberg_contrast_ratio( array $foreground, array $background ): float {
	$l1 = mcp_abilities_gutenberg_relative_luminance( $foreground );
	$l2 = mcp_abilities_gutenberg_relative_luminance( $background );

	$light = max( $l1, $l2 );
	$dark  = min( $l1, $l2 );

	return ( $light + 0.05 ) / ( $dark + 0.05 );
}

/**
 * Extract CSS property values from a declaration string.
 *
 * @param string $declarations Raw declaration block.
 * @param array<int,string> $properties Properties to inspect.
 * @return array<string,string>
 */
function mcp_abilities_gutenberg_extract_css_property_values( string $declarations, array $properties ): array {
	$values = array();

	foreach ( $properties as $property ) {
		$property = strtolower( trim( $property ) );
		if ( '' === $property ) {
			continue;
		}

		if ( preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*([^;]+)\s*(?:;|$)/i', $declarations, $matches ) ) {
			$values[ $property ] = trim( preg_replace( '/\s*!important\s*$/i', '', (string) $matches[1] ) );
		}
	}

	return $values;
}

/**
 * Return whether a design issue should fail a page-level demo/design acceptance check.
 *
 * @param string $type Issue type.
 * @return bool
 */
function mcp_abilities_gutenberg_is_blocking_design_issue( string $type ): bool {
	return in_array(
		$type,
		array(
			'section_width_inconsistency_risk',
			'internal_measure_mismatch',
			'support_module_cramp_risk',
			'followup_cluster_detachment_risk',
			'fullwidth_section_seam_gap_risk',
			'row_treatment_inconsistency',
			'repeated_object_treatment_inconsistency',
			'noninteractive_control_affordance_risk',
			'button_contrast_risk',
			'hero_heading_readability_risk',
		),
		true
	);
}

/**
 * Count words in a rendered text snippet.
 *
 * @param string $text Raw text.
 * @return int
 */
function mcp_abilities_gutenberg_count_words( string $text ): int {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( '' === $text ) {
		return 0;
	}

	$parts = preg_split( '/\s+/u', $text );
	return is_array( $parts ) ? count( array_filter( $parts, 'strlen' ) ) : 0;
}

/**
 * Extract layout-risk issues from CSS text.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_layout_risks( string $css, string $source ): array {
	$issues = array();
	$checks = array(
		array(
			'type'     => 'layout_overlap_risk',
			'severity' => 'warning',
			'pattern'  => '/(?:margin-top\s*:\s*-\s*[^;{}]+|margin\s*:\s*-\s*[^;{}]+)/i',
			'message'  => 'CSS uses a negative margin declaration; this can pull one section into another and create visible overlap.',
		),
		array(
			'type'     => 'scroll_region_risk',
			'severity' => 'warning',
			'pattern'  => '/overflow(?:-x|-y)?\s*:\s*(?:auto|scroll)\b/i',
			'message'  => 'CSS creates an explicit scroll region; this can introduce unintended internal scrollbars or scroll leakage.',
		),
		array(
			'type'     => 'viewport_overflow_risk',
			'severity' => 'warning',
			'pattern'  => '/(?:width|min-width|max-width)\s*:\s*(?:100vw|calc\([^;{}]*50vw[^;{}]*\)|1\d{2,}vw)\b/i',
			'message'  => 'CSS uses viewport-width sizing that often causes horizontal overflow when combined with page padding or transforms.',
		),
		// Large translate offsets are not automatically broken, but they are a common source of visual collisions.
		array(
			'type'     => 'transform_offset_risk',
			'severity' => 'warning',
			'pattern'  => '/transform\s*:\s*[^;{}]*translate(?:X|Y)?\(\s*-[^)]+|\btransform\s*:\s*[^;{}]*translate(?:X|Y)?\(\s*[1-9][0-9.]*rem/i',
			'message'  => 'CSS uses a translate offset; large offsets can detach content from its flow box and create overlaps or clipping.',
		),
		array(
			'type'     => 'position_overlay_risk',
			'severity' => 'warning',
			'pattern'  => '/position\s*:\s*(?:absolute|fixed)\b(?:(?![{}]).)*(?:top|right|bottom|left|inset)\s*:/i',
			'message'  => 'CSS uses absolute or fixed positioning with explicit offsets; this commonly creates overlays that break editor and frontend layout flow.',
		),
	);

	foreach ( $checks as $check ) {
		if ( preg_match_all( $check['pattern'], $css, $matches ) ) {
			$snippets = array_values(
				array_unique(
					array_map(
						'mcp_abilities_gutenberg_compact_css_snippet',
						array_slice( $matches[0], 0, 3 )
					)
				)
			);

			$issues[] = array(
				'type'      => $check['type'],
				'severity'  => $check['severity'],
				'source'    => $source,
				'count'     => count( $matches[0] ),
				'snippets'  => $snippets,
				'message'   => $check['message'],
			);
		}
	}

	return $issues;
}

/**
 * Collect layout-risk issues from inline style attributes inside a DOM scope.
 *
 * @param DOMXPath   $xpath XPath helper.
 * @param DOMElement $scope Scope element.
 * @return array{issues: array<int,array<string,mixed>>, count: int}
 */
function mcp_abilities_gutenberg_collect_inline_style_layout_risks( DOMXPath $xpath, DOMElement $scope ): array {
	$issues          = array();
	$inline_style_count = 0;
	$style_nodes     = $xpath->query( './/*[@style]', $scope );

	if ( ! $style_nodes instanceof DOMNodeList || 0 === $style_nodes->length ) {
		return array(
			'issues' => array(),
			'count'  => 0,
		);
	}

	for ( $index = 0; $index < $style_nodes->length; $index++ ) {
		$style_node = $style_nodes->item( $index );
		if ( ! $style_node instanceof DOMElement ) {
			continue;
		}

		$style = trim( (string) $style_node->getAttribute( 'style' ) );
		if ( '' === $style ) {
			continue;
		}

		++$inline_style_count;
		$selector = mcp_abilities_gutenberg_describe_dom_element( $style_node );
		$node_issues = mcp_abilities_gutenberg_collect_css_layout_risks(
			$style,
			sprintf( 'inline-style:%s', $selector )
		);

		foreach ( $node_issues as $node_issue ) {
			$node_issue['selector'] = $selector;
			$issues[] = $node_issue;
		}
	}

	return array(
		'issues' => $issues,
		'count'  => $inline_style_count,
	);
}

/**
 * Determine whether a CSS selector likely targets a visible Gutenberg content frame.
 *
 * @param string $selector CSS selector text.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_targets_content_measure( string $selector ): bool {
	$selector = strtolower( trim( $selector ) );
	if ( '' === $selector ) {
		return false;
	}

	$signals = array(
		'.wp-block-group',
		'.alignfull',
		'.wp-block-columns',
		'.wp-block-column',
		'.wp-block-quote',
		'.wp-block-cover__inner-container',
		'.page-content',
		'.entry-content',
		'nth-child(',
		'>*',
		'> *',
	);

	foreach ( $signals as $signal ) {
		if ( false !== strpos( $selector, $signal ) ) {
			return true;
		}
	}

	if ( preg_match( '/\.(?:[a-z0-9_-]*)(shell|container|wrapper|wrap)(?:[a-z0-9_-]*)/i', $selector ) ) {
		return true;
	}

	return false;
}

/**
 * Extract fixed content-measure declarations from CSS.
 *
 * @param string   $declarations CSS declaration block.
 * @param string[] $allowed_units Allowed measurement units.
 * @return array<int,array{property:string,value:float,unit:string}>
 */
function mcp_abilities_gutenberg_extract_css_measure_declarations( string $declarations, array $allowed_units ): array {
	$measures = array();

	if ( '' === trim( $declarations ) ) {
		return $measures;
	}

	if ( ! preg_match_all( '/\b(max-width|width)\s*:\s*([^;]+)\s*;?/i', $declarations, $matches, PREG_SET_ORDER ) ) {
		return $measures;
	}

	foreach ( $matches as $match ) {
		$property   = strtolower( (string) ( $match[1] ?? '' ) );
		$expression = (string) ( $match[2] ?? '' );
		if ( '' === $property || '' === trim( $expression ) ) {
			continue;
		}

		if ( ! preg_match( '/([0-9]+(?:\.[0-9]+)?)(px|rem|ch)\b/i', $expression, $value_match ) ) {
			continue;
		}

		$unit = strtolower( (string) ( $value_match[2] ?? '' ) );
		if ( '' === $unit || ! in_array( $unit, $allowed_units, true ) ) {
			continue;
		}

		$value = (float) ( $value_match[1] ?? 0 );
		if ( $value <= 0 ) {
			continue;
		}

		$measures[] = array(
			'property' => $property,
			'value'    => $value,
			'unit'     => $unit,
		);
	}

	return $measures;
}

/**
 * Extract fixed content-measure declarations from CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_content_measures( string $css, string $source ): array {
	$measures = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $measures;
	}

	foreach ( $rules as $rule ) {
		$selector     = trim( (string) ( $rule[1] ?? '' ) );
		$declarations = (string) ( $rule[2] ?? '' );
		if ( '' === $selector || '' === trim( $declarations ) ) {
			continue;
		}

		if ( ! mcp_abilities_gutenberg_selector_targets_content_measure( $selector ) ) {
			continue;
		}

		foreach ( mcp_abilities_gutenberg_extract_css_measure_declarations( $declarations, array( 'px', 'rem' ) ) as $match ) {
			$property = (string) $match['property'];
			$value    = (float) $match['value'];
			$unit     = (string) $match['unit'];

			$value_px = 'rem' === $unit ? $value * 16 : $value;
			if ( $value_px < 320 || $value_px > 1800 ) {
				continue;
			}

			$measures[] = array(
				'source'        => $source,
				'selector'      => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'property'      => $property,
				'value'         => rtrim( rtrim( sprintf( '%.2f', $value ), '0' ), '.' ) . $unit,
				'normalized_px' => (int) round( $value_px ),
			);
		}
	}

	return $measures;
}

/**
 * Check whether a CSS selector targets a text-level measure inside a larger section.
 *
 * @param string $selector CSS selector.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_targets_text_measure( string $selector ): bool {
	$selector = strtolower( trim( $selector ) );
	if ( '' === $selector ) {
		return false;
	}

	$signals = array(
		'.wp-block-quote p',
		'.wp-block-quote blockquote',
		'.wp-block-quote cite',
		'.wp-block-paragraph',
		' blockquote',
		' blockquote p',
		' p',
		' cite',
		' h1',
		' h2',
		' h3',
		' h4',
		' h5',
		' h6',
	);

	foreach ( $signals as $signal ) {
		if ( false !== strpos( $selector, $signal ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether a CSS selector targets a nested structural container inside a larger section.
 *
 * @param string $selector CSS selector.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_targets_nested_container_measure( string $selector ): bool {
	$selector = strtolower( trim( $selector ) );
	if ( '' === $selector ) {
		return false;
	}

	$signals = array(
		'.wp-block-columns',
		'.wp-block-column',
		'.wp-block-group',
		'.wp-block-cover__inner-container',
	);

	foreach ( $signals as $signal ) {
		if ( false !== strpos( $selector, $signal ) ) {
			return true;
		}
	}

	if ( preg_match( '/\.(?:[a-z0-9_-]*)(shell|container|wrapper|wrap)(?:[a-z0-9_-]*)/i', $selector ) ) {
		return true;
	}

	return false;
}

/**
 * Extract nested text-measure declarations from CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_text_measures( string $css, string $source ): array {
	$measures = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $measures;
	}

	foreach ( $rules as $rule ) {
		$selector     = trim( (string) ( $rule[1] ?? '' ) );
		$declarations = (string) ( $rule[2] ?? '' );
		if ( '' === $selector || '' === trim( $declarations ) ) {
			continue;
		}

		if ( ! mcp_abilities_gutenberg_selector_targets_text_measure( $selector ) ) {
			continue;
		}

		foreach ( mcp_abilities_gutenberg_extract_css_measure_declarations( $declarations, array( 'px', 'rem', 'ch' ) ) as $match ) {
			$property = (string) $match['property'];
			$value    = (float) $match['value'];
			$unit     = (string) $match['unit'];

			if ( 'rem' === $unit ) {
				$value_px = $value * 16;
			} elseif ( 'ch' === $unit ) {
				$value_px = $value * 8;
			} else {
				$value_px = $value;
			}

			if ( $value_px < 220 || $value_px > 1200 ) {
				continue;
			}

			$measures[] = array(
				'source'        => $source,
				'selector'      => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'property'      => $property,
				'value'         => rtrim( rtrim( sprintf( '%.2f', $value ), '0' ), '.' ) . $unit,
				'kind'          => 'text',
				'normalized_px' => (int) round( $value_px ),
			);
		}
	}

	return $measures;
}

/**
 * Extract nested structural-measure declarations from CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_nested_container_measures( string $css, string $source ): array {
	$measures = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $measures;
	}

	foreach ( $rules as $rule ) {
		$selector     = trim( (string) ( $rule[1] ?? '' ) );
		$declarations = (string) ( $rule[2] ?? '' );
		if ( '' === $selector || '' === trim( $declarations ) ) {
			continue;
		}

		if ( ! mcp_abilities_gutenberg_selector_targets_nested_container_measure( $selector ) ) {
			continue;
		}

		if ( mcp_abilities_gutenberg_selector_targets_text_measure( $selector ) ) {
			continue;
		}

		foreach ( mcp_abilities_gutenberg_extract_css_measure_declarations( $declarations, array( 'px', 'rem' ) ) as $match ) {
			$property = (string) $match['property'];
			$value    = (float) $match['value'];
			$unit     = (string) $match['unit'];

			$value_px = 'rem' === $unit ? $value * 16 : $value;
			if ( $value_px < 320 || $value_px > 1800 ) {
				continue;
			}

			$measures[] = array(
				'source'        => $source,
				'selector'      => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'property'      => $property,
				'value'         => rtrim( rtrim( sprintf( '%.2f', $value ), '0' ), '.' ) . $unit,
				'kind'          => 'container',
				'normalized_px' => (int) round( $value_px ),
			);
		}
	}

	return $measures;
}

/**
 * Turn extracted content measures into a width-system issue when they drift.
 *
 * @param array<int,array<string,mixed>> $measures Extracted measure declarations.
 * @param string                         $source   Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_width_system_risks( array $measures, string $source ): array {
	if ( count( $measures ) < 2 ) {
		return array();
	}

	$buckets = array();
	foreach ( $measures as $measure ) {
		$normalized_px = isset( $measure['normalized_px'] ) ? (int) $measure['normalized_px'] : 0;
		if ( $normalized_px <= 0 ) {
			continue;
		}

		$bucket_key = (string) ( (int) round( $normalized_px / 8 ) * 8 );
		if ( ! isset( $buckets[ $bucket_key ] ) ) {
			$buckets[ $bucket_key ] = array(
				'normalized_px' => (int) round( $normalized_px / 8 ) * 8,
				'values'        => array(),
				'selectors'     => array(),
			);
		}

		$buckets[ $bucket_key ]['values'][]    = (string) ( $measure['value'] ?? '' );
		$buckets[ $bucket_key ]['selectors'][] = (string) ( $measure['selector'] ?? '' );
	}

	if ( count( $buckets ) < 2 ) {
		return array();
	}

	$values    = array();
	$selectors = array();
	foreach ( $buckets as $bucket ) {
		$values[] = sprintf(
			'%dpx (%s)',
			(int) $bucket['normalized_px'],
			implode( ', ', array_slice( array_values( array_unique( array_filter( $bucket['values'] ) ) ), 0, 2 ) )
		);
		$selectors = array_merge( $selectors, array_slice( array_values( array_unique( array_filter( $bucket['selectors'] ) ) ), 0, 2 ) );
	}

	return array(
		array(
			'type'      => 'section_width_inconsistency_risk',
			'severity'  => 'warning',
			'source'    => $source,
			'values'    => array_values( array_unique( $values ) ),
			'selectors' => array_values( array_unique( array_slice( $selectors, 0, 6 ) ) ),
			'message'   => 'Embedded Gutenberg styling defines multiple fixed content measures across major section wrappers. Pages usually feel more coherent when intro panels, cards, quotes, reusable rows, and CTA sections share one primary content width and only intentional full-bleed sections break it.',
		),
	);
}

/**
 * Turn nested text measures into a coherence issue when they drift too far from the primary section width.
 *
 * @param array<int,array<string,mixed>> $content_measures Extracted section-level measures.
 * @param array<int,array<string,mixed>> $text_measures Extracted text-level measures.
 * @param string                         $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_internal_measure_mismatch_risks( array $content_measures, array $inner_measures, string $source ): array {
	if ( empty( $content_measures ) || empty( $inner_measures ) ) {
		return array();
	}

	$outer_values = array();
	foreach ( $content_measures as $measure ) {
		$normalized_px = isset( $measure['normalized_px'] ) ? (int) $measure['normalized_px'] : 0;
		if ( $normalized_px >= 880 ) {
			$outer_values[] = $normalized_px;
		}
	}

	if ( empty( $outer_values ) ) {
		return array();
	}

	rsort( $outer_values );
	$primary_outer = (int) $outer_values[0];
	$mismatched    = array();

	foreach ( $inner_measures as $measure ) {
		$normalized_px = isset( $measure['normalized_px'] ) ? (int) $measure['normalized_px'] : 0;
		$kind          = (string) ( $measure['kind'] ?? 'text' );
		if ( $normalized_px <= 0 ) {
			continue;
		}

		if ( $normalized_px > ( $primary_outer - 180 ) ) {
			continue;
		}

		if ( 'container' === $kind ) {
			if ( $normalized_px > (int) floor( $primary_outer * 0.82 ) ) {
				continue;
			}
		} else {
			if ( $normalized_px > (int) floor( $primary_outer * 0.72 ) ) {
				continue;
			}
		}

		$mismatched[] = $measure;
	}

	if ( empty( $mismatched ) ) {
		return array();
	}

	$selectors = array_values(
		array_unique(
			array_slice(
				array_filter( array_map( 'strval', wp_list_pluck( $mismatched, 'selector' ) ) ),
				0,
				6
			)
		)
	);
	$values = array_values(
		array_unique(
			array_slice(
				array_filter( array_map( 'strval', wp_list_pluck( $mismatched, 'value' ) ) ),
				0,
				6
			)
		)
	);
	$kinds = array_values(
		array_unique(
			array_filter(
				array_map(
					'strval',
					wp_list_pluck( $mismatched, 'kind' )
				)
			)
		)
	);

	return array(
		array(
			'type'             => 'internal_measure_mismatch',
			'severity'         => 'notice',
			'source'           => $source,
			'section_width_px' => $primary_outer,
			'measure_kinds'    => $kinds,
			'values'           => $values,
			'selectors'        => $selectors,
			'message'          => 'A major section uses the shared page width, but a nested row or text lane inside it is capped much narrower. That often leaves a fake empty lane and makes the section feel unfinished even when the outer wrapper is aligned correctly.',
		),
	);
}

/**
 * Detect support rows that drift too far below the CTA cluster they belong to.
 *
 * @param string $css Embedded CSS.
 * @param string $html Rendered HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_followup_cluster_detachment_issues( string $css, string $html, string $source ): array {
	if ( '' === trim( $css ) || '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$issues = array();
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = (string) ( $rule[2] ?? '' );
		if ( '' === $selector_block || '' === trim( $declarations ) ) {
			continue;
		}

		if ( ! preg_match( '/margin-top\s*:\s*([^;]+)\s*;/i', $declarations, $margin_match ) ) {
			continue;
		}

		$margin_raw = trim( (string) $margin_match[1] );
		$margin_px  = mcp_abilities_gutenberg_parse_spacing_value_to_px( $margin_raw );
		if ( ! is_float( $margin_px ) || $margin_px < 20 ) {
			continue;
		}

		foreach ( array_map( 'trim', explode( ',', $selector_block ) ) as $selector ) {
			if ( '' === $selector ) {
				continue;
			}

			$class_tokens = array_values(
				array_unique(
					array_filter(
						array_map(
							'strval',
							preg_match_all( '/\.([A-Za-z0-9_-]+)/', $selector, $class_matches ) ? $class_matches[1] : array()
						)
					)
				)
			);
			if ( empty( $class_tokens ) ) {
				continue;
			}

			$clusterish_selector = preg_match( '/(row|strip|grid|list|meta|proof|pill|chip|badge|items)/i', $selector );
			if ( ! $clusterish_selector ) {
				continue;
			}

			$query_parts = array();
			foreach ( $class_tokens as $class_token ) {
				$query_parts[] = sprintf(
					'contains(concat(" ", normalize-space(@class), " "), " %s ")',
					$class_token
				);
			}
			$nodes = $xpath->query( './/*[' . implode( ' and ', $query_parts ) . ']', $root );
			if ( ! $nodes instanceof DOMNodeList || 0 === $nodes->length ) {
				continue;
			}

			$matched_examples = array();
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof DOMElement ) {
					continue;
				}

				$children = mcp_abilities_gutenberg_get_significant_child_elements( $node );
				if ( count( $children ) < 2 ) {
					continue;
				}

				$previous = mcp_abilities_gutenberg_get_previous_significant_element_sibling( $node );
				if ( ! $previous instanceof DOMElement ) {
					continue;
				}

				$previous_classes = strtolower( (string) $previous->getAttribute( 'class' ) );
				$has_cta_cluster  = false;
				if ( false !== strpos( $previous_classes, 'wp-block-buttons' ) || false !== strpos( $previous_classes, 'wp-block-button' ) ) {
					$has_cta_cluster = true;
				} else {
					$cta_nodes = $xpath->query(
						'.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-buttons ") or contains(concat(" ", normalize-space(@class), " "), " wp-block-button ")]',
						$previous
					);
					$has_cta_cluster = $cta_nodes instanceof DOMNodeList && $cta_nodes->length > 0;
				}

				if ( ! $has_cta_cluster ) {
					continue;
				}

				$matched_examples[] = mcp_abilities_gutenberg_describe_dom_element( $node );
			}

			if ( empty( $matched_examples ) ) {
				continue;
			}

			$issues[] = array(
				'type'            => 'followup_cluster_detachment_risk',
				'severity'        => 'notice',
				'source'          => $source,
				'selector'        => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'selectors'       => array( mcp_abilities_gutenberg_compact_css_snippet( $selector ) ),
				'margin_top_px'   => (int) round( $margin_px ),
				'examples'        => array_values( array_unique( array_slice( $matched_examples, 0, 4 ) ) ),
				'message'         => 'A follow-up support cluster sits noticeably below the CTA cluster above it. When proof rows, metadata strips, or support items belong to the same selling moment, too much local gap makes them feel detached.',
			);
		}
	}

	return $issues;
}

/**
 * Detect visible seam gaps between adjacent full-width sections inside flow layouts.
 *
 * @param string $html Rendered HTML.
 * @param string $css Combined embedded CSS.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_rendered_fullwidth_seam_gap_issues( string $html, string $css, string $source ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-seam-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-seam-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$issues = array();
	$nodes  = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " is-layout-flow ")]', $root );
	if ( ! $nodes instanceof DOMNodeList ) {
		return array();
	}

	foreach ( $nodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$children = mcp_abilities_gutenberg_get_significant_child_elements( $node );
		if ( count( $children ) < 2 ) {
			continue;
		}

		$consecutive_pairs = array();
		for ( $index = 0; $index < count( $children ) - 1; $index++ ) {
			$current = $children[ $index ];
			$next    = $children[ $index + 1 ];
			$current_classes = ' ' . strtolower( trim( preg_replace( '/\s+/u', ' ', $current->getAttribute( 'class' ) ) ) ) . ' ';
			$next_classes    = ' ' . strtolower( trim( preg_replace( '/\s+/u', ' ', $next->getAttribute( 'class' ) ) ) ) . ' ';

			if ( false === strpos( $current_classes, ' alignfull ' ) || false === strpos( $next_classes, ' alignfull ' ) ) {
				continue;
			}

			$consecutive_pairs[] = array(
				mcp_abilities_gutenberg_describe_dom_element( $current ),
				mcp_abilities_gutenberg_describe_dom_element( $next ),
			);
		}

		if ( empty( $consecutive_pairs ) ) {
			continue;
		}

		$parent_selector = mcp_abilities_gutenberg_describe_dom_element( $node );
		$css_has_reset   = false;
		if ( '' !== trim( $css ) ) {
			$parent_classes = array_values(
				array_unique(
					array_filter(
						array_map(
							'strval',
							explode( ' ', trim( preg_replace( '/\s+/u', ' ', $node->getAttribute( 'class' ) ) ) )
						)
					)
				)
			);

			$css_normalized = strtolower( preg_replace( '/\s+/u', ' ', $css ) );
			$has_parent_hint = false;
			foreach ( $parent_classes as $parent_class ) {
				if ( '' !== $parent_class && false !== strpos( $css_normalized, '.' . strtolower( $parent_class ) ) ) {
					$has_parent_hint = true;
					break;
				}
			}

			if (
				$has_parent_hint &&
				preg_match( '/>\s*\*\s*\+\s*\*/', $css_normalized ) &&
				preg_match( '/margin-block-start\s*:\s*0(?:px)?|margin-top\s*:\s*0(?:px)?/', $css_normalized )
			) {
				$css_has_reset = true;
			}
		}

		if ( $css_has_reset ) {
			continue;
		}

		$examples = array();
		foreach ( array_slice( $consecutive_pairs, 0, 3 ) as $pair ) {
			$examples[] = $pair[0] . ' -> ' . $pair[1];
		}

		$issues[] = array(
			'type'      => 'fullwidth_section_seam_gap_risk',
			'severity'  => 'notice',
			'source'    => $source,
			'selector'  => $parent_selector,
			'selectors' => array( $parent_selector ),
			'examples'  => $examples,
			'message'   => 'Adjacent full-width sections sit inside a flow layout without an explicit seam reset. WordPress flow spacing can then leave a bright strip or visible gap between sections that should visually touch.',
		);
	}

	return $issues;
}

/**
 * Collect sibling-treatment inconsistencies from CSS selectors such as nth-child card styling.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_sibling_treatment_issues( string $css, string $source ): array {
	$groups = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = (string) ( $rule[2] ?? '' );
		if ( '' === $selector_block || '' === trim( $declarations ) ) {
			continue;
		}

		if ( ! preg_match( '/(?:transform\s*:|rotate\(|background\s*:|box-shadow\s*:|border-radius\s*:|border\s*:)/i', $declarations ) ) {
			continue;
		}

		$selectors = array_map( 'trim', explode( ',', $selector_block ) );
		foreach ( $selectors as $selector ) {
			if ( '' === $selector || ! preg_match( '/nth-child\(\s*(\d+)\s*\)/i', $selector, $matches ) ) {
				continue;
			}

			$index = (int) $matches[1];
			if ( $index <= 0 || $index > 6 ) {
				continue;
			}

			$group_key = strtolower( preg_replace( '/nth-child\(\s*\d+\s*\)/i', 'nth-child(*)', $selector ) );
			if ( '' === trim( $group_key ) ) {
				continue;
			}

			if ( ! isset( $groups[ $group_key ] ) ) {
				$groups[ $group_key ] = array(
					'indices'     => array(),
					'selectors'   => array(),
					'declarations'=> array(),
				);
			}

			$groups[ $group_key ]['indices'][]      = $index;
			$groups[ $group_key ]['selectors'][]    = mcp_abilities_gutenberg_compact_css_snippet( $selector );
			$groups[ $group_key ]['declarations'][] = mcp_abilities_gutenberg_compact_css_snippet( trim( $declarations ) );
		}
	}

	$issues = array();
	foreach ( $groups as $group_key => $group ) {
		$indices = array_values( array_unique( array_map( 'intval', $group['indices'] ) ) );
		sort( $indices );

		if ( count( $indices ) < 2 ) {
			continue;
		}

		$max_index = max( $indices );
		if ( $max_index < 3 ) {
			continue;
		}

		$expected = range( 1, $max_index );
		$missing  = array_values( array_diff( $expected, $indices ) );
		if ( empty( $missing ) ) {
			continue;
		}

		$issues[] = array(
			'type'        => 'sibling_treatment_inconsistency',
			'severity'    => 'notice',
			'source'      => $source,
			'selector'    => mcp_abilities_gutenberg_compact_css_snippet( $group_key ),
			'styled_items'=> $indices,
			'missing_items'=> $missing,
			'examples'    => array_values( array_unique( array_slice( $group['selectors'], 0, 3 ) ) ),
			'message'     => 'Sibling items in a repeated row use accent styling on only part of the set. If the row is meant to feel like one component family, make the treatment intentional across all siblings or clearly spotlight one item as the exception.',
		);
	}

	return $issues;
}

/**
 * Extract embedded CSS blocks directly from Gutenberg content.
 *
 * @param string $content Raw Gutenberg content.
 * @return array<int,array<string,string>>
 */
function mcp_abilities_gutenberg_extract_embedded_css_entries( string $content ): array {
	$entries = array();

	if ( ! preg_match_all( '#<style[^>]*>(.*?)</style>#is', $content, $matches, PREG_SET_ORDER ) ) {
		return $entries;
	}

	foreach ( $matches as $index => $match ) {
		$css = trim( (string) ( $match[1] ?? '' ) );
		if ( '' === $css ) {
			continue;
		}

		$entries[] = array(
			'source' => sprintf( 'embedded-style-block-%d', $index + 1 ),
			'css'    => $css,
		);
	}

	return $entries;
}

/**
 * Classify whether a selector-specific declaration tries to keep an object open or boxed.
 *
 * @param string $declarations CSS declarations.
 * @return string
 */
function mcp_abilities_gutenberg_classify_css_treatment( string $declarations ): string {
	$declarations_lc = strtolower( $declarations );

	$has_boxed_signal = false !== strpos( $declarations_lc, 'border-radius' )
		|| false !== strpos( $declarations_lc, 'box-shadow' )
		|| preg_match( '/background\s*:\s*(?!transparent\b)(?!none\b)/i', $declarations_lc )
		|| preg_match( '/background-color\s*:\s*(?!transparent\b)(?!none\b)/i', $declarations_lc )
		|| preg_match( '/border\s*:\s*(?!0\b)(?!none\b)/i', $declarations_lc )
		|| preg_match( '/border-(?:top|right|bottom|left)\s*:\s*(?!0\b)(?!none\b)/i', $declarations_lc );

	$has_open_signal = false !== strpos( $declarations_lc, 'background:transparent' )
		|| false !== strpos( $declarations_lc, 'background-color:transparent' )
		|| false !== strpos( $declarations_lc, 'border:0' )
		|| false !== strpos( $declarations_lc, 'border:none' )
		|| false !== strpos( $declarations_lc, 'box-shadow:none' );

	if ( $has_boxed_signal ) {
		return 'boxed';
	}
	if ( $has_open_signal ) {
		return 'open';
	}

	return 'neutral';
}

/**
 * Detect conflicting treatment applied to repeated sibling families through positional CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_repeated_object_treatment_issues( string $css, string $source ): array {
	$families = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = trim( (string) ( $rule[2] ?? '' ) );
		if ( '' === $selector_block || '' === $declarations ) {
			continue;
		}

		$selectors = array_map( 'trim', explode( ',', $selector_block ) );
		foreach ( $selectors as $selector ) {
			if ( '' === $selector ) {
				continue;
			}

			if ( ! preg_match( '/(:first-child|:last-child|nth-child\(\s*\d+\s*\))/i', $selector, $position_match ) ) {
				continue;
			}

			$treatment = mcp_abilities_gutenberg_classify_css_treatment( $declarations );
			if ( 'neutral' === $treatment ) {
				continue;
			}

			$family_key = strtolower(
				preg_replace(
					'/(?:\:first-child|\:last-child|nth-child\(\s*\d+\s*\))/i',
					':position(*)',
					$selector
				)
			);
			if ( '' === trim( $family_key ) ) {
				continue;
			}

			if ( ! isset( $families[ $family_key ] ) ) {
				$families[ $family_key ] = array(
					'boxed'    => array(),
					'open'     => array(),
					'examples' => array(),
				);
			}

			$families[ $family_key ][ $treatment ][] = mcp_abilities_gutenberg_compact_css_snippet( $selector );
			$families[ $family_key ]['examples'][]   = mcp_abilities_gutenberg_compact_css_snippet( $selector );
		}
	}

	$issues = array();
	foreach ( $families as $family_key => $family ) {
		if ( empty( $family['boxed'] ) || empty( $family['open'] ) ) {
			continue;
		}

		$issues[] = array(
			'type'        => 'repeated_object_treatment_inconsistency',
			'severity'    => 'notice',
			'source'      => $source,
			'selector'    => mcp_abilities_gutenberg_compact_css_snippet( $family_key ),
			'boxed_items' => array_values( array_unique( array_slice( $family['boxed'], 0, 4 ) ) ),
			'open_items'  => array_values( array_unique( array_slice( $family['open'], 0, 4 ) ) ),
			'examples'    => array_values( array_unique( array_slice( $family['examples'], 0, 6 ) ) ),
			'message'     => 'Matching sibling objects receive conflicting containment treatments through positional CSS. Repeated modules should not quietly switch between open and boxed states without a strong editorial reason.',
		);
	}

	return $issues;
}

/**
 * Detect weak button contrast in embedded Gutenberg CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_button_contrast_issues( string $css, string $source ): array {
	$issues = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $issues;
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = (string) ( $rule[2] ?? '' );
		if ( '' === $selector_block || '' === trim( $declarations ) ) {
			continue;
		}

		if ( false === strpos( strtolower( $selector_block ), 'wp-block-button__link' ) ) {
			continue;
		}

		$properties = mcp_abilities_gutenberg_extract_css_property_values(
			$declarations,
			array( 'color', 'background-color', 'background' )
		);
		$foreground = mcp_abilities_gutenberg_parse_css_color( $properties['color'] ?? '' );
		$background = mcp_abilities_gutenberg_parse_css_color( $properties['background-color'] ?? ( $properties['background'] ?? '' ) );

		if ( ! is_array( $foreground ) || ! is_array( $background ) ) {
			continue;
		}

		$contrast = mcp_abilities_gutenberg_contrast_ratio( $foreground, $background );
		if ( $contrast >= 3.5 ) {
			continue;
		}

		$issues[] = array(
			'type'           => 'button_contrast_risk',
			'severity'       => 'warning',
			'source'         => $source,
			'selector'       => mcp_abilities_gutenberg_compact_css_snippet( $selector_block ),
			'contrast_ratio' => round( $contrast, 2 ),
			'message'        => 'Button text and background colors are too close in contrast. This often makes CTAs look disabled or nearly invisible.',
		);
	}

	return $issues;
}

/**
 * Check whether a rendered element is interactive.
 *
 * @param DOMElement $element Element to inspect.
 * @return bool
 */
function mcp_abilities_gutenberg_dom_element_is_interactive( DOMElement $element ): bool {
	$tag = strtolower( $element->tagName );
	if ( in_array( $tag, array( 'a', 'button', 'input', 'select', 'textarea', 'summary' ), true ) ) {
		return true;
	}

	if ( '' !== trim( (string) $element->getAttribute( 'href' ) ) ) {
		return true;
	}

	$role = strtolower( trim( (string) $element->getAttribute( 'role' ) ) );
	if ( in_array( $role, array( 'button', 'link', 'tab', 'menuitem' ), true ) ) {
		return true;
	}

	$tabindex = trim( (string) $element->getAttribute( 'tabindex' ) );
	if ( '' !== $tabindex && '-1' !== $tabindex ) {
		return true;
	}

	if ( '' !== trim( (string) $element->getAttribute( 'onclick' ) ) ) {
		return true;
	}

	return false;
}

/**
 * Check whether a CSS selector explicitly targets interactive controls.
 *
 * @param string $selector Selector text.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_targets_interactive_controls( string $selector ): bool {
	$selector_lc = strtolower( trim( $selector ) );
	if ( '' === $selector_lc ) {
		return false;
	}

	return (bool) preg_match( '/(^|[\s>+~,])(?:a|button|input|select|textarea|summary)\b|\[role\s*=\s*["\']?(?:button|link|tab|menuitem)["\']?\]|wp-block-button|wp-element-button/', $selector_lc );
}

/**
 * Check whether a selector suggests badge/chip/tag treatment.
 *
 * @param string $selector Selector text.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_suggests_token_ui( string $selector ): bool {
	$selector_lc = strtolower( trim( $selector ) );
	if ( '' === $selector_lc ) {
		return false;
	}

	return (bool) preg_match( '/(?:pill|chip|badge|tag|token|label)/', $selector_lc );
}

/**
 * Check whether CSS declarations look like control-like inline token styling.
 *
 * @param string $declarations CSS declarations.
 * @return bool
 */
function mcp_abilities_gutenberg_css_looks_like_noninteractive_control_affordance( string $declarations ): bool {
	$declarations_lc = strtolower( $declarations );

	$has_radius = (bool) preg_match( '/border-radius\s*:\s*(?:999px|9999px|[1-9]\dpx|(?:1(?:\.[0-9]+)?|[2-9](?:\.[0-9]+)?)rem)/i', $declarations_lc );
	$has_padding = (bool) preg_match( '/padding(?:-[a-z]+)?\s*:\s*[^;]*(?:0\.[4-9]\d*|[1-9]\d*(?:\.\d+)?)\s*(?:rem|px)/i', $declarations_lc );
	$has_fill_or_border = (bool) preg_match( '/background(?:-color)?\s*:\s*(?!transparent\b)(?!none\b)|border(?:-[a-z]+)?\s*:\s*(?!0\b)(?!none\b)/i', $declarations_lc );
	$is_inlineish = false !== strpos( $declarations_lc, 'display:inline-flex' )
		|| false !== strpos( $declarations_lc, 'display:inline-block' )
		|| false !== strpos( $declarations_lc, 'display:inline-grid' );

	return $has_radius && $has_padding && $has_fill_or_border && $is_inlineish;
}

/**
 * Detect non-interactive tokens that are styled with button-like affordances.
 *
 * @param string $css CSS to inspect.
 * @param string $html Rendered HTML to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_noninteractive_control_affordance_issues( string $css, string $html, string $source ): array {
	if ( '' === trim( $css ) || '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	$issues = array();
	$seen   = array();

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = trim( (string) ( $rule[2] ?? '' ) );
		if ( '' === $selector_block || '' === $declarations ) {
			continue;
		}

		if ( ! mcp_abilities_gutenberg_css_looks_like_noninteractive_control_affordance( $declarations ) ) {
			continue;
		}

		$selectors = array_map( 'trim', explode( ',', $selector_block ) );
		foreach ( $selectors as $selector ) {
			if ( '' === $selector || mcp_abilities_gutenberg_selector_targets_interactive_controls( $selector ) ) {
				continue;
			}

			$class_matches = array();
			preg_match_all( '/\.([a-zA-Z0-9_-]+)/', $selector, $class_matches );
			$class_names = array_values( array_unique( array_map( 'strval', $class_matches[1] ?? array() ) ) );
			if ( empty( $class_names ) ) {
				continue;
			}

			$noninteractive_nodes = array();
			foreach ( $class_names as $class_name ) {
				$nodes = $xpath->query(
					sprintf( './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class_name ),
					$root
				);
				if ( ! $nodes instanceof DOMNodeList || 0 === $nodes->length ) {
					continue;
				}

				foreach ( $nodes as $node ) {
					if ( ! $node instanceof DOMElement ) {
						continue;
					}

					if ( mcp_abilities_gutenberg_dom_element_is_interactive( $node ) ) {
						continue;
					}

					$interactive_descendants = $xpath->query( './/a|.//button|.//input|.//select|.//textarea|.//*[@role="button"]|.//*[@role="link"]', $node );
					if ( $interactive_descendants instanceof DOMNodeList && $interactive_descendants->length > 0 ) {
						continue;
					}

					$noninteractive_nodes[] = $node;
				}
			}

			if ( empty( $noninteractive_nodes ) ) {
				continue;
			}

			$key = md5( $selector . '|' . $declarations );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$examples = array();
			foreach ( array_slice( $noninteractive_nodes, 0, 4 ) as $node ) {
				$examples[] = mcp_abilities_gutenberg_describe_dom_element( $node );
			}

			$issues[] = array(
				'type'          => 'noninteractive_control_affordance_risk',
				'severity'      => 'notice',
				'source'        => $source,
				'selector'      => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'examples'      => array_values( array_unique( $examples ) ),
				'count'         => count( $noninteractive_nodes ),
				'token_ui_hint' => mcp_abilities_gutenberg_selector_suggests_token_ui( $selector ),
				'message'       => 'Non-interactive labels are styled with chip/button-like affordances. If they are not real controls or links, they should read as metadata or proof, not as tappable actions.',
			);
		}
	}

	return $issues;
}

/**
 * Detect trailing bottom-gap styling on content wrappers.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_trailing_gap_issues( string $css, string $source ): array {
	$issues = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $issues;
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = (string) ( $rule[2] ?? '' );
		if ( '' === $selector_block || '' === trim( $declarations ) ) {
			continue;
		}

		$selector_lc = strtolower( $selector_block );
		if ( false === strpos( $selector_lc, '.page-content' ) && false === strpos( $selector_lc, '.entry-content' ) ) {
			continue;
		}

		$properties = mcp_abilities_gutenberg_extract_css_property_values(
			$declarations,
			array( 'padding-bottom' )
		);
		$value = (string) ( $properties['padding-bottom'] ?? '' );
		if ( '' === $value || ! preg_match( '/^([0-9]+(?:\.[0-9]+)?)(px|rem)$/i', $value, $matches ) ) {
			continue;
		}

		$amount = (float) $matches[1];
		$unit   = strtolower( (string) $matches[2] );
		$px     = 'rem' === $unit ? $amount * 16 : $amount;
		if ( $px < 24 ) {
			continue;
		}

		$issues[] = array(
			'type'        => 'trailing_content_gap_risk',
			'severity'    => 'notice',
			'source'      => $source,
			'selector'    => mcp_abilities_gutenberg_compact_css_snippet( $selector_block ),
			'value'       => rtrim( rtrim( sprintf( '%.2f', $amount ), '0' ), '.' ) . $unit,
			'normalized_px' => (int) round( $px ),
			'message'     => 'The main content wrapper adds notable bottom padding. This can leave a visible gap below the last section when the footer is hidden or minimal.',
		);
	}

	return $issues;
}

/**
 * Detect design-token sprawl in embedded Gutenberg CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_design_token_sprawl_issues( string $css, string $source ): array {
	$radii   = array();
	$shadows = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = (string) ( $rule[2] ?? '' );
		if ( '' === $selector_block || '' === trim( $declarations ) ) {
			continue;
		}

		$selector_lc = strtolower( $selector_block );
		if ( false === strpos( $selector_lc, 'wp-block-' ) && false === strpos( $selector_lc, '.page-content' ) && false === strpos( $selector_lc, '.entry-content' ) ) {
			continue;
		}

		$properties = mcp_abilities_gutenberg_extract_css_property_values(
			$declarations,
			array( 'border-radius', 'box-shadow' )
		);

		if ( ! empty( $properties['border-radius'] ) ) {
			$radii[] = mcp_abilities_gutenberg_compact_css_snippet( (string) $properties['border-radius'] );
		}
		if ( ! empty( $properties['box-shadow'] ) ) {
			$shadows[] = mcp_abilities_gutenberg_compact_css_snippet( (string) $properties['box-shadow'] );
		}
	}

	$radii   = array_values( array_unique( array_filter( $radii ) ) );
	$shadows = array_values( array_unique( array_filter( $shadows ) ) );
	$issues  = array();

	if ( count( $radii ) > 3 ) {
		$issues[] = array(
			'type'    => 'design_token_sprawl',
			'severity'=> 'notice',
			'source'  => $source,
			'aspect'  => 'border-radius',
			'values'  => array_slice( $radii, 0, 6 ),
			'message' => 'The page uses many different border-radius values. Repeated components usually feel more coherent when corners come from a smaller token set.',
		);
	}

	if ( count( $shadows ) > 3 ) {
		$issues[] = array(
			'type'    => 'design_token_sprawl',
			'severity'=> 'notice',
			'source'  => $source,
			'aspect'  => 'box-shadow',
			'values'  => array_slice( $shadows, 0, 6 ),
			'message' => 'The page uses many different shadow treatments. Design systems usually feel calmer when elevation is limited to a smaller token set.',
		);
	}

	return $issues;
}

/**
 * Detect hero heading contrast/readability risks from embedded CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_hero_heading_contrast_issues( string $css, string $source ): array {
	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	$hero_backgrounds = array();
	$hero_headings    = array();

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = trim( (string) ( $rule[2] ?? '' ) );
		if ( '' === $selector_block || '' === $declarations ) {
			continue;
		}

		$selectors = array_map( 'trim', explode( ',', $selector_block ) );
		foreach ( $selectors as $selector ) {
			$selector_lc = strtolower( $selector );
			if ( '' === $selector_lc || false === strpos( $selector_lc, 'div:first-child' ) ) {
				continue;
			}

			if ( preg_match( '/(^|[\s>+~])h1\b/i', $selector_lc ) ) {
				$canonical = mcp_abilities_gutenberg_canonicalize_hero_selector( $selector );
				$hero_headings[] = array(
					'selector'     => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
					'canonical'    => $canonical,
					'complexity'   => mcp_abilities_gutenberg_measure_selector_complexity( $canonical ),
					'declarations' => $declarations,
				);
				continue;
			}

			if ( false !== strpos( strtolower( $declarations ), 'background' ) ) {
				$canonical = mcp_abilities_gutenberg_canonicalize_hero_selector( $selector );
				$hero_backgrounds[] = array(
					'selector'     => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
					'canonical'    => $canonical,
					'complexity'   => mcp_abilities_gutenberg_measure_selector_complexity( $canonical ),
					'declarations' => $declarations,
				);
			}
		}
	}

	if ( empty( $hero_backgrounds ) || empty( $hero_headings ) ) {
		return array();
	}

	$min_heading_complexity = min( array_map( 'intval', wp_list_pluck( $hero_headings, 'complexity' ) ) );
	$min_background_complexity = min( array_map( 'intval', wp_list_pluck( $hero_backgrounds, 'complexity' ) ) );
	$hero_headings = array_values(
		array_filter(
			$hero_headings,
			static function ( array $heading ) use ( $min_heading_complexity ): bool {
				return (int) ( $heading['complexity'] ?? 999 ) === $min_heading_complexity;
			}
		)
	);
	$hero_backgrounds = array_values(
		array_filter(
			$hero_backgrounds,
			static function ( array $background ) use ( $min_background_complexity ): bool {
				return (int) ( $background['complexity'] ?? 999 ) === $min_background_complexity;
			}
		)
	);

	$issues = array();
	$seen   = array();
	foreach ( $hero_headings as $heading_rule ) {
		$heading_properties = mcp_abilities_gutenberg_extract_css_property_values(
			(string) $heading_rule['declarations'],
			array( 'color', 'text-shadow' )
		);
		$heading_color = mcp_abilities_gutenberg_parse_css_color( (string) ( $heading_properties['color'] ?? '' ) );

		foreach ( $hero_backgrounds as $background_rule ) {
			$background_properties = mcp_abilities_gutenberg_extract_css_property_values(
				(string) $background_rule['declarations'],
				array( 'background', 'background-color' )
			);
			$background_value = (string) ( $background_properties['background'] ?? ( $background_properties['background-color'] ?? '' ) );
			if ( '' === $background_value ) {
				continue;
			}

			$background_colors = mcp_abilities_gutenberg_extract_css_readability_color_stops( $background_value );
			if ( empty( $background_colors ) ) {
				continue;
			}

			if ( ! is_array( $heading_color ) ) {
				$key = md5( (string) $heading_rule['selector'] . '|' . (string) $background_rule['selector'] . '|missing-color' );
				if ( isset( $seen[ $key ] ) ) {
					break;
				}
				$seen[ $key ] = true;
				$issues[] = array(
					'type'              => 'hero_heading_readability_risk',
					'severity'          => 'warning',
					'source'            => $source,
					'selector'          => (string) $heading_rule['selector'],
					'background_selector' => (string) $background_rule['selector'],
					'message'           => 'A visually led hero uses a strong background treatment, but the main heading does not declare an explicit text color. Large titles can disappear into warm gradients or image-led heroes unless contrast is set intentionally.',
				);
				break;
			}

			$min_contrast = null;
			foreach ( $background_colors as $background_color ) {
				$contrast = mcp_abilities_gutenberg_contrast_ratio( $heading_color, $background_color );
				$min_contrast = null === $min_contrast ? $contrast : min( $min_contrast, $contrast );
			}

			if ( null !== $min_contrast && $min_contrast < 3 ) {
				$key = md5( (string) $heading_rule['selector'] . '|' . (string) $background_rule['selector'] . '|contrast' );
				if ( isset( $seen[ $key ] ) ) {
					break;
				}
				$seen[ $key ] = true;
				$issues[] = array(
					'type'              => 'hero_heading_readability_risk',
					'severity'          => 'warning',
					'source'            => $source,
					'selector'          => (string) $heading_rule['selector'],
					'background_selector' => (string) $background_rule['selector'],
					'contrast_ratio'    => round( $min_contrast, 2 ),
					'message'           => 'The main hero heading color is too close to at least one background stop. Display typography still needs strong contrast to read cleanly at a glance.',
				);
				break;
			}
		}
	}

	return $issues;
}

/**
 * Detect weakly intentional tilt angles in embedded Gutenberg CSS.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_subtle_tilt_issues( string $css, string $source ): array {
	$issues = array();

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return $issues;
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = trim( (string) ( $rule[2] ?? '' ) );
		if ( '' === $selector_block || '' === $declarations ) {
			continue;
		}

		if ( ! preg_match( '/transform\s*:\s*[^;]*rotate\(\s*(-?[0-9]+(?:\.[0-9]+)?)deg\s*\)/i', $declarations, $matches ) ) {
			continue;
		}

		$angle = abs( (float) ( $matches[1] ?? 0 ) );
		if ( $angle < 0.4 || $angle > 2.2 ) {
			continue;
		}

		$issues[] = array(
			'type'        => 'subtle_tilt_ambiguity',
			'severity'    => 'notice',
			'source'      => $source,
			'selector'    => mcp_abilities_gutenberg_compact_css_snippet( $selector_block ),
			'angle_deg'   => round( $angle, 2 ),
			'message'     => 'A rotated element uses only a very slight tilt. At that angle it often reads as accidental misalignment instead of a deliberate playful gesture.',
		);
	}

	return $issues;
}

/**
 * Map a boxed CSS selector to a broader section family key.
 *
 * @param string $selector CSS selector.
 * @return string
 */
function mcp_abilities_gutenberg_get_boxed_section_family_key( string $selector ): string {
	$selector_lc = strtolower( $selector );

	if ( preg_match( '/alignfull\s*>\s*div:nth-child\(\s*(\d+)\s*\)/i', $selector_lc, $matches ) ) {
		return 'alignfull-section-' . (string) (int) $matches[1];
	}

	if ( preg_match( '/\.wp-block-columns(?:[^a-z0-9_-]|$)/i', $selector_lc ) ) {
		return 'columns-layout';
	}

	if ( preg_match( '/\.wp-block-column(?:[^a-z0-9_-]|$)/i', $selector_lc ) ) {
		return 'column-module';
	}

	if ( preg_match( '/\.wp-block-quote(?:[^a-z0-9_-]|$)/i', $selector_lc ) ) {
		return 'quote-module';
	}

	if ( preg_match( '/\.wp-block-group(?:[^a-z0-9_-]|$)/i', $selector_lc ) ) {
		return 'group-module';
	}

	return '';
}

/**
 * Detect overuse of boxed/card treatments across a page.
 *
 * @param string $css CSS to inspect.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_card_monotony_issues( string $css, string $source ): array {
	$selectors         = array();
	$section_families  = array();
	$component_classes = array();
	$boxed_rule_count  = 0;

	if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
		return array();
	}

	foreach ( $rules as $rule ) {
		$selector_block = trim( (string) ( $rule[1] ?? '' ) );
		$declarations   = strtolower( trim( (string) ( $rule[2] ?? '' ) ) );
		if ( '' === $selector_block || '' === $declarations ) {
			continue;
		}

		$looks_boxed = false !== strpos( $declarations, 'border-radius' )
			&& (
				false !== strpos( $declarations, 'box-shadow' )
				|| false !== strpos( $declarations, 'background' )
				|| false !== strpos( $declarations, 'border:' )
				|| false !== strpos( $declarations, 'border-width' )
			);

		if ( ! $looks_boxed ) {
			continue;
		}

		++$boxed_rule_count;
		$selector_lc = strtolower( $selector_block );
		if ( false === strpos( $selector_lc, 'wp-block-group' ) && false === strpos( $selector_lc, 'wp-block-quote' ) && false === strpos( $selector_lc, 'wp-block-column' ) ) {
			continue;
		}

		$selectors[] = mcp_abilities_gutenberg_compact_css_snippet( $selector_block );

		$family_key = mcp_abilities_gutenberg_get_boxed_section_family_key( $selector_block );
		if ( '' !== $family_key ) {
			$section_families[] = $family_key;
		}

		if ( false !== strpos( $selector_lc, 'wp-block-columns' ) ) {
			$component_classes[] = 'wp-block-columns';
		}
		if ( false !== strpos( $selector_lc, 'wp-block-column' ) ) {
			$component_classes[] = 'wp-block-column';
		}
		if ( false !== strpos( $selector_lc, 'wp-block-group' ) ) {
			$component_classes[] = 'wp-block-group';
		}
		if ( false !== strpos( $selector_lc, 'wp-block-quote' ) ) {
			$component_classes[] = 'wp-block-quote';
		}
	}

	$selectors         = array_values( array_unique( array_filter( $selectors ) ) );
	$section_families  = array_values( array_unique( array_filter( $section_families ) ) );
	$component_classes = array_values( array_unique( array_filter( $component_classes ) ) );

	$has_section_spread    = count( $section_families ) >= 3;
	$has_component_spread  = count( $component_classes ) >= 3;
	$has_selector_density  = count( $selectors ) >= 4;
	$has_rule_density      = $boxed_rule_count >= 5;

	if ( ( ! $has_section_spread && ! $has_component_spread ) || ( ! $has_selector_density && ! $has_rule_density ) ) {
		return array();
	}

	return array(
		array(
			'type'              => 'card_monotony_risk',
			'severity'          => 'notice',
			'source'            => $source,
			'count'             => count( $selectors ),
			'boxed_rule_count'  => $boxed_rule_count,
			'selectors'         => array_slice( $selectors, 0, 8 ),
			'section_families'  => array_slice( $section_families, 0, 8 ),
			'component_classes' => $component_classes,
			'message'           => 'Too many major sections are being treated like contained cards or boxed panels. AI-generated pages often flatten into repeated rounded modules unless some sections stay open, linear, or full-bleed.',
		),
	);
}

/**
 * Detect overuse of rendered boxed modules from inline Gutenberg markup.
 *
 * @param string $html Rendered Gutenberg HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_rendered_boxed_module_issues( string $html, string $source ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$nodes = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-group ")
			or contains(concat(" ", normalize-space(@class), " "), " wp-block-quote ")
			or contains(concat(" ", normalize-space(@class), " "), " wp-block-column ")]',
		$root
	);
	if ( ! $nodes instanceof DOMNodeList || 0 === $nodes->length ) {
		return array();
	}

	$examples        = array();
	$module_types    = array();
	$section_families = array();
	$boxed_count     = 0;

	foreach ( $nodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}

		$class = ' ' . strtolower( trim( (string) $node->getAttribute( 'class' ) ) ) . ' ';
		$style = strtolower( trim( (string) $node->getAttribute( 'style' ) ) );

		$has_radius = false !== strpos( $style, 'border-radius' );
		$has_box_treatment = false !== strpos( $style, 'background' )
			|| false !== strpos( $style, 'border:' )
			|| false !== strpos( $style, 'box-shadow' )
			|| false !== strpos( $class, ' has-background ' );

		if ( ! $has_radius || ! $has_box_treatment ) {
			continue;
		}

		++$boxed_count;
		$examples[] = mcp_abilities_gutenberg_describe_dom_element( $node );

		if ( false !== strpos( $class, ' wp-block-quote ' ) ) {
			$module_types[] = 'wp-block-quote';
		} elseif ( false !== strpos( $class, ' wp-block-column ' ) ) {
			$module_types[] = 'wp-block-column';
		} else {
			$module_types[] = 'wp-block-group';
		}

		$parent = $node->parentNode;
		while ( $parent instanceof DOMElement ) {
			$parent_class = ' ' . strtolower( trim( (string) $parent->getAttribute( 'class' ) ) ) . ' ';
			if ( false !== strpos( $parent_class, ' wp-block-group ' ) || false !== strpos( $parent_class, ' wp-block-columns ' ) ) {
				$section_families[] = mcp_abilities_gutenberg_describe_dom_element( $parent );
				break;
			}
			$parent = $parent->parentNode;
		}
	}

	$examples         = array_values( array_unique( array_filter( $examples ) ) );
	$module_types     = array_values( array_unique( array_filter( $module_types ) ) );
	$section_families = array_values( array_unique( array_filter( $section_families ) ) );

	if ( $boxed_count < 5 || count( $section_families ) < 3 ) {
		return array();
	}

	return array(
		array(
			'type'              => 'card_monotony_risk',
			'severity'          => 'notice',
			'source'            => $source,
			'count'             => $boxed_count,
			'selectors'         => array_slice( $examples, 0, 8 ),
			'section_families'  => array_slice( $section_families, 0, 8 ),
			'component_classes' => $module_types,
			'message'           => 'Too many rendered sections resolve into contained rounded modules. Gutenberg pages feel stronger when some sections stay open, linear, or full-bleed instead of turning every content beat into a card.',
		),
	);
}

/**
 * Determine whether a rendered Gutenberg element reads as a boxed/card treatment.
 *
 * @param DOMElement $element Element to inspect.
 * @return bool
 */
function mcp_abilities_gutenberg_dom_element_has_box_treatment( DOMElement $element ): bool {
	$class = ' ' . strtolower( trim( (string) $element->getAttribute( 'class' ) ) ) . ' ';
	$style = strtolower( trim( (string) $element->getAttribute( 'style' ) ) );

	$has_radius = false !== strpos( $style, 'border-radius' );
	$has_box_treatment = false !== strpos( $style, 'background' )
		|| false !== strpos( $style, 'border:' )
		|| false !== strpos( $style, 'box-shadow' )
		|| false !== strpos( $class, ' has-background ' );

	return $has_radius && $has_box_treatment;
}

/**
 * Detect inconsistent treatment inside repeated rendered rows.
 *
 * @param string $html Rendered Gutenberg HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_rendered_row_treatment_issues( string $html, string $source ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$rows = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-columns ")]',
		$root
	);
	if ( ! $rows instanceof DOMNodeList || 0 === $rows->length ) {
		return array();
	}

	$issues = array();

	foreach ( $rows as $row ) {
		if ( ! $row instanceof DOMElement ) {
			continue;
		}

		$children = array();
		foreach ( $row->childNodes as $child_node ) {
			if ( ! $child_node instanceof DOMElement ) {
				continue;
			}

			$child_class = ' ' . strtolower( trim( (string) $child_node->getAttribute( 'class' ) ) ) . ' ';
			if ( false === strpos( $child_class, ' wp-block-column ' ) ) {
				continue;
			}

			$children[] = $child_node;
		}

		if ( count( $children ) < 3 ) {
			continue;
		}

		$boxed_indices = array();
		$open_indices  = array();
		$examples      = array();

		foreach ( $children as $index => $child ) {
			$is_boxed = mcp_abilities_gutenberg_dom_element_has_box_treatment( $child );

			if ( ! $is_boxed ) {
				foreach ( $child->childNodes as $grandchild ) {
					if ( ! $grandchild instanceof DOMElement ) {
						continue;
					}

					if ( mcp_abilities_gutenberg_dom_element_has_box_treatment( $grandchild ) ) {
						$is_boxed = true;
						break;
					}
				}
			}

			$human_index = $index + 1;
			if ( $is_boxed ) {
				$boxed_indices[] = $human_index;
				$examples[]      = mcp_abilities_gutenberg_describe_dom_element( $child );
			} else {
				$open_indices[] = $human_index;
			}
		}

		if ( empty( $boxed_indices ) || empty( $open_indices ) ) {
			continue;
		}

		if ( 1 !== count( $boxed_indices ) && 1 !== count( $open_indices ) ) {
			continue;
		}

		$issues[] = array(
			'type'        => 'row_treatment_inconsistency',
			'severity'    => 'notice',
			'source'      => $source,
			'selector'    => mcp_abilities_gutenberg_describe_dom_element( $row ),
			'row_size'    => count( $children ),
			'boxed_items' => $boxed_indices,
			'open_items'  => $open_indices,
			'examples'    => array_values( array_unique( array_slice( $examples, 0, 3 ) ) ),
			'message'     => 'A repeated row mixes one boxed/card-like sibling with otherwise open siblings. That usually reads as accidental rather than intentional unless the standout item is clearly meant to be a spotlight.',
		);
	}

	return $issues;
}

/**
 * Detect rendered support rows that pack too many text-bearing modules into a narrow horizontal rhythm.
 *
 * @param string $html Rendered Gutenberg HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_rendered_support_module_cramp_issues( string $html, string $source ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$issues = array();
	$seen   = array();

	$row_nodes = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-columns ")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "row")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "strip")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "grid")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "items")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "proof")
			or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "process")]',
		$root
	);
	if ( ! $row_nodes instanceof DOMNodeList || 0 === $row_nodes->length ) {
		return array();
	}

	foreach ( $row_nodes as $row ) {
		if ( ! $row instanceof DOMElement ) {
			continue;
		}

		$row_selector = mcp_abilities_gutenberg_describe_dom_element( $row );
		if ( isset( $seen[ $row_selector ] ) ) {
			continue;
		}

		$children = array();
		$row_class = ' ' . strtolower( trim( (string) $row->getAttribute( 'class' ) ) ) . ' ';
		$is_columns_row = false !== strpos( $row_class, ' wp-block-columns ' );

		foreach ( $row->childNodes as $child_node ) {
			if ( ! $child_node instanceof DOMElement ) {
				continue;
			}

			if ( $is_columns_row ) {
				$child_class = ' ' . strtolower( trim( (string) $child_node->getAttribute( 'class' ) ) ) . ' ';
				if ( false === strpos( $child_class, ' wp-block-column ' ) ) {
					continue;
				}
			}

			$children[] = $child_node;
		}

		if ( ! $is_columns_row ) {
			$children = mcp_abilities_gutenberg_get_significant_child_elements( $row );
		}

		$child_count = count( $children );
		if ( $child_count < 3 || $child_count > 4 ) {
			continue;
		}

		$word_counts      = array();
		$examples         = array();
		$heading_like     = 0;
		$body_like        = 0;

		foreach ( $children as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$text = trim( preg_replace( '/\s+/u', ' ', (string) $child->textContent ) );
			$word_count = mcp_abilities_gutenberg_count_words( $text );
			if ( $word_count <= 0 ) {
				continue;
			}

			$word_counts[] = $word_count;
			$examples[]    = mcp_abilities_gutenberg_describe_dom_element( $child );

			$heading_nodes = $xpath->query( './/h2|.//h3|.//h4|.//h5|.//h6|.//strong', $child );
			if ( $heading_nodes instanceof DOMNodeList && $heading_nodes->length > 0 ) {
				++$heading_like;
			}

			$paragraph_nodes = $xpath->query( './/p|.//span|.//li', $child );
			if ( $paragraph_nodes instanceof DOMNodeList && $paragraph_nodes->length > 0 ) {
				++$body_like;
			}
		}

		if ( count( $word_counts ) !== $child_count ) {
			continue;
		}

		$avg_words = array_sum( $word_counts ) / $child_count;
		$max_words = max( $word_counts );
		$min_words = min( $word_counts );

		$is_support_family = $heading_like >= max( 2, $child_count - 1 ) || $body_like >= max( 2, $child_count - 1 );
		if ( ! $is_support_family ) {
			continue;
		}

		$is_cramped = false;
		if ( 4 === $child_count && $avg_words >= 12 ) {
			$is_cramped = true;
		} elseif ( 3 === $child_count && $avg_words >= 9 ) {
			$is_cramped = true;
		} elseif ( $max_words >= 24 && $child_count >= 3 ) {
			$is_cramped = true;
		}

		if ( ! $is_cramped ) {
			continue;
		}

		$seen[ $row_selector ] = true;
		$issues[] = array(
			'type'              => 'support_module_cramp_risk',
			'severity'          => 'notice',
			'source'            => $source,
			'selector'          => $row_selector,
			'selectors'         => array( $row_selector ),
			'row_size'          => $child_count,
			'average_words'     => (int) round( $avg_words ),
			'max_words'         => (int) $max_words,
			'min_words'         => (int) $min_words,
			'examples'          => array_values( array_unique( array_slice( $examples, 0, 4 ) ) ),
			'message'           => 'A support row packs too many text-bearing sibling modules into one horizontal lane. Even without explicit width caps, the row can still feel too narrow because each module gets too little usable width for its copy load.',
		);
	}

	return $issues;
}

/**
 * Build a compact structural signature for a rendered module.
 *
 * @param DOMElement $element Element to describe.
 * @return string
 */
function mcp_abilities_gutenberg_get_dom_structure_signature( DOMElement $element ): string {
	$tokens = array();
	$walker = static function ( DOMElement $node ) use ( &$walker, &$tokens ): void {
		if ( count( $tokens ) >= 12 ) {
			return;
		}

		$class = strtolower( trim( (string) $node->getAttribute( 'class' ) ) );
		if ( '' !== $class ) {
			foreach ( preg_split( '/\s+/', $class ) as $class_name ) {
				$class_name = strtolower( trim( (string) $class_name ) );
				if ( '' === $class_name ) {
					continue;
				}

				if ( 0 === strpos( $class_name, 'wp-block-' ) || 'wp-element-button' === $class_name ) {
					$tokens[] = $class_name;
					if ( count( $tokens ) >= 12 ) {
						return;
					}
				}
			}
		}

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$walker( $child );
				if ( count( $tokens ) >= 12 ) {
					return;
				}
			}
		}
	};

	$walker( $element );

	if ( empty( $tokens ) ) {
		return strtolower( $element->tagName );
	}

	return implode( '|', array_slice( $tokens, 0, 12 ) );
}

/**
 * Detect repeated sibling modules that receive conflicting treatments.
 *
 * @param string $html Rendered Gutenberg HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_repeated_object_treatment_issues( string $html, string $source ): array {
	if ( '' === trim( $html ) ) {
		return array();
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-design-root">' . $html . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array();
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-design-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( ! $root instanceof DOMElement ) {
		return array();
	}

	$rows = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-columns ")]',
		$root
	);
	if ( ! $rows instanceof DOMNodeList || 0 === $rows->length ) {
		return array();
	}

	$issues = array();

	foreach ( $rows as $row ) {
		if ( ! $row instanceof DOMElement ) {
			continue;
		}

		$children = array();
		foreach ( $row->childNodes as $child_node ) {
			if ( ! $child_node instanceof DOMElement ) {
				continue;
			}

			$child_class = ' ' . strtolower( trim( (string) $child_node->getAttribute( 'class' ) ) ) . ' ';
			if ( false === strpos( $child_class, ' wp-block-column ' ) ) {
				continue;
			}

			$children[] = $child_node;
		}

		if ( count( $children ) < 2 ) {
			continue;
		}

		$families = array();

		foreach ( $children as $index => $child ) {
			$signature = mcp_abilities_gutenberg_get_dom_structure_signature( $child );
			if ( '' === $signature ) {
				continue;
			}

			$is_boxed = mcp_abilities_gutenberg_dom_element_has_box_treatment( $child );
			if ( ! $is_boxed ) {
				foreach ( $child->childNodes as $grandchild ) {
					if ( $grandchild instanceof DOMElement && mcp_abilities_gutenberg_dom_element_has_box_treatment( $grandchild ) ) {
						$is_boxed = true;
						break;
					}
				}
			}

			if ( ! isset( $families[ $signature ] ) ) {
				$families[ $signature ] = array(
					'boxed'    => array(),
					'open'     => array(),
					'examples' => array(),
				);
			}

			$human_index = $index + 1;
			if ( $is_boxed ) {
				$families[ $signature ]['boxed'][] = $human_index;
			} else {
				$families[ $signature ]['open'][] = $human_index;
			}
			$families[ $signature ]['examples'][] = mcp_abilities_gutenberg_describe_dom_element( $child );
		}

		foreach ( $families as $signature => $family ) {
			$total = count( $family['boxed'] ) + count( $family['open'] );
			if ( $total < 2 || empty( $family['boxed'] ) || empty( $family['open'] ) ) {
				continue;
			}

			$issues[] = array(
				'type'          => 'repeated_object_treatment_inconsistency',
				'severity'      => 'notice',
				'source'        => $source,
				'selector'      => mcp_abilities_gutenberg_describe_dom_element( $row ),
				'family'        => $signature,
				'row_size'      => count( $children ),
				'boxed_items'   => array_values( $family['boxed'] ),
				'open_items'    => array_values( $family['open'] ),
				'examples'      => array_values( array_unique( array_slice( $family['examples'], 0, 3 ) ) ),
				'message'       => 'Repeated sibling modules that appear to belong to the same object family are being treated differently. When the same object repeats, its containment style should usually repeat too unless one instance is very clearly featured.',
			);
		}
	}

	return $issues;
}

/**
 * Detect drifting section spacing rhythm from authored Gutenberg block values.
 *
 * @param string $content Raw Gutenberg content.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_block_spacing_rhythm_issues( string $content ): array {
	$blocks = parse_blocks( $content );
	if ( ! is_array( $blocks ) || count( $blocks ) < 3 ) {
		return array();
	}

	$entries = array();

	foreach ( $blocks as $index => $block ) {
		$block_name = (string) ( $block['blockName'] ?? '' );
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$values     = array();

		if ( 'core/spacer' === $block_name ) {
			$height_px = mcp_abilities_gutenberg_parse_spacing_value_to_px( (string) ( $attrs['height'] ?? '' ) );
			if ( is_float( $height_px ) && $height_px >= 24 ) {
				$values[] = array(
					'value' => (string) $attrs['height'],
					'px'    => $height_px,
				);
			}
		}

		$spacing = is_array( $attrs['style']['spacing'] ?? null ) ? $attrs['style']['spacing'] : array();
		foreach ( array( 'padding', 'margin' ) as $spacing_type ) {
			$spacing_values = is_array( $spacing[ $spacing_type ] ?? null ) ? $spacing[ $spacing_type ] : array();
			foreach ( array( 'top', 'bottom' ) as $direction ) {
				$raw_value = (string) ( $spacing_values[ $direction ] ?? '' );
				$px_value  = mcp_abilities_gutenberg_parse_spacing_value_to_px( $raw_value );
				if ( is_float( $px_value ) && $px_value >= 24 ) {
					$values[] = array(
						'value' => $raw_value,
						'px'    => $px_value,
					);
				}
			}
		}

		if ( empty( $values ) ) {
			continue;
		}

		foreach ( $values as $value ) {
			$entries[] = array(
				'block_index' => $index + 1,
				'block_name'  => $block_name,
				'value'       => (string) $value['value'],
				'px'          => (float) $value['px'],
				'selector'    => sprintf( 'top-level[%d] %s', $index + 1, '' !== $block_name ? $block_name : 'anonymous-block' ),
			);
		}
	}

	if ( count( $entries ) < 3 ) {
		return array();
	}

	$normalized_values = array();
	foreach ( $entries as $entry ) {
		$normalized_values[] = (int) ( round( (float) $entry['px'] / 4 ) * 4 );
	}
	$normalized_values = array_values( array_unique( $normalized_values ) );
	sort( $normalized_values );

	$min_spacing      = min( $normalized_values );
	$max_spacing      = max( $normalized_values );
	$has_value_sprawl = count( $normalized_values ) >= 3;
	$has_large_outlier = $max_spacing >= max( 96, (int) round( $min_spacing * 1.8 ) );

	if ( ! $has_value_sprawl && ! $has_large_outlier ) {
		return array();
	}

	return array(
		array(
			'type'               => 'spacing_rhythm_drift',
			'severity'           => 'notice',
			'source'             => 'block-structure',
			'count'              => count( $entries ),
			'values'             => array_map(
				static function ( int $value ): string {
					return $value . 'px';
				},
				array_slice( $normalized_values, 0, 8 )
			),
			'examples'           => array_values( array_unique( array_slice( array_map( 'strval', wp_list_pluck( $entries, 'selector' ) ), 0, 8 ) ) ),
			'largest_spacing_px' => $max_spacing,
			'message'            => 'Major section spacing uses several unrelated distances. Pages usually feel more balanced when vertical rhythm comes from a smaller spacing token set instead of one-off paddings, margins, and spacer heights.',
		),
	);
}

/**
 * Collect layout-risk issues directly from Gutenberg content markup.
 *
 * @param string $content Raw Gutenberg post content.
 * @return array{issues: array<int,array<string,mixed>>, embedded_style_block_count: int, inline_style_count: int, content_measures: array<int,array<string,mixed>>, shell_full_width_css_detected: bool}
 */
function mcp_abilities_gutenberg_collect_content_layout_risks( string $content ): array {
	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-gutenberg-layout-root">' . $content . '</div></body></html>'
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return array(
			'issues'                    => array(),
			'embedded_style_block_count'=> 0,
			'inline_style_count'        => 0,
			'content_measures'          => array(),
			'shell_full_width_css_detected' => false,
		);
	}

	$xpath      = new DOMXPath( $document );
	$root_nodes = $xpath->query( '//*[@id="mcp-gutenberg-layout-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;

	if ( ! $root instanceof DOMElement ) {
		return array(
			'issues'                    => array(),
			'embedded_style_block_count'=> 0,
			'inline_style_count'        => 0,
			'content_measures'          => array(),
			'shell_full_width_css_detected' => false,
		);
	}

	$issues                   = array();
	$embedded_style_block_count = 0;
	$content_measures         = array();
	$full_width_shell_css_snippets = array();
	$style_nodes              = $xpath->query( './/style', $root );

	if ( $style_nodes instanceof DOMNodeList && $style_nodes->length > 0 ) {
		$embedded_style_block_count = $style_nodes->length;

		for ( $index = 0; $index < $style_nodes->length; $index++ ) {
			$style_node = $style_nodes->item( $index );
			if ( ! $style_node instanceof DOMElement ) {
				continue;
			}

			$css = trim( (string) $style_node->textContent );
			if ( '' === $css ) {
				continue;
			}

			$full_width_shell_css_snippets = array_merge(
				$full_width_shell_css_snippets,
				mcp_abilities_gutenberg_detect_full_width_shell_css_snippets( $css )
			);
			$content_measures = array_merge(
				$content_measures,
				mcp_abilities_gutenberg_collect_css_content_measures(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				)
			);
			$issues = array_merge(
				$issues,
				mcp_abilities_gutenberg_collect_css_sibling_treatment_issues(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				),
				mcp_abilities_gutenberg_collect_css_button_contrast_issues(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				),
				mcp_abilities_gutenberg_collect_css_trailing_gap_issues(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				),
				mcp_abilities_gutenberg_collect_css_design_token_sprawl_issues(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				),
				mcp_abilities_gutenberg_collect_css_card_monotony_issues(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				),
				mcp_abilities_gutenberg_collect_css_layout_risks(
					$css,
					sprintf( 'embedded-style-block-%d', $index + 1 )
				)
			);
		}
	}

	$inline_style_risks = mcp_abilities_gutenberg_collect_inline_style_layout_risks( $xpath, $root );
	if ( ! empty( $inline_style_risks['issues'] ) ) {
		$issues = array_merge( $issues, $inline_style_risks['issues'] );
	}

	$full_width_shell_css_snippets = array_values( array_unique( $full_width_shell_css_snippets ) );
	$alignfull_nodes = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " alignfull ")]',
		$root
	);
	$alignfull_count = $alignfull_nodes instanceof DOMNodeList ? $alignfull_nodes->length : 0;
	if ( $alignfull_count > 0 && ! empty( $full_width_shell_css_snippets ) ) {
		$selectors = array();
		for ( $index = 0; $index < min( 3, $alignfull_count ); $index++ ) {
			$alignfull_node = $alignfull_nodes->item( $index );
			if ( $alignfull_node instanceof DOMElement ) {
				$selectors[] = mcp_abilities_gutenberg_describe_dom_element( $alignfull_node );
			}
		}

		$issues[] = array(
			'type'      => 'alignfull_breakout_risk',
			'severity'  => 'warning',
			'count'     => $alignfull_count,
			'selectors' => $selectors,
			'snippets'  => array_slice( $full_width_shell_css_snippets, 0, 3 ),
			'message'   => 'Content mixes alignfull Gutenberg blocks with CSS that already forces the surrounding shell to full width; theme breakout margins can then create horizontal page scrolling. Prefer neutralized alignfull margins or non-breakout full-width wrappers.',
		);
	}

	$issues = array_merge(
		$issues,
		mcp_abilities_gutenberg_collect_width_system_risks( $content_measures, 'embedded-style-blocks' )
	);

	return array(
		'issues'                    => $issues,
		'embedded_style_block_count'=> $embedded_style_block_count,
		'inline_style_count'        => (int) $inline_style_risks['count'],
		'content_measures'          => $content_measures,
		'shell_full_width_css_detected' => ! empty( $full_width_shell_css_snippets ),
	);
}

/**
 * Detect CSS that explicitly forces the rendered page shell to full width.
 *
 * @param string $css CSS to inspect.
 * @return array<int,string>
 */
function mcp_abilities_gutenberg_detect_full_width_shell_css_snippets( string $css ): array {
	$snippets = array();
	$patterns = array(
		'/(?:main|\.site-main|\.page-content|\.entry-content)[^{]*\{[^}]*max-width\s*:\s*none[^}]*width\s*:\s*100%[^}]*\}/i',
		'/(?:main|\.site-main)[^{]*\{[^}]*margin\s*:\s*0[^}]*max-width\s*:\s*none[^}]*\}/i',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match_all( $pattern, $css, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$snippets[] = mcp_abilities_gutenberg_compact_css_snippet( (string) $match );
			}
		}
	}

	return array_values( array_unique( $snippets ) );
}

/**
 * Fail content writes when they contain high-confidence editor/render layout risks.
 *
 * @param string $content Raw Gutenberg content.
 * @return true|WP_Error
 */
function mcp_abilities_gutenberg_assert_layout_safe_for_write( string $content ) {
	$layout_risks = mcp_abilities_gutenberg_collect_content_layout_risks( $content );
	$blocking_types = array(
		'layout_overlap_risk',
		'scroll_region_risk',
		'viewport_overflow_risk',
		'transform_offset_risk',
		'position_overlay_risk',
		'alignfull_breakout_risk',
	);

	$blocking_issues = array_values(
		array_filter(
			$layout_risks['issues'],
			static function ( array $issue ) use ( $blocking_types ): bool {
				return in_array( (string) ( $issue['type'] ?? '' ), $blocking_types, true );
			}
		)
	);

	if ( empty( $blocking_issues ) ) {
		return true;
	}

	$issue_types = array_values(
		array_unique(
			array_map(
				static function ( array $issue ): string {
					return (string) ( $issue['type'] ?? '' );
				},
				$blocking_issues
			)
		)
	);
	$snippets = array();
	foreach ( array_slice( $blocking_issues, 0, 3 ) as $issue ) {
		if ( ! empty( $issue['snippets'] ) && is_array( $issue['snippets'] ) ) {
			foreach ( array_slice( $issue['snippets'], 0, 2 ) as $snippet ) {
				$snippets[] = (string) $snippet;
			}
		}
	}
	$snippets = array_values( array_unique( array_filter( $snippets ) ) );
	$message  = 'Blocked save because the content contains layout-risk CSS that can break editor or frontend rendering.';
	if ( ! empty( $issue_types ) ) {
		$message .= ' Types: ' . implode( ', ', $issue_types ) . '.';
	}
	if ( ! empty( $snippets ) ) {
		$message .= ' Snippets: ' . implode( ' | ', array_slice( $snippets, 0, 4 ) ) . '.';
	}

	return new WP_Error(
		'mcp_gutenberg_editor_layout_risk_blocked',
		$message,
		array(
			'issues' => $blocking_issues,
		)
	);
}

/**
 * Evaluate rendered page context for a post/page.
 *
 * @param int $post_id Post ID.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_evaluate_render_context( int $post_id ) {
	$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
	if ( is_wp_error( $post ) ) {
		return $post;
	}

	$url = get_permalink( $post );
	if ( ! is_string( $url ) || '' === $url ) {
		return new WP_Error( 'mcp_gutenberg_render_context_missing_url', 'Could not resolve the rendered URL for this post.' );
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 15,
			'redirection' => 3,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'mcp_gutenberg_render_context_fetch_failed', $response->get_error_message() );
	}

	$status_code = (int) wp_remote_retrieve_response_code( $response );
	$html        = (string) wp_remote_retrieve_body( $response );
	if ( $status_code < 200 || $status_code >= 300 || '' === $html ) {
		return new WP_Error( 'mcp_gutenberg_render_context_bad_response', sprintf( 'Rendered page request returned HTTP %d.', $status_code ) );
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML( $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return new WP_Error( 'mcp_gutenberg_render_context_parse_failed', 'Could not parse rendered page HTML.' );
	}

	$xpath        = new DOMXPath( $document );
	$main_nodes   = $xpath->query( '//main' );
	$content_nodes = $xpath->query(
		'//*[contains(concat(" ", normalize-space(@class), " "), " entry-content ") or contains(concat(" ", normalize-space(@class), " "), " page-content ")]'
	);
	$issues       = array();
	$observations = array(
		'main_found'                => $main_nodes instanceof DOMNodeList && $main_nodes->length > 0,
		'content_wrapper_found'     => $content_nodes instanceof DOMNodeList && $content_nodes->length > 0,
		'content_wrapper_selector'  => '',
		'main_child_element_count'  => 0,
		'pre_content_sibling_count' => 0,
		'leading_content_child_tag' => '',
		'leading_content_child_path'=> '',
		'embedded_style_block_count'=> 0,
		'inline_style_count'       => 0,
		'alignfull_block_count'    => 0,
		'shell_full_width_css_detected' => false,
		'content_measure_values'   => array(),
	);

	if ( ! $observations['main_found'] ) {
		$issues[] = array(
			'type'     => 'missing_main',
			'severity' => 'error',
			'message'  => 'Rendered page is missing a <main> element.',
		);
	}

	if ( ! $observations['content_wrapper_found'] ) {
		$issues[] = array(
			'type'     => 'missing_content_wrapper',
			'severity' => 'error',
			'message'  => 'Rendered page is missing an .entry-content or .page-content wrapper.',
		);
	}

	if ( $observations['main_found'] && $observations['content_wrapper_found'] ) {
		$main          = $main_nodes->item( 0 );
		$content_wrapper = $content_nodes->item( 0 );

		if ( $main instanceof DOMElement && $content_wrapper instanceof DOMElement ) {
			$observations['content_wrapper_selector'] = mcp_abilities_gutenberg_describe_dom_element( $content_wrapper );
			$main_children = array();
			foreach ( $main->childNodes as $child ) {
				if ( $child instanceof DOMElement ) {
					$main_children[] = $child;
				}
			}

			$observations['main_child_element_count'] = count( $main_children );

			$pre_entry_siblings = array();
			foreach ( $main_children as $child ) {
				if ( $child->isSameNode( $content_wrapper ) ) {
					break;
				}
				$pre_entry_siblings[] = $child;
			}

			$observations['pre_content_sibling_count'] = count( $pre_entry_siblings );

			if ( ! empty( $pre_entry_siblings ) ) {
				$issues[] = array(
					'type'      => 'pre_content_wrappers',
					'severity'  => 'warning',
					'count'     => count( $pre_entry_siblings ),
					'selectors' => array_map( 'mcp_abilities_gutenberg_describe_dom_element', $pre_entry_siblings ),
					'message'   => 'Rendered page contains wrapper elements before the main content wrapper inside <main>; these can create unexpected spacing or layout chrome above the first content block.',
				);

				foreach ( $pre_entry_siblings as $sibling ) {
					if ( ! mcp_abilities_gutenberg_dom_element_has_meaningful_content( $sibling ) ) {
						$issues[] = array(
							'type'     => 'empty_pre_content_wrapper',
							'severity' => 'warning',
							'selector' => mcp_abilities_gutenberg_describe_dom_element( $sibling ),
							'message'  => 'An empty wrapper appears before the main content wrapper inside <main>; this often creates a visible gap above the hero.',
						);
					}
				}
			}

			$entry_children = array();
			foreach ( $content_wrapper->childNodes as $child ) {
				if ( $child instanceof DOMElement ) {
					$entry_children[] = $child;
				}
			}

			if ( ! empty( $entry_children ) ) {
				$first_child = $entry_children[0];
				$observations['leading_content_child_tag']  = strtolower( $first_child->tagName );
				$observations['leading_content_child_path'] = mcp_abilities_gutenberg_describe_dom_element( $first_child );

				if ( 'style' === strtolower( $first_child->tagName ) ) {
					$issues[] = array(
						'type'     => 'leading_style_block',
						'severity' => 'warning',
						'selector' => mcp_abilities_gutenberg_describe_dom_element( $first_child ),
						'message'  => 'The first rendered child inside the main content wrapper is a style block; this can interact badly with theme flow spacing ahead of the hero.',
					);
				}
			}

			$style_nodes = $xpath->query( './/style', $content_wrapper );
			$full_width_shell_css_snippets = array();
			$content_measures = array();
			if ( $style_nodes instanceof DOMNodeList && $style_nodes->length > 0 ) {
				$observations['embedded_style_block_count'] = $style_nodes->length;

				for ( $index = 0; $index < $style_nodes->length; $index++ ) {
					$style_node = $style_nodes->item( $index );
					if ( ! $style_node instanceof DOMElement ) {
						continue;
					}

					$css = trim( (string) $style_node->textContent );
					if ( '' === $css ) {
						continue;
					}

					$full_width_shell_css_snippets = array_merge(
						$full_width_shell_css_snippets,
						mcp_abilities_gutenberg_detect_full_width_shell_css_snippets( $css )
					);
					$content_measures = array_merge(
						$content_measures,
						mcp_abilities_gutenberg_collect_css_content_measures(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						)
					);

					$issues = array_merge(
						$issues,
						mcp_abilities_gutenberg_collect_css_sibling_treatment_issues(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						),
						mcp_abilities_gutenberg_collect_css_button_contrast_issues(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						),
						mcp_abilities_gutenberg_collect_css_trailing_gap_issues(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						),
						mcp_abilities_gutenberg_collect_css_design_token_sprawl_issues(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						),
						mcp_abilities_gutenberg_collect_css_card_monotony_issues(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						),
						mcp_abilities_gutenberg_collect_css_layout_risks(
							$css,
							sprintf( 'embedded-style-block-%d', $index + 1 )
						)
					);
				}
			}
			$full_width_shell_css_snippets = array_values( array_unique( $full_width_shell_css_snippets ) );
			$observations['shell_full_width_css_detected'] = ! empty( $full_width_shell_css_snippets );
			if ( ! empty( $content_measures ) ) {
				$observations['content_measure_values'] = array_values(
					array_unique(
						array_map(
							static function ( array $measure ): string {
								return (string) ( $measure['value'] ?? '' );
							},
							$content_measures
						)
					)
				);
				$issues = array_merge(
					$issues,
					mcp_abilities_gutenberg_collect_width_system_risks( $content_measures, 'rendered-embedded-style-blocks' )
				);
			}

			$inline_style_risks = mcp_abilities_gutenberg_collect_inline_style_layout_risks( $xpath, $content_wrapper );
			$observations['inline_style_count'] = (int) $inline_style_risks['count'];
			if ( ! empty( $inline_style_risks['issues'] ) ) {
				$issues = array_merge( $issues, $inline_style_risks['issues'] );
			}

			$alignfull_nodes = $xpath->query(
				'.//*[contains(concat(" ", normalize-space(@class), " "), " alignfull ")]',
				$content_wrapper
			);
			if ( $alignfull_nodes instanceof DOMNodeList && $alignfull_nodes->length > 0 ) {
				$observations['alignfull_block_count'] = $alignfull_nodes->length;
			}

			if ( $observations['alignfull_block_count'] > 0 && ! empty( $full_width_shell_css_snippets ) ) {
				$selectors = array();
				for ( $index = 0; $index < min( 3, $alignfull_nodes->length ); $index++ ) {
					$alignfull_node = $alignfull_nodes->item( $index );
					if ( $alignfull_node instanceof DOMElement ) {
						$selectors[] = mcp_abilities_gutenberg_describe_dom_element( $alignfull_node );
					}
				}

				$issues[] = array(
					'type'      => 'alignfull_breakout_risk',
					'severity'  => 'warning',
					'count'     => (int) $observations['alignfull_block_count'],
					'selectors' => $selectors,
					'snippets'  => array_slice( $full_width_shell_css_snippets, 0, 3 ),
					'message'   => 'Rendered content mixes alignfull Gutenberg blocks with CSS that already forces the surrounding shell to full width; theme breakout margins can then create horizontal page scrolling. Prefer neutralized alignfull margins or non-breakout full-width wrappers.',
				);
			}
		}
	}

	return array(
		'url'          => $url,
		'status_code'  => $status_code,
		'post'         => array(
			'id'     => (int) $post->ID,
			'type'   => (string) $post->post_type,
			'status' => (string) $post->post_status,
			'slug'   => (string) $post->post_name,
			'title'  => get_the_title( $post ),
		),
		'issues'       => $issues,
		'observations' => $observations,
	);
}

/**
 * Transform a normalized block tree.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_transform_blocks( array $input ) {
	$operation = isset( $input['operation'] ) ? sanitize_key( (string) $input['operation'] ) : '';
	$blocks    = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );

	switch ( $operation ) {
		case 'wrap-in-group':
			$attrs = isset( $input['attrs'] ) && is_array( $input['attrs'] ) ? $input['attrs'] : array( 'layout' => array( 'type' => 'constrained' ) );
			$wrapped = array(
				array(
					'block_name'    => 'core/group',
					'attrs'         => $attrs,
					'inner_blocks'  => $normalized,
					'inner_html'    => '',
					'inner_content' => array(),
				),
			);
			$normalized = $wrapped;
			break;

		case 'unwrap-single-group':
			if ( 1 === count( $normalized ) && 'core/group' === ( $normalized[0]['block_name'] ?? '' ) ) {
				$normalized = is_array( $normalized[0]['inner_blocks'] ?? null ) ? $normalized[0]['inner_blocks'] : array();
			}
			break;

		case 'append-block':
			$append = isset( $input['block'] ) && is_array( $input['block'] ) ? $input['block'] : null;
			if ( ! $append ) {
				return new WP_Error( 'mcp_gutenberg_transform_missing_block', 'block is required for append-block.' );
			}
			$normalized[] = mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $append ) );
			break;

		case 'prepend-block':
			$prepend = isset( $input['block'] ) && is_array( $input['block'] ) ? $input['block'] : null;
			if ( ! $prepend ) {
				return new WP_Error( 'mcp_gutenberg_transform_missing_block', 'block is required for prepend-block.' );
			}
			array_unshift( $normalized, mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $prepend ) ) );
			break;

		case 'replace-block':
			$replacement = isset( $input['block'] ) && is_array( $input['block'] ) ? $input['block'] : null;
			$index       = isset( $input['index'] ) ? (int) $input['index'] : -1;
			if ( ! $replacement || $index < 0 || $index >= count( $normalized ) ) {
				return new WP_Error( 'mcp_gutenberg_transform_invalid_replace', 'Valid index and replacement block are required for replace-block.' );
			}
			$normalized[ $index ] = mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $replacement ) );
			break;

		case 'remove-block':
			$index = isset( $input['index'] ) ? (int) $input['index'] : -1;
			if ( $index < 0 || $index >= count( $normalized ) ) {
				return new WP_Error( 'mcp_gutenberg_transform_invalid_remove', 'Valid index is required for remove-block.' );
			}
			array_splice( $normalized, $index, 1 );
			break;

		default:
			return new WP_Error( 'mcp_gutenberg_unknown_transform', 'Unsupported transform operation.' );
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $normalized );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'operation' => $operation,
		'content'   => $content,
		'summary'   => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'    => $normalized,
	);
}

/**
 * Audit Gutenberg content for editorial and structural issues.
 *
 * @param string $content Raw content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_audit_content( string $content ): array {
	$analysis = mcp_abilities_gutenberg_analyze_content( $content );
	$outline  = is_array( $analysis['outline'] ?? null ) ? $analysis['outline'] : array();
	$blocks   = is_array( $analysis['blocks'] ?? null ) ? $analysis['blocks'] : array();
	$issues   = array();

	$h1_count = 0;
	$prev_level = 0;
	foreach ( $outline as $heading ) {
		$level = isset( $heading['level'] ) ? (int) $heading['level'] : 0;
		$text  = isset( $heading['text'] ) ? trim( (string) $heading['text'] ) : '';
		if ( 1 === $level ) {
			$h1_count++;
		}
		if ( '' === $text ) {
			$issues[] = array(
				'severity' => 'warning',
				'code'     => 'empty_heading',
				'message'  => 'A heading block has no visible text.',
			);
		}
		if ( $prev_level > 0 && $level > $prev_level + 1 ) {
			$issues[] = array(
				'severity' => 'warning',
				'code'     => 'heading_level_jump',
				'message'  => sprintf( 'Heading hierarchy jumps from H%d to H%d.', $prev_level, $level ),
			);
		}
		$prev_level = $level;
	}

	if ( 0 === $h1_count ) {
		$issues[] = array(
			'severity' => 'warning',
			'code'     => 'missing_h1',
			'message'  => 'No H1 heading was found in the block document.',
		);
	}

	if ( $h1_count > 1 ) {
		$issues[] = array(
			'severity' => 'warning',
			'code'     => 'multiple_h1',
			'message'  => 'More than one H1 heading was found in the block document.',
		);
	}

	$walker = function ( array $nodes ) use ( &$walker, &$issues ): void {
		foreach ( $nodes as $node ) {
			$name  = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			$html  = isset( $node['inner_html'] ) ? (string) $node['inner_html'] : '';

			if ( 'core/button' === $name || 'core/buttons' === $name ) {
				if ( false === strpos( $html, 'href=' ) ) {
					$issues[] = array(
						'severity' => 'warning',
						'code'     => 'button_without_link',
						'message'  => 'A button block does not include a destination URL.',
					);
				}
			}

			if ( 'core/image' === $name && empty( $attrs['alt'] ) ) {
				$issues[] = array(
					'severity' => 'warning',
					'code'     => 'image_missing_alt',
					'message'  => 'An image block is missing alt text.',
				);
			}

			if ( 'core/spacer' === $name && ! empty( $attrs['height'] ) ) {
				$height = (string) $attrs['height'];
				if ( preg_match( '/^(\d+)px$/', $height, $matches ) && (int) $matches[1] > 160 ) {
					$issues[] = array(
						'severity' => 'notice',
						'code'     => 'oversized_spacer',
						'message'  => 'A spacer block exceeds 160px and may indicate layout padding being handled in content.',
					);
				}
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$walker( $node['inner_blocks'] );
			}
		}
	};

	$walker( $blocks );

	return array(
		'summary'    => $analysis['summary'],
		'validation' => $analysis['validation'],
		'outline'    => $outline,
		'issues'     => $issues,
		'issue_count'=> count( $issues ),
	);
}

/**
 * Evaluate Gutenberg design coherence from block structure and embedded styling.
 *
 * @param string $content Raw content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_evaluate_design( string $content ): array {
	$analysis     = mcp_abilities_gutenberg_analyze_content( $content );
	$validation   = is_array( $analysis['validation'] ?? null ) ? $analysis['validation'] : array();
	$layout_risks = is_array( $validation['layout_risks'] ?? null ) ? $validation['layout_risks'] : array();
	$issues       = array();
	$rendered_html = (string) ( $analysis['summary']['rendered_html'] ?? '' );
	$content_measures         = is_array( $layout_risks['content_measures'] ?? null ) ? $layout_risks['content_measures'] : array();
	$text_measures            = array();
	$nested_container_measures = array();
	$embedded_css_entries = array();
	$signals      = array(
		'content_measures'      => array_values( array_unique( array_filter( array_map( 'strval', wp_list_pluck( $content_measures, 'value' ) ) ) ) ),
		'issue_types'           => array_values( array_unique( array_filter( array_map( 'strval', wp_list_pluck( is_array( $layout_risks['issues'] ?? null ) ? $layout_risks['issues'] : array(), 'type' ) ) ) ) ),
		'has_full_bleed'        => in_array( 'alignfull_breakout_risk', array_map( 'strval', wp_list_pluck( is_array( $layout_risks['issues'] ?? null ) ? $layout_risks['issues'] : array(), 'type' ) ), true ),
		'top_level_block_count' => (int) ( $validation['top_level_block_count'] ?? 0 ),
	);

	foreach ( is_array( $layout_risks['issues'] ?? null ) ? $layout_risks['issues'] : array() as $issue ) {
		$type = (string) ( $issue['type'] ?? '' );
		if ( in_array( $type, array( 'section_width_inconsistency_risk', 'sibling_treatment_inconsistency', 'row_treatment_inconsistency', 'repeated_object_treatment_inconsistency', 'support_module_cramp_risk', 'followup_cluster_detachment_risk', 'fullwidth_section_seam_gap_risk', 'noninteractive_control_affordance_risk', 'spacing_rhythm_drift', 'alignfull_breakout_risk', 'button_contrast_risk', 'trailing_content_gap_risk', 'design_token_sprawl', 'card_monotony_risk' ), true ) ) {
			$issues[] = $issue;
		}
	}

	$raw_embedded_css_entries = is_array( $validation['embedded_css'] ?? null ) ? $validation['embedded_css'] : array();
	if ( ! empty( $raw_embedded_css_entries ) ) {
		foreach ( $raw_embedded_css_entries as $css_entry ) {
			if ( ! is_array( $css_entry ) || '' === trim( (string) ( $css_entry['css'] ?? '' ) ) ) {
				continue;
			}

			$embedded_css_entries[] = array(
				'source' => (string) ( $css_entry['source'] ?? 'embedded-css' ),
				'css'    => (string) $css_entry['css'],
			);
		}
	}

	foreach ( mcp_abilities_gutenberg_extract_embedded_css_entries( $content ) as $css_entry ) {
		$key = md5( (string) $css_entry['css'] );
		$embedded_css_entries[ $key ] = $css_entry;
	}

	foreach ( array_values( $embedded_css_entries ) as $css_entry ) {
		$css    = (string) ( $css_entry['css'] ?? '' );
		$source = (string) ( $css_entry['source'] ?? 'embedded-css' );
		if ( '' === $css ) {
			continue;
		}

		$text_measures = array_merge(
			$text_measures,
			mcp_abilities_gutenberg_collect_css_text_measures( $css, $source )
		);
		$nested_container_measures = array_merge(
			$nested_container_measures,
			mcp_abilities_gutenberg_collect_css_nested_container_measures( $css, $source )
		);
		$issues = array_merge(
			$issues,
			mcp_abilities_gutenberg_collect_css_noninteractive_control_affordance_issues( $css, $rendered_html, $source ),
			mcp_abilities_gutenberg_collect_css_followup_cluster_detachment_issues( $css, $rendered_html, $source ),
			mcp_abilities_gutenberg_collect_css_hero_heading_contrast_issues( $css, $source ),
			mcp_abilities_gutenberg_collect_css_subtle_tilt_issues( $css, $source ),
			mcp_abilities_gutenberg_collect_css_repeated_object_treatment_issues( $css, $source )
		);
	}

	$signals['text_measures'] = array_values( array_unique( array_filter( array_map( 'strval', wp_list_pluck( $text_measures, 'value' ) ) ) ) );
	$signals['nested_container_measures'] = array_values( array_unique( array_filter( array_map( 'strval', wp_list_pluck( $nested_container_measures, 'value' ) ) ) ) );

	$issues = array_merge(
		$issues,
		mcp_abilities_gutenberg_collect_internal_measure_mismatch_risks( $content_measures, array_merge( $text_measures, $nested_container_measures ), 'embedded-style-blocks' ),
		mcp_abilities_gutenberg_collect_block_spacing_rhythm_issues( $content ),
		mcp_abilities_gutenberg_collect_rendered_fullwidth_seam_gap_issues( $rendered_html, implode( "\n", array_map( static function ( array $entry ): string { return (string) ( $entry['css'] ?? '' ); }, array_values( $embedded_css_entries ) ) ), 'rendered-html' ),
		mcp_abilities_gutenberg_collect_rendered_support_module_cramp_issues( $rendered_html, 'rendered-html' ),
		mcp_abilities_gutenberg_collect_rendered_row_treatment_issues( $rendered_html, 'rendered-html' ),
		mcp_abilities_gutenberg_collect_repeated_object_treatment_issues( $rendered_html, 'rendered-html' ),
		mcp_abilities_gutenberg_collect_rendered_boxed_module_issues( $rendered_html, 'rendered-html' )
	);

	$signals['issue_types'] = array_values(
		array_unique(
			array_filter(
				array_map(
					'strval',
					wp_list_pluck( $issues, 'type' )
				)
			)
		)
	);

	$score = 100;
	$blocking_issue_types = array();
	foreach ( $issues as $issue ) {
		$type = (string) ( $issue['type'] ?? '' );
		if ( mcp_abilities_gutenberg_is_blocking_design_issue( $type ) ) {
			$blocking_issue_types[] = $type;
		}
		if ( 'section_width_inconsistency_risk' === $type ) {
			$score -= 18;
		} elseif ( 'internal_measure_mismatch' === $type ) {
			$score -= 14;
		} elseif ( 'support_module_cramp_risk' === $type ) {
			$score -= 16;
		} elseif ( 'followup_cluster_detachment_risk' === $type ) {
			$score -= 12;
		} elseif ( 'fullwidth_section_seam_gap_risk' === $type ) {
			$score -= 14;
		} elseif ( 'sibling_treatment_inconsistency' === $type ) {
			$score -= 14;
		} elseif ( 'row_treatment_inconsistency' === $type ) {
			$score -= 14;
		} elseif ( 'repeated_object_treatment_inconsistency' === $type ) {
			$score -= 16;
		} elseif ( 'noninteractive_control_affordance_risk' === $type ) {
			$score -= 14;
		} elseif ( 'spacing_rhythm_drift' === $type ) {
			$score -= 12;
		} elseif ( 'hero_heading_readability_risk' === $type ) {
			$score -= 14;
		} elseif ( 'subtle_tilt_ambiguity' === $type ) {
			$score -= 8;
		} elseif ( 'alignfull_breakout_risk' === $type ) {
			$score -= 12;
		} elseif ( 'button_contrast_risk' === $type ) {
			$score -= 16;
		} elseif ( 'trailing_content_gap_risk' === $type ) {
			$score -= 8;
		} elseif ( 'design_token_sprawl' === $type ) {
			$score -= 8;
		} elseif ( 'card_monotony_risk' === $type ) {
			$score -= 12;
		}
	}
	$score = max( 0, min( 100, $score ) );
	$blocking_issue_types = array_values( array_unique( array_filter( array_map( 'strval', $blocking_issue_types ) ) ) );

	$recommendations = array();
	if ( in_array( 'section_width_inconsistency_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Use one primary interior content width for intro panels, cards, quotes, reusable rows, and CTA sections. Reserve full bleed for heroes, strips, or deliberate breakouts.';
	}
	if ( in_array( 'internal_measure_mismatch', $signals['issue_types'], true ) ) {
		$recommendations[] = 'When a section shares the page width, do not quietly cap the quote, text lane, or nested columns row inside it to a much narrower measure unless that asymmetry is very clearly intentional.';
		$recommendations[] = 'In split editorial layouts, a shorter support column often needs vertical centering and a structural full-height divider. Otherwise the section can look abandoned even when the outer wrapper width is correct.';
	}
	if ( in_array( 'support_module_cramp_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Do not cram support modules into more columns than the copy can comfortably carry. Process rows, proof strips, and benefit rows should use fewer columns or larger modules when the text starts feeling pinched.';
	}
	if ( in_array( 'followup_cluster_detachment_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Keep follow-up proof rows, metadata strips, and support clusters visually attached to the CTA or copy they belong to. If the gap gets too loose, the cluster stops feeling like part of the same selling moment.';
	}
	if ( in_array( 'fullwidth_section_seam_gap_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'When adjacent full-width sections are meant to touch, explicitly neutralize flow-layout margins between them. Otherwise WordPress block gap can leave a visible seam or bright strip between sections.';
	}
	if ( in_array( 'sibling_treatment_inconsistency', $signals['issue_types'], true ) ) {
		$recommendations[] = 'When a repeated row uses accent styling such as tilt, shadow, or background shifts, either style all siblings coherently or make one spotlight card obviously intentional.';
	}
	if ( in_array( 'row_treatment_inconsistency', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Do not let one sibling in a repeated row become a random boxed module while the others stay open. Either treat the whole row as one family or make the spotlight item unmistakably deliberate.';
	}
	if ( in_array( 'repeated_object_treatment_inconsistency', $signals['issue_types'], true ) ) {
		$recommendations[] = 'When the same object repeats, keep its containment treatment coherent. Do not let one instance become boxed, tinted, or elevated while the matching instance stays plain unless one is intentionally featured.';
	}
	if ( in_array( 'noninteractive_control_affordance_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Do not style non-clickable labels, tags, or proof chips like buttons. If an element is not interactive, it should read as metadata or supporting proof, not as a tappable control. If it occupies real visual space, give it enough explanatory substance to justify that prominence.';
	}
	if ( in_array( 'spacing_rhythm_drift', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Reuse a smaller set of vertical spacing distances between major sections. Balanced pages usually feel tighter when they alternate between a compact gap and a generous gap instead of inventing a new distance every time.';
	}
	if ( in_array( 'hero_heading_readability_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Give visually led hero headings an explicit high-contrast text treatment. Big type still disappears fast when warm gradients or image-led backgrounds sit too close in value.';
	}
	if ( in_array( 'subtle_tilt_ambiguity', $signals['issue_types'], true ) ) {
		$recommendations[] = 'If you rotate a note card or accent module, tilt it enough to read as intentional. Very small angles often feel like accidental misalignment.';
	}
	if ( in_array( 'alignfull_breakout_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'If the shell is already full width, neutralize alignfull breakout margins instead of stacking shell-level and block-level breakout logic.';
	}
	if ( in_array( 'button_contrast_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Give button text and fills enough contrast that the CTA reads as active and intentional at a glance.';
	}
	if ( in_array( 'trailing_content_gap_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Avoid large bottom padding on the main content wrapper unless the footer transition is intentional.';
	}
	if ( in_array( 'design_token_sprawl', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Reduce the number of corner-radius and shadow variants so repeated components feel like one system instead of many unrelated treatments.';
	}
	if ( in_array( 'card_monotony_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Break the page out of all-card mode. Keep some sections open, linear, or full-bleed so the design breathes.';
	}

	return array(
		'score'           => $score,
		'issues'          => $issues,
		'issue_count'     => count( $issues ),
		'passes'          => 0 === count( $blocking_issue_types ),
		'blocking_issue_count' => count( $blocking_issue_types ),
		'blocking_issue_types' => $blocking_issue_types,
		'signals'         => $signals,
		'recommendations' => $recommendations,
		'summary'         => $analysis['summary'],
	);
}

/**
 * Suggest concrete design fixes for Gutenberg content.
 *
 * @param string $content Raw content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_suggest_design_fixes( string $content ): array {
	$evaluation  = mcp_abilities_gutenberg_evaluate_design( $content );
	$issues      = is_array( $evaluation['issues'] ?? null ) ? $evaluation['issues'] : array();
	$suggestions = array();
	$added_types = array();

	foreach ( $issues as $issue ) {
		$type = (string) ( $issue['type'] ?? '' );

		if ( 'section_width_inconsistency_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['selectors'] ?? null ) ? $issue['selectors'] : array() ) ),
				'problem'      => 'Major sections are using different fixed content widths.',
				'fixes'        => array(
					'Pick one main interior content width and reuse it across intro panels, card rows, quotes, reusable rows, and CTA sections.',
					'Keep only heroes, strips, or intentional feature sections full bleed.',
					'If one section needs to feel narrower, make that contrast obvious and deliberate rather than accidental.',
				),
			);
		} elseif ( 'internal_measure_mismatch' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['selectors'] ?? null ) ? $issue['selectors'] : array() ) ),
				'problem'      => 'A section shares the page width, but the usable lane inside it stays much narrower, so the section still feels visibly unfinished.',
				'fixes'        => array(
					'Let the inner quote, text block, or columns row inherit the section width rhythm unless a narrower editorial lane is the point of the design.',
					'If you intentionally want a narrow text measure, pair it with a second visual anchor or asymmetrical composition so the empty side feels designed rather than abandoned.',
					'Do not declare a quiet text max-width or leave a nested `.wp-block-columns` row at Gutenberg\'s smaller default measure inside a newly widened section and assume the problem is solved. The usable measure matters as much as the wrapper.',
					'In two-column editorial sections, vertically center the smaller support column when its content is much shorter than the dominant column. Top alignment often makes the short column look stranded.',
					'If the split layout uses a divider, attach it to the column or section structure so it spans the intended full height. A border on a short inner text wrapper usually makes the divider look accidentally truncated.',
				),
			);
		} elseif ( 'support_module_cramp_risk' === $type ) {
			$suggestions[] = array(
				'type'      => $type,
				'selectors' => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'   => 'A support row is trying to carry too much copy in too many side-by-side modules, so the row reads cramped even though the page shell is wide enough.',
				'fixes'     => array(
					'Reduce the number of columns in that row, especially for process steps, proof strips, and benefit rows with both a heading and a body line.',
					'Let support modules grow wider before adding more siblings. Three or four modules across is only safe when each item is genuinely brief.',
					'If the row needs to keep the same item count, shorten the copy sharply or split the row into two calmer lines instead of forcing every module to stay narrow.',
				),
			);
		} elseif ( 'followup_cluster_detachment_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'      => 'A follow-up proof row or support cluster sits too far below the CTA or copy it belongs to, so the component feels split into separate moments.',
				'fixes'        => array(
					'Tighten the local top spacing so the support row reads as part of the same cluster rather than a detached afterthought.',
					'Use a compact gap for CTA-to-proof transitions inside a hero or intro. Save larger gaps for real section breaks.',
					'If the support row needs stronger separation, give it a clearer structural reason such as a contrasting surface or deliberate section wrapper instead of only empty air.',
				),
			);
		} elseif ( 'fullwidth_section_seam_gap_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'      => 'Adjacent full-width sections are separated by default flow spacing, creating a visible seam even though they should feel continuous.',
				'fixes'        => array(
					'Reset the flow-layout seam between adjacent full-width sections with an explicit `margin-block-start:0` or equivalent adjacent-sibling rule.',
					'If the sections are supposed to separate, make the separation intentional with a real divider, color shift, or spacing rhythm instead of an accidental bright strip.',
					'Do not rely on WordPress default block gap between stacked full-width bands when the visual intent is edge-to-edge continuity.',
				),
			);
		} elseif ( 'sibling_treatment_inconsistency' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) ),
				'problem'      => 'Only part of a repeated component family uses accent styling.',
				'fixes'        => array(
					'Apply the treatment family across all siblings, even if the exact intensity differs slightly.',
					'If one item is supposed to be the spotlight card, make it clearly dominant instead of leaving another item untreated by accident.',
					'Prefer small controlled variation across a row instead of leaving one card visually disconnected from the set.',
				),
			);
		} elseif ( 'row_treatment_inconsistency' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'      => 'A repeated row mixes one boxed sibling with open siblings, so the row looks half-switched between two design systems.',
				'fixes'        => array(
					'Make the siblings share one treatment family: all open, all softly contained, or one obviously spotlighted item with stronger hierarchy.',
					'If one item is meant to stand out, increase the contrast enough that it reads as a featured module instead of a stray leftover card.',
					'When in doubt, remove the lone box treatment first. Open rows usually feel cleaner than one random contained column.',
				),
			);
		} elseif ( 'repeated_object_treatment_inconsistency' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'      => 'Matching repeated objects are being presented with different containment treatments, so they no longer read as the same component family.',
				'fixes'        => array(
					'If the same object repeats, keep its background, border, and elevation logic consistent across instances.',
					'Only break that consistency when one instance is intentionally promoted into a feature state with clearly stronger hierarchy.',
					'Compare repeated modules side by side; if one looks boxed and the other looks raw without a narrative reason, normalize them.',
				),
			);
		} elseif ( 'noninteractive_control_affordance_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array_merge(
							array( (string) ( $issue['selector'] ?? '' ) ),
							array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) )
						)
					)
				),
				'problem'      => 'Non-interactive labels are borrowing the visual language of buttons or pills, so they imply an action that does not exist.',
				'fixes'        => array(
					'Quiet the styling so the tokens read as metadata, scope labels, or proof instead of controls.',
					'If the row is visually prominent, upgrade bare labels into a short proof strip with one useful supporting line per item.',
					'Reserve filled pill/button treatments for real links, filters, toggles, and calls to action.',
					'If the labels genuinely need interaction, turn them into actual links or controls instead of keeping them inert.',
				),
			);
		} elseif ( 'spacing_rhythm_drift' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['examples'] ?? null ) ? $issue['examples'] : array() ) ),
				'problem'      => 'Major sections are separated by too many unrelated distances, so the page rhythm starts to feel arbitrary.',
				'fixes'        => array(
					'Reduce the page to a smaller spacing scale, for example one compact section gap and one generous section gap.',
					'Prefer spacing on the section wrappers themselves instead of stacking several spacer blocks with different heights.',
					'Reserve very large gaps for deliberate pauses such as hero-to-body transitions, not for ordinary section changes.',
				),
			);
		} elseif ( 'hero_heading_readability_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values(
					array_filter(
						array(
							(string) ( $issue['selector'] ?? '' ),
							(string) ( $issue['background_selector'] ?? '' ),
						)
					)
				),
				'problem'      => 'The main hero title does not separate strongly enough from its background treatment.',
				'fixes'        => array(
					'Set an explicit heading color with stronger contrast against the darkest and lightest background stops.',
					'Use a subtle text shadow only as support, not as the primary contrast strategy.',
					'Check the hero at a glance first: if the name or headline softens into the background, the display type is underpowered.',
				),
			);
		} elseif ( 'subtle_tilt_ambiguity' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array( (string) ( $issue['selector'] ?? '' ) ),
				'problem'      => 'A rotated element is tilted so slightly that it risks reading as accidental rather than intentional.',
				'fixes'        => array(
					'Either remove the rotation and let the element sit cleanly, or increase the angle enough that the gesture is clearly willed.',
					'Keep slight playful tilts for secondary notes or ephemera, not for major content modules that need to feel anchored.',
					'When in doubt, compare the module straight versus clearly tilted. The ambiguous middle usually loses.',
				),
			);
		} elseif ( 'alignfull_breakout_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['selectors'] ?? null ) ? $issue['selectors'] : array() ) ),
				'problem'      => 'Alignfull blocks are layered on top of shell-level full-width CSS.',
				'fixes'        => array(
					'Neutralize alignfull margins if the shell already spans the full viewport.',
					'Choose one breakout system: either theme-shell full width or Gutenberg breakout margins, not both.',
					'Test on desktop widths first because sideways bleed usually shows there before mobile.',
				),
			);
		} elseif ( 'button_contrast_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array( (string) ( $issue['selector'] ?? '' ) ),
				'problem'      => 'The CTA looks disabled or hard to read because button text and fill are too close in value.',
				'fixes'        => array(
					'Darken the button fill or lighten the text so the action reads instantly.',
					'Keep button contrast comfortably strong instead of aiming for a subtle “luxury” tone that becomes weak.',
					'If the button sits inside a soft card, give it enough visual weight to separate from the surrounding paper tone.',
				),
			);
		} elseif ( 'trailing_content_gap_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array( (string) ( $issue['selector'] ?? '' ) ),
				'problem'      => 'Bottom padding on the main content wrapper can leave visible blank space after the last section.',
				'fixes'        => array(
					'Move end-spacing onto the final section itself if the transition is intentional.',
					'Remove wrapper-level bottom padding when the footer is hidden or minimal.',
					'Check the document bottom after hiding theme chrome, because shell padding often becomes visible only then.',
				),
			);
		} elseif ( 'design_token_sprawl' === $type ) {
			if ( in_array( $type, $added_types, true ) ) {
				continue;
			}

			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array(),
				'problem'      => 'The page keeps introducing new visual tokens for the same design language.',
				'fixes'        => array(
					'Collapse radii into a smaller set, for example one small card radius and one large panel radius.',
					'Limit shadows to a couple of elevation levels instead of inventing a new shadow for every section.',
					'Let hierarchy come from composition and contrast, not from endlessly multiplying decorative token values.',
				),
			);
			$added_types[] = $type;
		} elseif ( 'card_monotony_risk' === $type ) {
			$suggestions[] = array(
				'type'         => $type,
				'selectors'    => array_values( array_map( 'strval', is_array( $issue['selectors'] ?? null ) ? $issue['selectors'] : array() ) ),
				'problem'      => 'Too many sections are enclosed in similar card treatments, which makes the page feel templated and repetitive.',
				'fixes'        => array(
					'Flatten some sections so they live directly on the page background instead of inside another rounded box.',
					'Use a mix of open text bands, dividers, full-bleed sections, and only a few true cards.',
					'Reserve the boxed treatment for moments that actually need containment, such as notes, quotes, or operational modules.',
				),
			);
		}
	}

	return array(
		'score'           => (int) ( $evaluation['score'] ?? 0 ),
		'issue_count'     => (int) ( $evaluation['issue_count'] ?? 0 ),
		'passes'          => (bool) ( $evaluation['passes'] ?? false ),
		'blocking_issue_count' => (int) ( $evaluation['blocking_issue_count'] ?? 0 ),
		'blocking_issue_types' => array_values( array_map( 'strval', is_array( $evaluation['blocking_issue_types'] ?? null ) ? $evaluation['blocking_issue_types'] : array() ) ),
		'issues'          => $issues,
		'suggestions'     => $suggestions,
		'summary'         => $evaluation['summary'] ?? array(),
	);
}

/**
 * Evaluate Gutenberg copy quality with lightweight editorial heuristics.
 *
 * @param string $content Raw content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_evaluate_copy( string $content ): array {
	$analysis    = mcp_abilities_gutenberg_analyze_content( $content );
	$blocks      = is_array( $analysis['blocks'] ?? null ) ? $analysis['blocks'] : array();
	$plain_text  = trim( wp_strip_all_tags( $content ) );
	$issues      = array();
	$metrics     = array(
		'paragraphs'          => 0,
		'long_paragraphs'     => 0,
		'long_sentences'      => 0,
		'generic_ctas'        => 0,
		'generic_headings'    => 0,
		'exclamation_marks'   => substr_count( $plain_text, '!' ),
		'all_caps_fragments'  => 0,
	);

	$generic_heading_text = array( 'welcome', 'introduction', 'overview', 'section', 'about', 'title' );
	$generic_cta_text     = array( 'learn more', 'read more', 'click here', 'submit', 'more', 'get started' );

	$walker = static function ( array $nodes ) use ( &$walker, &$issues, &$metrics, $generic_heading_text, $generic_cta_text ): void {
		foreach ( $nodes as $node ) {
			$name  = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			$text  = trim( wp_strip_all_tags( (string) ( $node['inner_html'] ?? '' ) ) );

			if ( 'core/paragraph' === $name ) {
				$metrics['paragraphs']++;
				$word_count = str_word_count( $text );
				if ( $word_count > 90 ) {
					$metrics['long_paragraphs']++;
					$issues[] = array(
						'severity' => 'warning',
						'code'     => 'long_paragraph',
						'message'  => sprintf( 'A paragraph has %d words, which is likely too dense for scan-friendly block content.', $word_count ),
						'context'  => mb_substr( $text, 0, 140 ),
					);
				}
			}

			if ( 'core/heading' === $name ) {
				$heading_text = strtolower( trim( $text ) );
				if ( in_array( $heading_text, $generic_heading_text, true ) ) {
					$metrics['generic_headings']++;
					$issues[] = array(
						'severity' => 'notice',
						'code'     => 'generic_heading',
						'message'  => sprintf( 'Heading "%s" is generic and may undersell the section.', $text ),
					);
				}
			}

			if ( 'core/button' === $name ) {
				$label = strtolower( trim( wp_strip_all_tags( (string) ( $attrs['text'] ?? $text ) ) ) );
				if ( in_array( $label, $generic_cta_text, true ) ) {
					$metrics['generic_ctas']++;
					$issues[] = array(
						'severity' => 'warning',
						'code'     => 'generic_cta',
						'message'  => sprintf( 'CTA "%s" is vague. A more specific action label would convert better.', $text ),
					);
				}
			}

			if ( preg_match_all( '/\b[A-Z]{4,}\b/u', $text, $caps_matches ) ) {
				$metrics['all_caps_fragments'] += count( $caps_matches[0] );
				if ( count( $caps_matches[0] ) >= 2 ) {
					$issues[] = array(
						'severity' => 'notice',
						'code'     => 'all_caps_copy',
						'message'  => 'This block contains multiple all-caps fragments, which can read as shouty.',
						'context'  => mb_substr( $text, 0, 140 ),
					);
				}
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$walker( $node['inner_blocks'] );
			}
		}
	};

	$walker( $blocks );

	$sentences = preg_split( '/(?<=[.!?])\s+/u', $plain_text );
	$sentences = array_values( array_filter( array_map( 'trim', is_array( $sentences ) ? $sentences : array() ) ) );
	foreach ( $sentences as $sentence ) {
		$word_count = str_word_count( $sentence );
		if ( $word_count > 28 ) {
			$metrics['long_sentences']++;
		}
	}

	if ( $metrics['long_sentences'] > 2 ) {
		$issues[] = array(
			'severity' => 'notice',
			'code'     => 'many_long_sentences',
			'message'  => 'Several sentences are long enough to reduce readability and scanning speed.',
		);
	}

	if ( $metrics['exclamation_marks'] > 3 ) {
		$issues[] = array(
			'severity' => 'notice',
			'code'     => 'excessive_exclamation',
			'message'  => 'The copy uses many exclamation marks, which may weaken tone and credibility.',
		);
	}

	$word_count         = max( 1, str_word_count( $plain_text ) );
	$sentence_count     = max( 1, count( $sentences ) );
	$avg_sentence_words = round( $word_count / $sentence_count, 1 );
	$score              = 100;
	$score             -= $metrics['long_paragraphs'] * 8;
	$score             -= $metrics['generic_ctas'] * 8;
	$score             -= $metrics['generic_headings'] * 6;
	$score             -= max( 0, $metrics['long_sentences'] - 1 ) * 4;
	$score             -= max( 0, $metrics['exclamation_marks'] - 2 ) * 2;
	$score             -= min( 12, $metrics['all_caps_fragments'] * 2 );
	$score              = max( 0, min( 100, $score ) );

	$recommendations = array();
	if ( $metrics['long_paragraphs'] > 0 ) {
		$recommendations[] = 'Break long paragraph blocks into shorter chunks or lists for easier scanning.';
	}
	if ( $metrics['generic_ctas'] > 0 ) {
		$recommendations[] = 'Use CTA labels that state the action or value, such as "Order Fresh Bread" instead of "Learn More".';
	}
	if ( $metrics['generic_headings'] > 0 ) {
		$recommendations[] = 'Rewrite generic headings so they communicate a concrete promise, offer, or topic.';
	}
	if ( $metrics['long_sentences'] > 2 ) {
		$recommendations[] = 'Shorten sentence length to make the page easier to skim on mobile.';
	}
	if ( $metrics['exclamation_marks'] > 3 || $metrics['all_caps_fragments'] > 1 ) {
		$recommendations[] = 'Reduce shouty emphasis and let hierarchy, spacing, and stronger wording carry the message.';
	}

	return array(
		'score'           => $score,
		'metrics'         => array_merge(
			$metrics,
			array(
				'word_count'          => $word_count,
				'sentence_count'      => $sentence_count,
				'avg_sentence_words'  => $avg_sentence_words,
			)
		),
		'issues'          => $issues,
		'issue_count'     => count( $issues ),
		'recommendations' => $recommendations,
		'summary'         => $analysis['summary'],
		'outline'         => $analysis['outline'],
	);
}

/**
 * Suggest targeted copy fixes for Gutenberg content.
 *
 * @param string $content Raw content.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_suggest_copy_fixes( string $content ): array {
	$evaluation = mcp_abilities_gutenberg_evaluate_copy( $content );
	$analysis   = mcp_abilities_gutenberg_analyze_content( $content );
	$blocks     = is_array( $analysis['blocks'] ?? null ) ? $analysis['blocks'] : array();
	$suggestions = array();

	$heading_map = array(
		'welcome'      => array( 'Why Customers Make the Trip', 'Fresh From the Oven Every Morning' ),
		'introduction' => array( 'What Makes This Worth Ordering', 'Why This Offer Stands Out' ),
		'overview'     => array( 'What You Get', 'How It Works' ),
		'section'      => array( 'What To Expect', 'Why It Matters' ),
		'about'        => array( 'Why People Come Back', 'Built Around Craft, Not Volume' ),
		'title'        => array( 'The Main Draw', 'What This Section Is Really About' ),
	);
	$cta_map = array(
		'learn more'  => array( 'See Today\'s Menu', 'View the Full Offer' ),
		'read more'   => array( 'See the Details', 'Explore the Full Story' ),
		'click here'  => array( 'Book a Visit', 'Start Your Order' ),
		'submit'      => array( 'Send Request', 'Confirm Booking' ),
		'more'        => array( 'See More Options', 'Browse the Full Range' ),
		'get started' => array( 'Start Your Order', 'Plan Your First Visit' ),
	);

	$walker = static function ( array $nodes, array $path = array() ) use ( &$walker, &$suggestions, $heading_map, $cta_map ): void {
		foreach ( $nodes as $index => $node ) {
			$current_path = array_merge( $path, array( $index ) );
			$name         = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			$attrs        = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			$text         = trim( wp_strip_all_tags( (string) ( $node['inner_html'] ?? '' ) ) );
			$text_lc      = strtolower( $text );

			if ( 'core/heading' === $name && isset( $heading_map[ $text_lc ] ) ) {
				$suggestions[] = array(
					'path'        => $current_path,
					'block_name'  => $name,
					'issue'       => 'generic_heading',
					'original'    => $text,
					'suggestions' => $heading_map[ $text_lc ],
				);
			}

			if ( 'core/button' === $name ) {
				$label    = trim( wp_strip_all_tags( (string) ( $attrs['text'] ?? $text ) ) );
				$label_lc = strtolower( $label );
				if ( isset( $cta_map[ $label_lc ] ) ) {
					$suggestions[] = array(
						'path'        => $current_path,
						'block_name'  => $name,
						'issue'       => 'generic_cta',
						'original'    => $label,
						'suggestions' => $cta_map[ $label_lc ],
					);
				}
			}

			if ( 'core/paragraph' === $name ) {
				$word_count = str_word_count( $text );
				if ( $word_count > 90 ) {
					$suggestions[] = array(
						'path'        => $current_path,
						'block_name'  => $name,
						'issue'       => 'long_paragraph',
						'original'    => mb_substr( $text, 0, 180 ),
						'suggestions' => array(
							'Split this paragraph into two shorter blocks, with the first sentence carrying the main point.',
							'Turn the benefits or proof points into a short list so the section scans faster.',
						),
					);
				}
			}

			if ( preg_match_all( '/\b[A-Z]{4,}\b/u', $text, $caps_matches ) && count( $caps_matches[0] ) >= 2 ) {
				$suggestions[] = array(
					'path'        => $current_path,
					'block_name'  => $name,
					'issue'       => 'all_caps_copy',
					'original'    => mb_substr( $text, 0, 180 ),
					'suggestions' => array(
						'Keep only one emphasis word if needed and let heading size or button styling do the rest.',
						'Rephrase this line in sentence case so it feels more credible and less shouty.',
					),
				);
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$walker( $node['inner_blocks'], $current_path );
			}
		}
	};

	$walker( $blocks );

	return array(
		'score'              => $evaluation['score'] ?? 0,
		'issue_count'        => $evaluation['issue_count'] ?? 0,
		'issues'             => $evaluation['issues'] ?? array(),
		'suggestions'        => $suggestions,
		'suggestion_count'   => count( $suggestions ),
		'recommendations'    => $evaluation['recommendations'] ?? array(),
		'summary'            => $analysis['summary'] ?? array(),
		'outline'            => $analysis['outline'] ?? array(),
	);
}

/**
 * Get block style variations and style-relevant supports for a block.
 *
 * @param string $name Block name.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_block_style_variations( string $name ) {
	$details = mcp_abilities_gutenberg_get_block_details( $name );
	if ( is_wp_error( $details ) ) {
		return $details;
	}

	$style_guide = mcp_abilities_gutenberg_get_style_guide();
	$supports    = is_array( $details['supports'] ?? null ) ? $details['supports'] : array();

	return array(
		'name'             => $name,
		'styles'           => is_array( $details['styles'] ?? null ) ? $details['styles'] : array(),
		'supports'         => $supports,
		'color_support'    => $supports['color'] ?? null,
		'spacing_support'  => $supports['spacing'] ?? null,
		'typography_support' => $supports['typography'] ?? null,
		'shadow_support'   => $supports['shadow'] ?? null,
		'theme_palette'    => $style_guide['palette'] ?? array(),
		'theme_gradients'  => $style_guide['gradients'] ?? array(),
		'theme_font_sizes' => $style_guide['font_sizes'] ?? array(),
		'theme_spacing'    => $style_guide['spacing_sizes'] ?? array(),
	);
}

/**
 * Normalize a block path array.
 *
 * @param mixed $path Input path.
 * @return array<int,int>|WP_Error
 */
function mcp_abilities_gutenberg_normalize_block_path( $path ) {
	if ( ! is_array( $path ) ) {
		return new WP_Error( 'mcp_gutenberg_invalid_path', 'path must be an array of indexes.' );
	}

	$normalized = array();
	foreach ( $path as $segment ) {
		if ( ! is_numeric( $segment ) ) {
			return new WP_Error( 'mcp_gutenberg_invalid_path', 'path must contain only numeric indexes.' );
		}
		$normalized[] = (int) $segment;
	}

	return $normalized;
}

/**
 * Read a nested block by path from a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param array<int,int>                 $path Block path.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_block_by_path( array $blocks, array $path ) {
	$current = $blocks;
	$node    = null;

	foreach ( $path as $depth => $index ) {
		if ( ! isset( $current[ $index ] ) || ! is_array( $current[ $index ] ) ) {
			return new WP_Error( 'mcp_gutenberg_path_not_found', sprintf( 'Block path segment %d was not found.', $depth ) );
		}
		$node = $current[ $index ];
		$current = isset( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ? $node['inner_blocks'] : array();
	}

	return is_array( $node ) ? $node : new WP_Error( 'mcp_gutenberg_path_not_found', 'Block path was not found.' );
}

/**
 * Mutate a normalized block tree by path.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param array<int,int>                 $path Block path.
 * @param callable                       $mutator Mutator callback.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function mcp_abilities_gutenberg_mutate_blocks_at_path( array $blocks, array $path, callable $mutator ) {
	$index = array_shift( $path );
	if ( null === $index || ! isset( $blocks[ $index ] ) || ! is_array( $blocks[ $index ] ) ) {
		return new WP_Error( 'mcp_gutenberg_path_not_found', 'Target block path was not found.' );
	}

	if ( empty( $path ) ) {
		$result = $mutator( $blocks[ $index ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $result ) {
			array_splice( $blocks, $index, 1 );
			return array_values( $blocks );
		}

		if ( ! is_array( $result ) ) {
			return new WP_Error( 'mcp_gutenberg_invalid_mutation', 'Mutator must return a block array, null, or WP_Error.' );
		}

		$blocks[ $index ] = $result;
		return $blocks;
	}

	$inner_blocks = isset( $blocks[ $index ]['inner_blocks'] ) && is_array( $blocks[ $index ]['inner_blocks'] ) ? $blocks[ $index ]['inner_blocks'] : array();
	$mutated_inner = mcp_abilities_gutenberg_mutate_blocks_at_path( $inner_blocks, $path, $mutator );
	if ( is_wp_error( $mutated_inner ) ) {
		return $mutated_inner;
	}

	$blocks[ $index ]['inner_blocks'] = $mutated_inner;
	return $blocks;
}

/**
 * Insert a block into a normalized block tree at a parent path and position.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param array<int,int>                 $parent_path Parent block path, or empty for the root list.
 * @param array<string,mixed>            $block Block to insert.
 * @param int                            $position Position within the target children.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function mcp_abilities_gutenberg_insert_block_at_path( array $blocks, array $parent_path, array $block, int $position ) {
	if ( empty( $parent_path ) ) {
		$position = max( 0, min( $position, count( $blocks ) ) );
		array_splice( $blocks, $position, 0, array( $block ) );
		return array_values( $blocks );
	}

	return mcp_abilities_gutenberg_mutate_blocks_at_path(
		$blocks,
		$parent_path,
		static function ( array $parent_block ) use ( $block, $position ) {
			$inner    = isset( $parent_block['inner_blocks'] ) && is_array( $parent_block['inner_blocks'] ) ? $parent_block['inner_blocks'] : array();
			$position = max( 0, min( $position, count( $inner ) ) );
			array_splice( $inner, $position, 0, array( $block ) );
			$parent_block['inner_blocks'] = array_values( $inner );
			return $parent_block;
		}
	);
}

/**
 * Remove a block from a normalized block tree at path and return both tree and block.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param array<int,int>                 $path Block path.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_extract_block_at_path( array $blocks, array $path ) {
	$target = mcp_abilities_gutenberg_get_block_by_path( $blocks, $path );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	$remaining = mcp_abilities_gutenberg_mutate_blocks_at_path(
		$blocks,
		$path,
		static function (): ?array {
			return null;
		}
	);

	if ( is_wp_error( $remaining ) ) {
		return $remaining;
	}

	return array(
		'block'  => $target,
		'blocks' => $remaining,
	);
}

/**
 * Replace text inside a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @param string                         $search Search string.
 * @param string                         $replace Replacement string.
 * @param array<int,int>|null            $path Optional subtree path.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_replace_text_in_blocks( array $blocks, string $search, string $replace, ?array $path = null ) {
	if ( '' === $search ) {
		return new WP_Error( 'mcp_gutenberg_missing_search', 'search must not be empty.' );
	}

	$replacement_count = 0;

	$walker = static function ( array $nodes ) use ( &$walker, $search, $replace, &$replacement_count ) {
		foreach ( $nodes as $index => $node ) {
			if ( isset( $node['inner_html'] ) && is_string( $node['inner_html'] ) ) {
				$nodes[ $index ]['inner_html'] = str_replace( $search, $replace, $node['inner_html'], $count );
				$replacement_count += $count;
			}

			if ( isset( $node['inner_content'] ) && is_array( $node['inner_content'] ) ) {
				foreach ( $node['inner_content'] as $content_index => $chunk ) {
					if ( is_string( $chunk ) ) {
						$nodes[ $index ]['inner_content'][ $content_index ] = str_replace( $search, $replace, $chunk, $count );
						$replacement_count += $count;
					}
				}
			}

			if ( ! empty( $node['attrs'] ) && is_array( $node['attrs'] ) ) {
				foreach ( array( 'content', 'label', 'placeholder', 'alt', 'caption' ) as $attr_key ) {
					if ( isset( $node['attrs'][ $attr_key ] ) && is_string( $node['attrs'][ $attr_key ] ) ) {
						$nodes[ $index ]['attrs'][ $attr_key ] = str_replace( $search, $replace, $node['attrs'][ $attr_key ], $count );
						$replacement_count += $count;
					}
				}
			}

			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$nodes[ $index ]['inner_blocks'] = $walker( $node['inner_blocks'] );
			}
		}

		return $nodes;
	};

	if ( null === $path || array() === $path ) {
		$mutated = $walker( $blocks );
	} else {
		$mutated = mcp_abilities_gutenberg_mutate_blocks_at_path(
			$blocks,
			$path,
			static function ( array $block ) use ( $walker ) {
				$wrapped = $walker( array( $block ) );
				return $wrapped[0] ?? $block;
			}
		);
	}

	if ( is_wp_error( $mutated ) ) {
		return $mutated;
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'content'            => $content,
		'summary'            => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'             => $mutated,
		'replacement_count'  => $replacement_count,
	);
}

/**
 * Apply a nested block-tree mutation.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_mutate_block_tree( array $input ) {
	$path   = mcp_abilities_gutenberg_normalize_block_path( $input['path'] ?? null );
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $path ) ) {
		return $path;
	}
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	$operation  = isset( $input['operation'] ) ? sanitize_key( (string) $input['operation'] ) : '';
	$before     = mcp_abilities_gutenberg_get_block_by_path( $normalized, $path );
	if ( is_wp_error( $before ) ) {
		return $before;
	}

	$mutated = mcp_abilities_gutenberg_mutate_blocks_at_path(
		$normalized,
		$path,
		static function ( array $block ) use ( $input, $operation ) {
			switch ( $operation ) {
				case 'update-attrs':
					$attrs = isset( $input['attrs'] ) && is_array( $input['attrs'] ) ? $input['attrs'] : array();
					$block['attrs'] = array_replace_recursive( is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(), $attrs );
					return $block;

				case 'replace-block':
					if ( empty( $input['block'] ) || ! is_array( $input['block'] ) ) {
						return new WP_Error( 'mcp_gutenberg_missing_block', 'block is required for replace-block.' );
					}
					return mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $input['block'] ) );

				case 'remove-block':
					return null;

				default:
					return new WP_Error( 'mcp_gutenberg_unknown_mutation', 'Unsupported mutation operation.' );
			}
		}
	);

	if ( is_wp_error( $mutated ) ) {
		return $mutated;
	}

	$after        = mcp_abilities_gutenberg_get_block_by_path( $mutated, $path );
	$render_risks = array();
	if ( ! is_wp_error( $after ) && 'update-attrs' === $operation ) {
		$render_risks = mcp_abilities_gutenberg_get_static_render_risks_for_block( $before, $after, $path );
	}

	if ( ! empty( $render_risks ) && empty( $input['allow_unsafe_static_markup'] ) ) {
		$first_risk = $render_risks[0];
		return new WP_Error(
			'mcp_gutenberg_static_render_risk',
			sprintf(
				'Unsafe static block attr mutation detected for %1$s at path [%2$s]; changed attrs: %3$s. Pass allow_unsafe_static_markup=true only if you will regenerate the saved markup separately.',
				(string) ( $first_risk['block_name'] ?? 'block' ),
				implode( ',', array_map( 'strval', $path ) ),
				implode( ', ', array_map( 'strval', $first_risk['changed_attrs'] ?? array() ) )
			),
			array(
				'render_risks' => $render_risks,
			)
		);
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'operation' => $operation,
		'path'      => $path,
		'content'   => $content,
		'summary'   => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'    => $mutated,
		'render_risks' => $render_risks,
		'render_safe'  => empty( $render_risks ),
	);
}

/**
 * Set block lock attributes by path.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_set_block_lock( array $input ) {
	$lock = array(
		'move'   => ! empty( $input['lock_move'] ),
		'remove' => ! empty( $input['lock_remove'] ),
	);

	$input['operation'] = 'update-attrs';
	$input['attrs']     = array(
		'lock' => $lock,
	);

	return mcp_abilities_gutenberg_mutate_block_tree( $input );
}

/**
 * Set allowedBlocks on a container block by path.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_set_allowed_blocks( array $input ) {
	$allowed = isset( $input['allowed_blocks'] ) && is_array( $input['allowed_blocks'] ) ? array_values( array_map( 'strval', $input['allowed_blocks'] ) ) : array();

	$input['operation'] = 'update-attrs';
	$input['attrs']     = array(
		'allowedBlocks' => $allowed,
	);

	return mcp_abilities_gutenberg_mutate_block_tree( $input );
}

/**
 * Set template lock on a block by path.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_set_template_lock( array $input ) {
	$template_lock = $input['template_lock'] ?? false;
	if ( ! in_array( $template_lock, array( false, 'all', 'insert', 'contentOnly' ), true ) ) {
		return new WP_Error( 'mcp_gutenberg_invalid_template_lock', 'template_lock must be false, all, insert, or contentOnly.' );
	}

	$input['operation'] = 'update-attrs';
	$input['attrs']     = array(
		'templateLock' => $template_lock,
	);

	return mcp_abilities_gutenberg_mutate_block_tree( $input );
}

/**
 * Insert a child block into a container block's inner blocks by path.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_insert_inner_block( array $input ) {
	$path   = mcp_abilities_gutenberg_normalize_block_path( $input['path'] ?? null );
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $path ) ) {
		return $path;
	}
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}
	if ( empty( $input['block'] ) || ! is_array( $input['block'] ) ) {
		return new WP_Error( 'mcp_gutenberg_missing_block', 'block is required.' );
	}

	$normalized   = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	$insert_block = mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $input['block'] ) );
	$position     = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : -1;

	$mutated = mcp_abilities_gutenberg_mutate_blocks_at_path(
		$normalized,
		$path,
		static function ( array $block ) use ( $insert_block, $position ) {
			$inner = isset( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ? $block['inner_blocks'] : array();
			if ( $position < 0 || $position >= count( $inner ) ) {
				$inner[] = $insert_block;
			} else {
				array_splice( $inner, $position, 0, array( $insert_block ) );
			}
			$block['inner_blocks'] = array_values( $inner );
			return $block;
		}
	);

	if ( is_wp_error( $mutated ) ) {
		return $mutated;
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'path'    => $path,
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => $mutated,
	);
}

/**
 * Duplicate a block at path.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_duplicate_block( array $input ) {
	$path   = mcp_abilities_gutenberg_normalize_block_path( $input['path'] ?? null );
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $path ) ) {
		return $path;
	}
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	$target     = mcp_abilities_gutenberg_get_block_by_path( $normalized, $path );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	$parent_path = $path;
	$index       = array_pop( $parent_path );
	$position    = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : ( (int) $index + 1 );
	$duplicate   = mcp_abilities_gutenberg_normalize_block( mcp_abilities_gutenberg_denormalize_block( $target ) );
	$mutated     = mcp_abilities_gutenberg_insert_block_at_path( $normalized, $parent_path, $duplicate, $position );

	if ( is_wp_error( $mutated ) ) {
		return $mutated;
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'path'     => $path,
		'position' => $position,
		'content'  => $content,
		'summary'  => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'   => $mutated,
	);
}

/**
 * Move a block from one path to a parent path and position.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_move_block( array $input ) {
	$from_path   = mcp_abilities_gutenberg_normalize_block_path( $input['from_path'] ?? null );
	$target_path = mcp_abilities_gutenberg_normalize_block_path( $input['to_parent_path'] ?? array() );
	$blocks      = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $from_path ) ) {
		return $from_path;
	}
	if ( is_wp_error( $target_path ) ) {
		return $target_path;
	}
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	$extracted  = mcp_abilities_gutenberg_extract_block_at_path( $normalized, $from_path );
	if ( is_wp_error( $extracted ) ) {
		return $extracted;
	}

	$position = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : PHP_INT_MAX;
	$mutated  = mcp_abilities_gutenberg_insert_block_at_path(
		$extracted['blocks'],
		$target_path,
		$extracted['block'],
		$position
	);

	if ( is_wp_error( $mutated ) ) {
		return $mutated;
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'from_path'      => $from_path,
		'to_parent_path' => $target_path,
		'position'       => $position,
		'content'        => $content,
		'summary'        => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'         => $mutated,
	);
}

/**
 * Replace text within a block tree or subtree.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_replace_block_text( array $input ) {
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$path = null;
	if ( array_key_exists( 'path', $input ) ) {
		$path = mcp_abilities_gutenberg_normalize_block_path( $input['path'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
	}

	$search  = isset( $input['search'] ) ? (string) $input['search'] : '';
	$replace = isset( $input['replace'] ) ? (string) $input['replace'] : '';

	return mcp_abilities_gutenberg_replace_text_in_blocks(
		mcp_abilities_gutenberg_normalize_blocks( $blocks ),
		$search,
		$replace,
		$path
	);
}

/**
 * Return block bindings for a target block.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_get_block_bindings( array $input ) {
	$path   = mcp_abilities_gutenberg_normalize_block_path( $input['path'] ?? null );
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );

	if ( is_wp_error( $path ) ) {
		return $path;
	}
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$target = mcp_abilities_gutenberg_get_block_by_path( mcp_abilities_gutenberg_normalize_blocks( $blocks ), $path );
	if ( is_wp_error( $target ) ) {
		return $target;
	}

	$metadata = isset( $target['attrs']['metadata'] ) && is_array( $target['attrs']['metadata'] ) ? $target['attrs']['metadata'] : array();
	$bindings = isset( $metadata['bindings'] ) && is_array( $metadata['bindings'] ) ? $metadata['bindings'] : array();

	return array(
		'path'     => $path,
		'bindings' => $bindings,
		'metadata' => $metadata,
		'block'    => $target,
	);
}

/**
 * Set block bindings for a target block.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_set_block_bindings( array $input ) {
	$bindings = isset( $input['bindings'] ) && is_array( $input['bindings'] ) ? $input['bindings'] : null;
	if ( null === $bindings ) {
		return new WP_Error( 'mcp_gutenberg_missing_bindings', 'bindings must be an object.' );
	}

	$input['operation'] = 'update-attrs';
	$input['attrs']     = array(
		'metadata' => array(
			'bindings' => $bindings,
		),
	);

	return mcp_abilities_gutenberg_mutate_block_tree( $input );
}

/**
 * Normalize heading levels across a block tree.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_normalize_heading_levels( array $input ) {
	$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );
	if ( is_wp_error( $blocks ) ) {
		return $blocks;
	}

	$start_level = isset( $input['start_level'] ) ? max( 1, min( 6, (int) $input['start_level'] ) ) : 2;
	$normalized  = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	$current     = $start_level - 1;

	$walker = static function ( array $nodes ) use ( &$walker, &$current ) {
		foreach ( $nodes as $index => $node ) {
			$name = isset( $node['block_name'] ) ? (string) $node['block_name'] : '';
			if ( 'core/heading' === $name ) {
				$current++;
				$nodes[ $index ]['attrs'] = is_array( $node['attrs'] ?? null ) ? $node['attrs'] : array();
				$nodes[ $index ]['attrs']['level'] = max( 1, min( 6, $current ) );
			}
			if ( ! empty( $node['inner_blocks'] ) && is_array( $node['inner_blocks'] ) ) {
				$nodes[ $index ]['inner_blocks'] = $walker( $node['inner_blocks'] );
			}
		}
		return $nodes;
	};

	$mutated = $walker( $normalized );
	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return $denormalized;
	}

	$content = serialize_blocks( $denormalized );

	return array(
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => $mutated,
	);
}

/**
 * Return a style-book oriented summary for the active theme.
 *
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_get_style_book_summary(): array {
	$style_guide = mcp_abilities_gutenberg_get_style_guide();
	$catalog     = mcp_abilities_gutenberg_get_block_catalog();
	$styled      = array();

	foreach ( $catalog as $block ) {
		if ( ! empty( $block['styles'] ) || ! empty( $block['supports']['color'] ) || ! empty( $block['supports']['spacing'] ) || ! empty( $block['supports']['typography'] ) ) {
			$styled[] = array(
				'name'        => (string) $block['name'],
				'title'       => (string) $block['title'],
				'styles'      => is_array( $block['styles'] ) ? $block['styles'] : array(),
				'has_color'   => ! empty( $block['supports']['color'] ),
				'has_spacing' => ! empty( $block['supports']['spacing'] ),
				'has_type'    => ! empty( $block['supports']['typography'] ),
			);
		}
	}

	return array(
		'theme'                => mcp_abilities_gutenberg_get_theme_context(),
		'palette_count'        => count( $style_guide['palette'] ?? array() ),
		'gradient_count'       => count( $style_guide['gradients'] ?? array() ),
		'font_size_count'      => count( $style_guide['font_sizes'] ?? array() ),
		'spacing_size_count'   => count( $style_guide['spacing_sizes'] ?? array() ),
		'layout'               => $style_guide['layout'] ?? array(),
		'styled_block_count'   => count( $styled ),
		'styled_blocks'        => $styled,
	);
}

/**
 * Return reusable section recipes.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_section_recipes(): array {
	return array(
		array(
			'slug'        => 'hero',
			'label'       => 'Hero',
			'description' => 'Large introductory section with eyebrow, headline, body copy, and buttons.',
			'blocks'      => array( 'core/group', 'core/paragraph', 'core/heading', 'core/buttons' ),
		),
		array(
			'slug'        => 'feature-list',
			'label'       => 'Feature List',
			'description' => 'Feature or offering cards arranged in columns.',
			'blocks'      => array( 'core/group', 'core/columns', 'core/heading', 'core/paragraph' ),
		),
		array(
			'slug'        => 'faq',
			'label'       => 'FAQ',
			'description' => 'Question and answer stack using headings and paragraphs.',
			'blocks'      => array( 'core/group', 'core/heading', 'core/paragraph' ),
		),
		array(
			'slug'        => 'testimonial',
			'label'       => 'Testimonial',
			'description' => 'Pull quote or customer quote block.',
			'blocks'      => array( 'core/quote', 'core/group' ),
		),
		array(
			'slug'        => 'stats',
			'label'       => 'Stats',
			'description' => 'Short highlight metrics in columns.',
			'blocks'      => array( 'core/columns', 'core/heading', 'core/paragraph' ),
		),
		array(
			'slug'        => 'final-cta',
			'label'       => 'Final CTA',
			'description' => 'Closing conversion section with button and support text.',
			'blocks'      => array( 'core/group', 'core/heading', 'core/paragraph', 'core/buttons' ),
		),
		array(
			'slug'        => 'pricing',
			'label'       => 'Pricing',
			'description' => 'Tiered or package pricing cards with pricing and supporting details.',
			'blocks'      => array( 'core/group', 'core/columns', 'core/heading', 'core/paragraph', 'core/list', 'core/buttons' ),
		),
		array(
			'slug'        => 'team',
			'label'       => 'Team',
			'description' => 'Team profile cards with names, roles, and short bios.',
			'blocks'      => array( 'core/group', 'core/columns', 'core/heading', 'core/paragraph' ),
		),
		array(
			'slug'        => 'timeline',
			'label'       => 'Timeline',
			'description' => 'Sequential milestones or process steps.',
			'blocks'      => array( 'core/group', 'core/heading', 'core/list', 'core/paragraph' ),
		),
		array(
			'slug'        => 'gallery',
			'label'       => 'Gallery',
			'description' => 'Visual showcase section with image placeholders and captions.',
			'blocks'      => array( 'core/group', 'core/gallery', 'core/image', 'core/paragraph', 'core/heading' ),
		),
		array(
			'slug'        => 'contact-map',
			'label'       => 'Contact & Map',
			'description' => 'Visit/contact section with address details and a map placeholder.',
			'blocks'      => array( 'core/columns', 'core/heading', 'core/paragraph', 'core/list', 'core/buttons', 'core/embed' ),
		),
	);
}

/**
 * Return reusable query-section recipes.
 *
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_get_query_section_recipes(): array {
	return array(
		array(
			'slug'        => 'post-grid',
			'label'       => 'Post Grid',
			'description' => 'Responsive card grid with featured image, title, excerpt, and read-more link.',
			'blocks'      => array( 'core/group', 'core/query', 'core/post-template', 'core/post-featured-image', 'core/post-title', 'core/post-excerpt', 'core/read-more' ),
		),
		array(
			'slug'        => 'compact-list',
			'label'       => 'Compact List',
			'description' => 'Tighter editorial list with title, date, excerpt, and read-more.',
			'blocks'      => array( 'core/group', 'core/query', 'core/post-template', 'core/post-title', 'core/post-date', 'core/post-excerpt', 'core/read-more' ),
		),
		array(
			'slug'        => 'magazine',
			'label'       => 'Magazine',
			'description' => 'Editorial two-column cards with image-led story blocks and metadata.',
			'blocks'      => array( 'core/group', 'core/query', 'core/post-template', 'core/post-featured-image', 'core/post-terms', 'core/post-title', 'core/post-date', 'core/post-excerpt' ),
		),
	);
}

/**
 * Build sanitized query-loop attrs.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_build_query_attributes( array $input ): array {
	$per_page = isset( $input['per_page'] ) ? max( 1, min( 12, (int) $input['per_page'] ) ) : 6;
	$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
	$order = isset( $input['order'] ) ? strtoupper( (string) $input['order'] ) : 'DESC';
	$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
	$order_by = isset( $input['order_by'] ) ? sanitize_key( (string) $input['order_by'] ) : 'date';
	$allowed_order_by = array( 'date', 'title', 'modified', 'menu_order', 'rand' );
	if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
		$order_by = 'date';
	}

	$query = array(
		'perPage'  => $per_page,
		'pages'    => 0,
		'offset'   => 0,
		'postType' => $post_type,
		'order'    => strtolower( $order ),
		'orderBy'  => $order_by,
		'inherit'  => false,
	);

	if ( isset( $input['offset'] ) ) {
		$query['offset'] = max( 0, (int) $input['offset'] );
	}
	if ( isset( $input['search'] ) && '' !== trim( (string) $input['search'] ) ) {
		$query['search'] = trim( (string) $input['search'] );
	}
	if ( isset( $input['author'] ) ) {
		$query['author'] = max( 0, (int) $input['author'] );
	}
	if ( isset( $input['category_ids'] ) && is_array( $input['category_ids'] ) ) {
		$query['categoryIds'] = array_values(
			array_filter(
				array_map( 'intval', $input['category_ids'] ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);
	}
	if ( isset( $input['tag_ids'] ) && is_array( $input['tag_ids'] ) ) {
		$query['tagIds'] = array_values(
			array_filter(
				array_map( 'intval', $input['tag_ids'] ),
				static function ( int $id ): bool {
					return $id > 0;
				}
			)
		);
	}

	return array(
		'queryId' => wp_rand( 1, 999 ),
		'query'   => $query,
	);
}

/**
 * Generate a reusable query section payload.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_generate_query_section_payload( array $input ) {
	$recipe = isset( $input['recipe'] ) ? sanitize_key( (string) $input['recipe'] ) : 'post-grid';
	$title  = isset( $input['title'] ) ? trim( (string) $input['title'] ) : 'Latest Stories';
	$intro  = isset( $input['intro'] ) ? trim( (string) $input['intro'] ) : 'A dynamic Gutenberg section that keeps the page fresh as new content is published.';
	$empty  = isset( $input['empty_message'] ) ? trim( (string) $input['empty_message'] ) : 'Nothing has been published here yet.';
	$query_attrs = mcp_abilities_gutenberg_build_query_attributes( $input );
	$content = '';

	switch ( $recipe ) {
		case 'post-grid':
			$query_block_attrs = array_merge(
				$query_attrs,
				array(
					'displayLayout' => array(
						'type'    => 'flex',
						'columns' => isset( $input['columns'] ) ? max( 2, min( 4, (int) $input['columns'] ) ) : 3,
					),
					'layout'        => array( 'type' => 'constrained' ),
				)
			);
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1.25rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $intro )
				. '<!-- wp:query ' . wp_json_encode( $query_block_attrs ) . ' --><div class="wp-block-query">'
				. '<!-- wp:post-template {"layout":{"type":"grid","columnCount":' . (int) $query_block_attrs['displayLayout']['columns'] . '}} -->'
				. '<!-- wp:group {"style":{"spacing":{"blockGap":"0.9rem","padding":{"top":"1.1rem","right":"1.1rem","bottom":"1.1rem","left":"1.1rem"}},"border":{"radius":"18px"}},"backgroundColor":"base-2","layout":{"type":"constrained"}} --><div class="wp-block-group has-base-2-background-color has-background" style="border-radius:18px;padding-top:1.1rem;padding-right:1.1rem;padding-bottom:1.1rem;padding-left:1.1rem">'
				. '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"14px"}}} /-->'
				. '<!-- wp:post-title {"level":3,"isLink":true} /-->'
				. '<!-- wp:post-excerpt {"moreText":"Continue reading"} /-->'
				. '<!-- wp:read-more {"content":"Read story"} /-->'
				. '</div><!-- /wp:group -->'
				. '<!-- /wp:post-template -->'
				. '<!-- wp:query-no-results --><div class="wp-block-query-no-results">'
				. mcp_abilities_gutenberg_paragraph_block( $empty )
				. '</div><!-- /wp:query-no-results -->'
				. '</div><!-- /wp:query -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'compact-list':
			$query_block_attrs = array_merge(
				$query_attrs,
				array(
					'displayLayout' => array( 'type' => 'list' ),
					'layout'        => array( 'type' => 'constrained' ),
				)
			);
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $intro )
				. '<!-- wp:query ' . wp_json_encode( $query_block_attrs ) . ' --><div class="wp-block-query">'
				. '<!-- wp:post-template {"layout":{"type":"default"}} -->'
				. '<!-- wp:group {"style":{"spacing":{"blockGap":"0.6rem","padding":{"top":"0.9rem","bottom":"0.9rem"}},"border":{"bottom":{"color":"var:preset|color|contrast-3","width":"1px"}}},"layout":{"type":"constrained"}} --><div class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--contrast-3);border-bottom-width:1px;padding-top:0.9rem;padding-bottom:0.9rem">'
				. '<!-- wp:post-title {"level":3,"isLink":true} /-->'
				. '<!-- wp:post-date /-->'
				. '<!-- wp:post-excerpt {"moreText":"Continue reading"} /-->'
				. '<!-- wp:read-more {"content":"Open"} /-->'
				. '</div><!-- /wp:group -->'
				. '<!-- /wp:post-template -->'
				. '<!-- wp:query-no-results --><div class="wp-block-query-no-results">'
				. mcp_abilities_gutenberg_paragraph_block( $empty )
				. '</div><!-- /wp:query-no-results -->'
				. '</div><!-- /wp:query -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'magazine':
			$query_block_attrs = array_merge(
				$query_attrs,
				array(
					'displayLayout' => array(
						'type'    => 'flex',
						'columns' => 2,
					),
					'layout'        => array( 'type' => 'constrained' ),
				)
			);
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1.4rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $intro )
				. '<!-- wp:query ' . wp_json_encode( $query_block_attrs ) . ' --><div class="wp-block-query">'
				. '<!-- wp:post-template {"layout":{"type":"grid","columnCount":2}} -->'
				. '<!-- wp:columns {"verticalAlignment":"top","style":{"spacing":{"blockGap":"1.2rem","padding":{"top":"1rem","bottom":"1rem"}},"border":{"bottom":{"color":"var:preset|color|contrast-3","width":"1px"}}}} --><div class="wp-block-columns are-vertically-aligned-top" style="border-bottom-color:var(--wp--preset--color--contrast-3);border-bottom-width:1px;padding-top:1rem;padding-bottom:1rem">'
				. '<!-- wp:column {"verticalAlignment":"top","width":"40%"} --><div class="wp-block-column is-vertically-aligned-top" style="flex-basis:40%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","style":{"border":{"radius":"16px"}}} /--></div><!-- /wp:column -->'
				. '<!-- wp:column {"verticalAlignment":"top","width":"60%"} --><div class="wp-block-column is-vertically-aligned-top" style="flex-basis:60%">'
				. '<!-- wp:post-terms {"term":"category"} /-->'
				. '<!-- wp:post-title {"level":3,"isLink":true} /-->'
				. '<!-- wp:post-date /-->'
				. '<!-- wp:post-excerpt {"moreText":"Continue reading"} /-->'
				. '</div><!-- /wp:column -->'
				. '</div><!-- /wp:columns -->'
				. '<!-- /wp:post-template -->'
				. '<!-- wp:query-no-results --><div class="wp-block-query-no-results">'
				. mcp_abilities_gutenberg_paragraph_block( $empty )
				. '</div><!-- /wp:query-no-results -->'
				. '</div><!-- /wp:query -->'
				. '</div><!-- /wp:group -->';
			break;

		default:
			return new WP_Error( 'mcp_gutenberg_unknown_query_recipe', 'Unknown query section recipe.' );
	}

	return array(
		'recipe'  => $recipe,
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
		'query'   => $query_attrs['query'],
	);
}

/**
 * Generate a reusable section payload.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_generate_section_payload( array $input ) {
	$section  = isset( $input['section'] ) ? sanitize_key( (string) $input['section'] ) : 'hero';
	$title    = isset( $input['title'] ) ? trim( (string) $input['title'] ) : 'Section Title';
	$body     = isset( $input['body'] ) ? trim( (string) $input['body'] ) : 'Supporting copy goes here.';
	$items    = isset( $input['items'] ) && is_array( $input['items'] ) ? array_values( array_filter( array_map( 'strval', $input['items'] ) ) ) : array();
	$eyebrow  = isset( $input['eyebrow'] ) ? trim( (string) $input['eyebrow'] ) : 'Section';
	$cta      = isset( $input['cta_text'] ) ? trim( (string) $input['cta_text'] ) : 'Learn More';
	$content  = '';

	switch ( $section ) {
		case 'hero':
			$content = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem","left":"min(6vw,4rem)","right":"min(6vw,4rem)"},"blockGap":"1rem"},"color":{"gradient":"linear-gradient(135deg,rgb(24,18,16) 0%,rgb(70,46,33) 42%,rgb(198,150,93) 100%)"}},"textColor":"base","layout":{"type":"constrained"}} --><div class="wp-block-group has-base-color has-text-color has-background" style="background:linear-gradient(135deg,rgb(24,18,16) 0%,rgb(70,46,33) 42%,rgb(198,150,93) 100%);padding-top:4rem;padding-right:min(6vw,4rem);padding-bottom:4rem;padding-left:min(6vw,4rem)">'
				. mcp_abilities_gutenberg_paragraph_block( $eyebrow, array( 'fontSize' => 'small' ) )
				. mcp_abilities_gutenberg_heading_block( $title, 1 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'feature-list':
			$columns = '';
			foreach ( array_slice( $items, 0, 4 ) as $item ) {
				$columns .= '<!-- wp:column --><div class="wp-block-column">' . mcp_abilities_gutenberg_heading_block( $item, 3 ) . mcp_abilities_gutenberg_paragraph_block( $body ) . '</div><!-- /wp:column -->';
			}
			$content = '<!-- wp:group {"style":{"spacing":{"blockGap":"1rem"}},"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. '<!-- wp:columns --><div class="wp-block-columns">' . $columns . '</div><!-- /wp:columns -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'faq':
			$faq_items = '';
			foreach ( array_slice( $items, 0, 6 ) as $item ) {
				$faq_items .= mcp_abilities_gutenberg_heading_block( $item, 3 ) . mcp_abilities_gutenberg_paragraph_block( $body );
			}
			$content = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. $faq_items
				. '</div><!-- /wp:group -->';
			break;

		case 'testimonial':
			$content = '<!-- wp:quote {"className":"is-style-large"} --><blockquote class="wp-block-quote is-style-large"><p>' . esc_html( $body ) . '</p><cite>' . esc_html( $title ) . '</cite></blockquote><!-- /wp:quote -->';
			break;

		case 'stats':
			$columns = '';
			foreach ( array_slice( $items, 0, 4 ) as $item ) {
				$columns .= '<!-- wp:column --><div class="wp-block-column">' . mcp_abilities_gutenberg_heading_block( $item, 3 ) . mcp_abilities_gutenberg_paragraph_block( $body ) . '</div><!-- /wp:column -->';
			}
			$content = '<!-- wp:columns --><div class="wp-block-columns">' . $columns . '</div><!-- /wp:columns -->';
			break;

		case 'final-cta':
			$content = '<!-- wp:group {"backgroundColor":"base-2","style":{"spacing":{"padding":{"top":"3rem","bottom":"3rem","left":"min(6vw,4rem)","right":"min(6vw,4rem)"},"blockGap":"1rem"}},"layout":{"type":"constrained"}} --><div class="wp-block-group has-base-2-background-color has-background" style="padding-top:3rem;padding-right:min(6vw,4rem);padding-bottom:3rem;padding-left:min(6vw,4rem)">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'pricing':
			$columns = '';
			foreach ( array_slice( $items, 0, 3 ) as $index => $item ) {
				$price = '$' . (string) ( 12 + ( $index * 8 ) );
				$columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"1.5rem","right":"1.5rem","bottom":"1.5rem","left":"1.5rem"},"blockGap":"0.75rem"},"border":{"radius":"18px","width":"1px"}},"layout":{"type":"constrained"}} --><div class="wp-block-group" style="border-width:1px;border-radius:18px;padding-top:1.5rem;padding-right:1.5rem;padding-bottom:1.5rem;padding-left:1.5rem">'
					. mcp_abilities_gutenberg_heading_block( $item, 3 )
					. mcp_abilities_gutenberg_paragraph_block( $price, array( 'fontSize' => 'large' ) )
					. '<!-- wp:list --><ul><li>' . esc_html( $body ) . '</li><li>Flexible package details</li><li>Clear next step</li></ul><!-- /wp:list -->'
					. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
					. '</div><!-- /wp:group --></div><!-- /wp:column -->';
			}
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. '<!-- wp:columns --><div class="wp-block-columns">' . $columns . '</div><!-- /wp:columns -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'team':
			$columns = '';
			foreach ( array_slice( $items, 0, 4 ) as $member ) {
				$columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"padding":{"top":"1.25rem","right":"1.25rem","bottom":"1.25rem","left":"1.25rem"},"blockGap":"0.5rem"},"border":{"radius":"16px"}},"backgroundColor":"base-2","layout":{"type":"constrained"}} --><div class="wp-block-group has-base-2-background-color has-background" style="border-radius:16px;padding-top:1.25rem;padding-right:1.25rem;padding-bottom:1.25rem;padding-left:1.25rem">'
					. mcp_abilities_gutenberg_heading_block( $member, 3 )
					. mcp_abilities_gutenberg_paragraph_block( 'Role or specialty' )
					. mcp_abilities_gutenberg_paragraph_block( $body )
					. '</div><!-- /wp:group --></div><!-- /wp:column -->';
			}
			$content = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. '<!-- wp:columns --><div class="wp-block-columns">' . $columns . '</div><!-- /wp:columns -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'timeline':
			$list_items = '';
			foreach ( array_slice( $items, 0, 6 ) as $item ) {
				$list_items .= '<li><strong>' . esc_html( $item ) . '</strong>: ' . esc_html( $body ) . '</li>';
			}
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. '<!-- wp:list --><ul>' . $list_items . '</ul><!-- /wp:list -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'gallery':
			$images = '';
			foreach ( array_slice( $items, 0, 3 ) as $item ) {
				$images .= '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img alt="' . esc_attr( $item ) . '"/><figcaption>' . esc_html( $item ) . '</figcaption></figure><!-- /wp:image -->';
			}
			$content = '<!-- wp:group {"layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"1rem"}}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped">' . $images . '</figure><!-- /wp:gallery -->'
				. '</div><!-- /wp:group -->';
			break;

		case 'contact-map':
			$content = '<!-- wp:columns {"verticalAlignment":"top"} --><div class="wp-block-columns are-vertically-aligned-top"><!-- wp:column {"verticalAlignment":"top"} --><div class="wp-block-column is-vertically-aligned-top">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. '<!-- wp:list --><ul><li>Address line placeholder</li><li>Opening hours placeholder</li><li>Phone or email placeholder</li></ul><!-- /wp:list -->'
				. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"style":{"border":{"radius":"999px"}}} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="border-radius:999px">' . esc_html( $cta ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
				. '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"top"} --><div class="wp-block-column is-vertically-aligned-top"><!-- wp:embed {"providerNameSlug":"wordpress","responsive":true,"className":"is-provider-wordpress wp-block-embed is-provider-wordpress is-type-rich is-responsive"} --><figure class="wp-block-embed is-provider-wordpress wp-block-embed is-type-rich is-responsive"><div class="wp-block-embed__wrapper">https://maps.example.com/location-placeholder</div></figure><!-- /wp:embed --></div><!-- /wp:column --></div><!-- /wp:columns -->';
			break;

		default:
			return new WP_Error( 'mcp_gutenberg_unknown_section', 'Unsupported section recipe.' );
	}

	return array(
		'section' => $section,
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
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

	$all_block_names = mcp_abilities_gutenberg_collect_block_names( $normalized );
	$layout_risks    = mcp_abilities_gutenberg_collect_content_layout_risks( $content );
	$markup_bearing_block_names = array();
	$comment_only_block_names   = array();
	$walk_blocks                = static function ( array $blocks ) use ( &$walk_blocks, &$markup_bearing_block_names, &$comment_only_block_names ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$block_name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
			if ( '' !== $block_name ) {
				if ( mcp_abilities_gutenberg_block_persists_markup( $block ) ) {
					$markup_bearing_block_names[] = $block_name;
				} else {
					$comment_only_block_names[] = $block_name;
				}
			}

			$inner_blocks = isset( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ? $block['inner_blocks'] : array();
			if ( ! empty( $inner_blocks ) ) {
				$walk_blocks( $inner_blocks );
			}
		}
	};
	$walk_blocks( $normalized );
	$markup_bearing_block_names = array_values( array_unique( $markup_bearing_block_names ) );
	$comment_only_block_names   = array_values( array_unique( $comment_only_block_names ) );

	$warnings = array();
	if ( empty( $normalized ) ) {
		$warnings[] = 'Content contains no Gutenberg blocks.';
	}
	if ( ! in_array( 'core/heading', $all_block_names, true ) ) {
		$warnings[] = 'No heading block found anywhere in the block tree.';
	}
	if ( ! in_array( 'core/buttons', $all_block_names, true ) ) {
		$warnings[] = 'No buttons block found anywhere in the block tree.';
	}
	if ( count( $normalized ) < 3 ) {
		$warnings[] = 'Very few top-level blocks; page structure may be too shallow for a landing page.';
	}
	if ( ! empty( $markup_bearing_block_names ) ) {
		$warnings[] = 'Markup-bearing blocks are present; attr-only mutations may require saved markup regeneration to affect frontend rendering.';
	}
	if ( ! empty( $layout_risks['issues'] ) ) {
		$issue_types = array_values(
			array_unique(
				array_map(
					static function ( array $issue ): string {
						return (string) ( $issue['type'] ?? '' );
					},
					$layout_risks['issues']
				)
			)
		);
		$warnings[] = 'Layout-risk styles detected in content: ' . implode( ', ', $issue_types ) . '.';
	}
	if ( ! empty( $layout_risks['content_measures'] ) ) {
		$measure_values = array_values(
			array_unique(
				array_map(
					static function ( array $measure ): string {
						return (string) ( $measure['value'] ?? '' );
					},
					$layout_risks['content_measures']
				)
			)
		);
		if ( count( $measure_values ) > 1 ) {
			$warnings[] = 'Multiple fixed content measures detected in embedded Gutenberg styling: ' . implode( ', ', array_slice( $measure_values, 0, 6 ) ) . '. Interior sections usually need one primary width rhythm.';
		}
	}

	return array(
		'summary'                 => mcp_abilities_gutenberg_content_summary( $content ),
		'roundtrip_equal'         => $normalized === $roundtrip_normalized,
		'all_block_names'         => $all_block_names,
		'static_block_names'      => $markup_bearing_block_names,
		'dynamic_block_names'     => $comment_only_block_names,
		'top_level_block_names'   => $top_level_names,
		'top_level_block_count'   => count( $normalized ),
		'warnings'                => $warnings,
		'layout_risks'            => array(
			'issue_count'               => count( $layout_risks['issues'] ),
			'embedded_style_block_count'=> (int) $layout_risks['embedded_style_block_count'],
			'inline_style_count'        => (int) $layout_risks['inline_style_count'],
			'shell_full_width_css_detected' => ! empty( $layout_risks['shell_full_width_css_detected'] ),
			'content_measures'          => $layout_risks['content_measures'],
			'issues'                    => $layout_risks['issues'],
		),
		'mutation_guardrails'     => array(
			'static_attr_changes_require_markup_regeneration' => ! empty( $markup_bearing_block_names ),
			'editor_only_attr_paths'                          => array( 'lock', 'templateLock', 'allowedBlocks', 'metadata' ),
		),
	);
}

/**
 * Collect block names from a normalized block tree.
 *
 * @param array<int,array<string,mixed>> $blocks Normalized blocks.
 * @return array<int,string>
 */
function mcp_abilities_gutenberg_collect_block_names( array $blocks ): array {
	$names = array();

	foreach ( $blocks as $block ) {
		$name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
		if ( '' !== $name ) {
			$names[] = $name;
		}

		if ( ! empty( $block['inner_blocks'] ) && is_array( $block['inner_blocks'] ) ) {
			$names = array_merge( $names, mcp_abilities_gutenberg_collect_block_names( $block['inner_blocks'] ) );
		}
	}

	return array_values( array_unique( $names ) );
}

/**
 * Find a page with an exact slug match.
 *
 * @param string $slug Page slug.
 * @return WP_Post|null
 */
function mcp_abilities_gutenberg_find_page_by_slug( string $slug ) {
	$posts = get_posts(
		array(
			'post_type'              => 'page',
			'name'                   => $slug,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => 1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return ! empty( $posts ) && $posts[0] instanceof WP_Post ? $posts[0] : null;
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

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}

	$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : 'Untitled Page';
	$slug  = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $title );
	$status = isset( $input['status'] ) ? sanitize_text_field( (string) $input['status'] ) : 'draft';
	$upsert_matching_slug = ! empty( $input['upsert_matching_slug'] );

	if ( '' === $slug ) {
		$slug = sanitize_title( $title );
	}

	$existing_post = '' !== $slug ? mcp_abilities_gutenberg_find_page_by_slug( $slug ) : null;
	if ( $upsert_matching_slug && $existing_post instanceof WP_Post ) {
		$update_result = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $existing_post->ID,
					'post_title'   => $title,
					'post_status'  => $status,
					'post_content' => $content,
				)
			),
			true
		);

		if ( is_wp_error( $update_result ) ) {
			return array(
				'success' => false,
				'message' => $update_result->get_error_message(),
			);
		}

		$post = get_post( (int) $existing_post->ID );

		return array(
			'success' => true,
			'message' => 'Existing page updated successfully.',
			'post'    => array(
				'id'       => (int) $existing_post->ID,
				'type'     => $post ? (string) $post->post_type : 'page',
				'status'   => $post ? (string) $post->post_status : $status,
				'slug'     => $post ? (string) $post->post_name : $slug,
				'title'    => $post ? get_the_title( $post ) : $title,
				'url'      => get_permalink( (int) $existing_post->ID ),
				'modified' => $post ? (string) $post->post_modified_gmt : '',
			),
			'content' => $content,
			'summary' => mcp_abilities_gutenberg_content_summary( $content ),
			'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
		);
	}

	$parent_id   = 0;
	$unique_slug = wp_unique_post_slug( $slug, 0, $status, 'page', $parent_id );

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $unique_slug,
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
			'slug'     => $post ? (string) $post->post_name : $unique_slug,
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
 * Create a page from a registered pattern.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_create_page_from_pattern( array $input ): array {
	$pattern_name = isset( $input['pattern_name'] ) ? (string) $input['pattern_name'] : '';
	$pattern      = mcp_abilities_gutenberg_get_pattern_details( $pattern_name );

	if ( is_wp_error( $pattern ) ) {
		return array(
			'success' => false,
			'message' => $pattern->get_error_message(),
		);
	}

	$create = mcp_abilities_gutenberg_create_page_from_input(
		array(
			'title'                => isset( $input['title'] ) ? (string) $input['title'] : (string) $pattern['title'],
			'slug'                 => isset( $input['slug'] ) ? (string) $input['slug'] : sanitize_title( (string) $pattern['title'] ),
			'status'               => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
			'upsert_matching_slug' => ! empty( $input['upsert_matching_slug'] ),
			'content'              => (string) $pattern['content'],
		)
	);

	if ( ! empty( $create['success'] ) ) {
		$create['pattern'] = $pattern;
	}

	return $create;
}

/**
 * Insert a registered pattern into an existing post.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_insert_pattern_into_post( array $input ): array {
	$post_id      = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$pattern_name = isset( $input['pattern_name'] ) ? (string) $input['pattern_name'] : '';
	$position     = isset( $input['position'] ) ? (string) $input['position'] : 'append';

	$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
	if ( is_wp_error( $post ) ) {
		return array(
			'success' => false,
			'message' => $post->get_error_message(),
		);
	}

	$pattern = mcp_abilities_gutenberg_get_pattern_details( $pattern_name );
	if ( is_wp_error( $pattern ) ) {
		return array(
			'success' => false,
			'message' => $pattern->get_error_message(),
		);
	}

	$existing_content = (string) $post->post_content;
	$pattern_content  = (string) $pattern['content'];
	$content          = $existing_content;

	if ( 'prepend' === $position ) {
		$content = $pattern_content . "\n\n" . $existing_content;
	} elseif ( 'replace' === $position ) {
		$content = $pattern_content;
	} else {
		$content = $existing_content . "\n\n" . $pattern_content;
	}

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return array(
			'success' => false,
			'message' => $layout_guard->get_error_message(),
		);
	}

	$result = wp_update_post(
		wp_slash(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $content,
			)
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return array(
			'success' => false,
			'message' => $result->get_error_message(),
		);
	}

	$updated_post = get_post( (int) $post->ID );

	return array(
		'success' => true,
		'message' => 'Pattern inserted successfully.',
		'post'    => array(
			'id'       => (int) $post->ID,
			'type'     => $updated_post ? (string) $updated_post->post_type : (string) $post->post_type,
			'status'   => $updated_post ? (string) $updated_post->post_status : (string) $post->post_status,
			'slug'     => $updated_post ? (string) $updated_post->post_name : (string) $post->post_name,
			'title'    => $updated_post ? get_the_title( $updated_post ) : get_the_title( $post ),
			'url'      => $updated_post ? get_permalink( $updated_post ) : get_permalink( $post ),
			'modified' => $updated_post ? (string) $updated_post->post_modified_gmt : (string) $post->post_modified_gmt,
		),
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) ),
	);
}

/**
 * Set a post featured image.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_set_post_featured_media( array $input ): array {
	$post_id       = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$attachment_id = isset( $input['attachment_id'] ) ? (int) $input['attachment_id'] : 0;

	$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
	if ( is_wp_error( $post ) ) {
		return array(
			'success' => false,
			'message' => $post->get_error_message(),
		);
	}

	if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
		return array(
			'success' => false,
			'message' => 'attachment_id must reference an attachment.',
		);
	}

	$result = set_post_thumbnail( $post_id, $attachment_id );
	if ( ! $result ) {
		return array(
			'success' => false,
			'message' => 'Failed to set featured media.',
		);
	}

	return array(
		'success'       => true,
		'message'       => 'Featured media updated successfully.',
		'post_id'       => $post_id,
		'attachment_id' => $attachment_id,
		'featured_url'  => (string) wp_get_attachment_image_url( $attachment_id, 'full' ),
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
				'default'              => array(),
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
				'default'              => array(),
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
		'gutenberg/get-style-book',
		array(
			'label'               => 'Get Style Book Summary',
			'description'         => 'Return a style-book oriented summary of theme presets and style-capable blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'style_book' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success'    => true,
					'style_book' => mcp_abilities_gutenberg_get_style_book_summary(),
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
		'gutenberg/get-site-editor-summary',
		array(
			'label'               => 'Get Site Editor Summary',
			'description'         => 'Return a site-editor oriented summary of the active block theme, style-book data, templates, parts, navigation entities, and synced patterns.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'summary' => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'summary' => mcp_abilities_gutenberg_get_site_editor_summary(),
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
		'gutenberg/get-site-editor-references',
		array(
			'label'               => 'Get Site Editor References',
			'description'         => 'Return a reference graph for templates and template parts, including navigation and template-part block references.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'graph'   => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'graph'   => mcp_abilities_gutenberg_get_site_editor_reference_graph(),
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
				'default'              => array(),
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
				'default'              => array(),
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
		'gutenberg/get-block-categories',
		array(
			'label'               => 'Get Block Categories',
			'description'         => 'Return compact block category groupings derived from the registered block catalog.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'count'      => array( 'type' => 'integer' ),
					'categories' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				$categories = mcp_abilities_gutenberg_get_block_categories();
				return array(
					'success'    => true,
					'count'      => count( $categories ),
					'categories' => $categories,
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
		'gutenberg/get-block-details',
		array(
			'label'               => 'Get Block Details',
			'description'         => 'Return full metadata for a single registered block type.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'name' ),
				'properties'           => array(
					'name' => array(
						'type'        => 'string',
						'description' => 'Registered block name such as core/group.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'block'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$details = mcp_abilities_gutenberg_get_block_details( isset( $input['name'] ) ? (string) $input['name'] : '' );
				if ( is_wp_error( $details ) ) {
					return array(
						'success' => false,
						'message' => $details->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'block'   => $details,
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
		'gutenberg/get-block-style-variations',
		array(
			'label'               => 'Get Block Style Variations',
			'description'         => 'Return style variations and style-relevant theme support data for a block type.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'name' ),
				'properties'           => array(
					'name' => array(
						'type'        => 'string',
						'description' => 'Registered block name such as core/group.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'styles'  => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$styles = mcp_abilities_gutenberg_get_block_style_variations( isset( $input['name'] ) ? (string) $input['name'] : '' );
				if ( is_wp_error( $styles ) ) {
					return array(
						'success' => false,
						'message' => $styles->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'styles'  => $styles,
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
		'gutenberg/get-pattern',
		array(
			'label'               => 'Get Block Pattern',
			'description'         => 'Return a registered block pattern including raw content, summary, and normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'name' ),
				'properties'           => array(
					'name' => array(
						'type'        => 'string',
						'description' => 'Registered pattern name.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'pattern' => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$pattern = mcp_abilities_gutenberg_get_pattern_details( isset( $input['name'] ) ? (string) $input['name'] : '' );
				if ( is_wp_error( $pattern ) ) {
					return array(
						'success' => false,
						'message' => $pattern->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'pattern' => $pattern,
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
		'gutenberg/list-synced-patterns',
		array(
			'label'               => 'List Synced Patterns',
			'description'         => 'Return reusable synced patterns stored as `wp_block` entities.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'patterns' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success'  => true,
					'patterns' => mcp_abilities_gutenberg_get_synced_patterns(),
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
		'gutenberg/get-synced-pattern',
		array(
			'label'               => 'Get Synced Pattern',
			'description'         => 'Return a reusable synced pattern (`wp_block`) with raw content and normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Synced pattern post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Synced pattern slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'pattern' => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$pattern = mcp_abilities_gutenberg_get_synced_pattern( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $pattern ) ) {
					return array(
						'success' => false,
						'message' => $pattern->get_error_message(),
					);
				}
				return array(
					'success' => true,
					'pattern' => $pattern,
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
				'default'              => array(),
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
				'default'              => array(),
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
		'gutenberg/get-section-recipes',
		array(
			'label'               => 'Get Section Recipes',
			'description'         => 'Return reusable Gutenberg section recipes for common landing-page and marketing layouts.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'recipes'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'recipes' => mcp_abilities_gutenberg_get_section_recipes(),
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
		'gutenberg/get-query-section-recipes',
		array(
			'label'               => 'Get Gutenberg Query Section Recipes',
			'description'         => 'Return reusable dynamic query-section recipes such as post-grid, compact-list, and magazine.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => new stdClass(),
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
					'recipes' => mcp_abilities_gutenberg_get_query_section_recipes(),
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
					'status' => array(
						'type'        => 'string',
						'description' => 'Optional page status hint; accepted for parity with create-landing-page.',
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
		'gutenberg/generate-section',
		array(
			'label'               => 'Generate Section Blocks',
			'description'         => 'Generate a reusable Gutenberg section such as a hero, feature list, FAQ, testimonial, stats row, pricing table, team grid, timeline, gallery, contact-map, or final CTA.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'section' ),
				'properties'           => array(
					'section' => array(
						'type'        => 'string',
						'description' => 'Section recipe slug.',
						'enum'        => array( 'hero', 'feature-list', 'faq', 'testimonial', 'stats', 'pricing', 'team', 'timeline', 'gallery', 'contact-map', 'final-cta' ),
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Section headline or label.',
					),
					'body' => array(
						'type'        => 'string',
						'description' => 'Main supporting copy.',
					),
					'eyebrow' => array(
						'type'        => 'string',
						'description' => 'Optional eyebrow text for hero sections.',
					),
					'cta_text' => array(
						'type'        => 'string',
						'description' => 'Button label where relevant.',
					),
					'items' => array(
						'type'        => 'array',
						'description' => 'Feature, FAQ, or stat labels used by the chosen recipe.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'section' => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_generate_section_payload( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}

				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/generate-query-section',
		array(
			'label'               => 'Generate Gutenberg Query Section',
			'description'         => 'Generate a dynamic Gutenberg query section such as a post-grid, compact-list, or magazine feed.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'recipe' ),
				'properties' => array(
					'recipe' => array(
						'type'        => 'string',
						'enum'        => array( 'post-grid', 'compact-list', 'magazine' ),
						'description' => 'Dynamic query-section recipe to generate.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Section heading.',
					),
					'intro' => array(
						'type'        => 'string',
						'description' => 'Optional intro text above the query block.',
					),
					'empty_message' => array(
						'type'        => 'string',
						'description' => 'Message to show when the query has no results.',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Target post type, for example post or page.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => 'Number of posts to show.',
					),
					'columns' => array(
						'type'        => 'integer',
						'description' => 'Grid columns for the post-grid recipe.',
					),
					'order' => array(
						'type'        => 'string',
						'description' => 'ASC or DESC.',
					),
					'order_by' => array(
						'type'        => 'string',
						'description' => 'date, title, modified, menu_order, or rand.',
					),
					'offset' => array(
						'type'        => 'integer',
						'description' => 'Optional query offset.',
					),
					'search' => array(
						'type'        => 'string',
						'description' => 'Optional search term for the query.',
					),
					'author' => array(
						'type'        => 'integer',
						'description' => 'Optional author ID filter.',
					),
					'category_ids' => array(
						'type'        => 'array',
						'description' => 'Optional category term IDs to filter by.',
						'items'       => array( 'type' => 'integer' ),
					),
					'tag_ids' => array(
						'type'        => 'array',
						'description' => 'Optional tag term IDs to filter by.',
						'items'       => array( 'type' => 'integer' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'recipe'  => array( 'type' => 'string' ),
					'query'   => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_generate_query_section_payload( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}

				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/audit-content',
		array(
			'label'               => 'Audit Gutenberg Content',
			'description'         => 'Return Gutenberg-specific editorial and structural issues such as missing H1, heading jumps, unlinked buttons, and missing image alt text.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to audit.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to audit when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'audit'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
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
					'success' => true,
					'audit'   => mcp_abilities_gutenberg_audit_content( $content ),
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
		'gutenberg/evaluate-design',
		array(
			'label'               => 'Evaluate Gutenberg Design',
			'description'         => 'Evaluate Gutenberg design coherence and flag width-rhythm drift, sibling-treatment mismatches, and risky full-width breakout combinations.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to evaluate.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to evaluate when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'evaluation' => array( 'type' => 'object' ),
					'message'    => array( 'type' => 'string' ),
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
					'evaluation' => mcp_abilities_gutenberg_evaluate_design( $content ),
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
		'gutenberg/suggest-design-fixes',
		array(
			'label'               => 'Suggest Gutenberg Design Fixes',
			'description'         => 'Return concrete design-fix suggestions for width-rhythm drift, sibling-treatment mismatches, weak button contrast, trailing gaps, and risky full-width breakouts.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to inspect.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to inspect when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'suggestions' => array( 'type' => 'object' ),
					'message'     => array( 'type' => 'string' ),
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
					'success'     => true,
					'suggestions' => mcp_abilities_gutenberg_suggest_design_fixes( $content ),
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
		'gutenberg/evaluate-copy',
		array(
			'label'               => 'Evaluate Gutenberg Copy',
			'description'         => 'Evaluate Gutenberg copy quality and flag weak headings, vague CTAs, dense paragraphs, and other writing issues.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to evaluate.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to evaluate when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'evaluation' => array( 'type' => 'object' ),
					'message'    => array( 'type' => 'string' ),
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
					'evaluation' => mcp_abilities_gutenberg_evaluate_copy( $content ),
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
		'gutenberg/suggest-copy-fixes',
		array(
			'label'               => 'Suggest Gutenberg Copy Fixes',
			'description'         => 'Return targeted rewrite suggestions for generic headings, vague CTAs, dense paragraphs, and shouty copy.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to inspect.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to inspect when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'suggestions'=> array( 'type' => 'object' ),
					'message'    => array( 'type' => 'string' ),
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
					'success'     => true,
					'suggestions' => mcp_abilities_gutenberg_suggest_copy_fixes( $content ),
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
		'gutenberg/analyze-content',
		array(
			'label'               => 'Analyze Gutenberg Content',
			'description'         => 'Return structural analysis for Gutenberg content including validation, outline, block usage, links, and media references.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'Raw Gutenberg content to analyze.',
					),
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional post ID to analyze when content is omitted.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'analysis' => array( 'type' => 'object' ),
					'message'  => array( 'type' => 'string' ),
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
					'success'  => true,
					'analysis' => mcp_abilities_gutenberg_analyze_content( $content ),
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
		'gutenberg/evaluate-render-context',
		array(
			'label'               => 'Evaluate Gutenberg Render Context',
			'description'         => 'Inspect the rendered page around Gutenberg post content to catch wrapper and layout-context issues such as empty pre-content wrappers or leading style blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Post or page ID to fetch and inspect in rendered form.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'context' => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
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

				$result = mcp_abilities_gutenberg_evaluate_render_context( $post_id );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'context' => $result,
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
		'gutenberg/list-templates',
		array(
			'label'               => 'List Block Templates',
			'description'         => 'Return `wp_template` entities available to the active block theme.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'templates' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success'   => true,
					'templates' => mcp_abilities_gutenberg_get_template_entities( 'wp_template' ),
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
		'gutenberg/get-template',
		array(
			'label'               => 'Get Block Template',
			'description'         => 'Return a `wp_template` entity with raw content and normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Template post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Template slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'template' => array( 'type' => 'object' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$template = mcp_abilities_gutenberg_get_template_entity( 'wp_template', is_array( $input ) ? $input : array() );
				if ( is_wp_error( $template ) ) {
					return array(
						'success' => false,
						'message' => $template->get_error_message(),
					);
				}

				return array(
					'success'  => true,
					'template' => $template,
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
		'gutenberg/list-template-parts',
		array(
			'label'               => 'List Template Parts',
			'description'         => 'Return `wp_template_part` entities available to the active block theme.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'parts'   => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success' => true,
					'parts'   => mcp_abilities_gutenberg_get_template_entities( 'wp_template_part' ),
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
		'gutenberg/create-template',
		array(
			'label'               => 'Create Block Template',
			'description'         => 'Create a `wp_template` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title' => array( 'type' => 'string' ),
					'slug'  => array( 'type' => 'string' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'theme' => array(
						'type'        => 'string',
						'description' => 'Theme stylesheet slug; defaults to the active theme.',
					),
					'content' => array( 'type' => 'string' ),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'entity'  => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_template_entity( 'wp_template', is_array( $input ) ? $input : array() );
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
		'gutenberg/update-template',
		array(
			'label'               => 'Update Block Template',
			'description'         => 'Update a `wp_template` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'slug'    => array( 'type' => 'string' ),
					'status'  => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'theme'   => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'entity'  => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_template_entity( 'wp_template', is_array( $input ) ? $input : array() );
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
		'gutenberg/get-template-part',
		array(
			'label'               => 'Get Template Part',
			'description'         => 'Return a `wp_template_part` entity with raw content and normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Template part post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Template part slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'part'    => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$part = mcp_abilities_gutenberg_get_template_entity( 'wp_template_part', is_array( $input ) ? $input : array() );
				if ( is_wp_error( $part ) ) {
					return array(
						'success' => false,
						'message' => $part->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'part'    => $part,
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
		'gutenberg/create-template-part',
		array(
			'label'               => 'Create Template Part',
			'description'         => 'Create a `wp_template_part` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title' => array( 'type' => 'string' ),
					'slug'  => array( 'type' => 'string' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'theme' => array( 'type' => 'string' ),
					'area'  => array(
						'type'        => 'string',
						'description' => 'Template part area such as header, footer, or uncategorized.',
					),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'entity'  => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_template_entity( 'wp_template_part', is_array( $input ) ? $input : array() );
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
		'gutenberg/update-template-part',
		array(
			'label'               => 'Update Template Part',
			'description'         => 'Update a `wp_template_part` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'slug'    => array( 'type' => 'string' ),
					'status'  => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'theme'   => array( 'type' => 'string' ),
					'area'    => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'entity'  => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_template_entity( 'wp_template_part', is_array( $input ) ? $input : array() );
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
		'gutenberg/list-navigations',
		array(
			'label'               => 'List Navigation Entities',
			'description'         => 'Return `wp_navigation` entities available to the site editor.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'default'              => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'navigations' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function (): array {
				return array(
					'success'     => true,
					'navigations' => mcp_abilities_gutenberg_get_navigation_entities(),
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
		'gutenberg/get-navigation',
		array(
			'label'               => 'Get Navigation Entity',
			'description'         => 'Return a `wp_navigation` entity with raw content and normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Navigation post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Navigation slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'navigation' => array( 'type' => 'object' ),
					'message'    => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$navigation = mcp_abilities_gutenberg_get_navigation_entity( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $navigation ) ) {
					return array(
						'success' => false,
						'message' => $navigation->get_error_message(),
					);
				}

				return array(
					'success'    => true,
					'navigation' => $navigation,
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
		'gutenberg/create-navigation',
		array(
			'label'               => 'Create Navigation Entity',
			'description'         => 'Create a `wp_navigation` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title' => array( 'type' => 'string' ),
					'slug'  => array( 'type' => 'string' ),
					'status' => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'message'    => array( 'type' => 'string' ),
					'navigation' => array( 'type' => 'object' ),
					'content'    => array( 'type' => 'string' ),
					'summary'    => array( 'type' => 'object' ),
					'blocks'     => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_navigation_entity( is_array( $input ) ? $input : array() );
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
		'gutenberg/update-navigation',
		array(
			'label'               => 'Update Navigation Entity',
			'description'         => 'Update a `wp_navigation` entity from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'slug'    => array( 'type' => 'string' ),
					'status'  => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'message'    => array( 'type' => 'string' ),
					'navigation' => array( 'type' => 'object' ),
					'content'    => array( 'type' => 'string' ),
					'summary'    => array( 'type' => 'object' ),
					'blocks'     => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_navigation_entity( is_array( $input ) ? $input : array() );
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
		'gutenberg/find-navigation-usage',
		array(
			'label'               => 'Find Navigation Usage',
			'description'         => 'Find which templates or template parts reference a target navigation entity.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Navigation post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Navigation slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'usage'      => array( 'type' => 'object' ),
					'message'    => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$usage = mcp_abilities_gutenberg_find_navigation_usage( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $usage ) ) {
					return array(
						'success' => false,
						'message' => $usage->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'usage'   => $usage,
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
		'gutenberg/find-template-part-usage',
		array(
			'label'               => 'Find Template Part Usage',
			'description'         => 'Find which templates or template parts reference a target template part.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Template part post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Template part slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'usage'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$usage = mcp_abilities_gutenberg_find_template_part_usage( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $usage ) ) {
					return array(
						'success' => false,
						'message' => $usage->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'usage'   => $usage,
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
		'gutenberg/find-synced-pattern-usage',
		array(
			'label'               => 'Find Synced Pattern Usage',
			'description'         => 'Find which posts, pages, templates, template parts, or navigation entities reference a target synced pattern.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Synced pattern post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Synced pattern slug.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'usage'   => array( 'type' => 'object' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$usage = mcp_abilities_gutenberg_find_synced_pattern_usage( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $usage ) ) {
					return array(
						'success' => false,
						'message' => $usage->get_error_message(),
					);
				}

				return array(
					'success' => true,
					'usage'   => $usage,
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
					'upsert_matching_slug' => array(
						'type'        => 'boolean',
						'description' => 'Update the earliest existing page with the same slug instead of creating a duplicate page.',
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
		'gutenberg/create-synced-pattern',
		array(
			'label'               => 'Create Synced Pattern',
			'description'         => 'Create a reusable synced pattern (`wp_block`) from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title' ),
				'properties' => array(
					'title'   => array( 'type' => 'string' ),
					'slug'    => array( 'type' => 'string' ),
					'status'  => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'content' => array( 'type' => 'string' ),
					'blocks'  => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'pattern' => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_synced_pattern( is_array( $input ) ? $input : array() );
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
		'gutenberg/update-synced-pattern',
		array(
			'label'               => 'Update Synced Pattern',
			'description'         => 'Update a reusable synced pattern (`wp_block`) from raw content or normalized blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id'  => array( 'type' => 'integer' ),
					'title'    => array( 'type' => 'string' ),
					'slug'     => array( 'type' => 'string' ),
					'status'   => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'content'  => array( 'type' => 'string' ),
					'blocks'   => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'pattern' => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_save_synced_pattern( is_array( $input ) ? $input : array() );
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
		'gutenberg/extract-synced-pattern',
		array(
			'label'               => 'Extract Synced Pattern',
			'description'         => 'Extract a block subtree into a reusable synced pattern and optionally replace the source block with a `core/block` reference.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'title', 'path' ),
				'properties' => array(
					'title'          => array( 'type' => 'string' ),
					'slug'           => array( 'type' => 'string' ),
					'status'         => array(
						'type' => 'string',
						'enum' => array( 'publish', 'draft' ),
					),
					'post_id'        => array( 'type' => 'integer' ),
					'blocks'         => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'path'           => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'replace_source' => array( 'type' => 'boolean' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'message'   => array( 'type' => 'string' ),
					'pattern'   => array( 'type' => 'object' ),
					'extracted' => array( 'type' => 'object' ),
					'post'      => array( 'type' => array( 'object', 'null' ) ),
					'replaced'  => array( 'type' => 'boolean' ),
					'content'   => array( 'type' => 'string' ),
					'summary'   => array( 'type' => 'object' ),
					'blocks'    => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_extract_synced_pattern( is_array( $input ) ? $input : array() );
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
		'gutenberg/insert-synced-pattern-into-post',
		array(
			'label'               => 'Insert Synced Pattern Into Post',
			'description'         => 'Insert a synced pattern into a post as a reusable `core/block` reference.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id'    => array( 'type' => 'integer' ),
					'pattern_id' => array( 'type' => 'integer' ),
					'slug'       => array( 'type' => 'string' ),
					'parent_path'=> array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'position'   => array( 'type' => 'integer' ),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
					'post'    => array( 'type' => 'object' ),
					'pattern' => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_insert_synced_pattern_into_post( is_array( $input ) ? $input : array() );
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
		'gutenberg/create-page-from-pattern',
		array(
			'label'               => 'Create Page From Pattern',
			'description'         => 'Create or upsert a page from a registered block pattern.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'pattern_name' ),
				'properties' => array(
					'pattern_name' => array(
						'type'        => 'string',
						'description' => 'Registered pattern name.',
					),
					'title' => array(
						'type'        => 'string',
						'description' => 'Optional page title override.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional page slug.',
					),
					'upsert_matching_slug' => array(
						'type'        => 'boolean',
						'description' => 'Update the earliest existing page with the same slug instead of creating a duplicate page.',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Page status.',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
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
					'pattern' => array( 'type' => 'object' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				return mcp_abilities_gutenberg_create_page_from_pattern( is_array( $input ) ? $input : array() );
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
					'upsert_matching_slug' => array(
						'type'        => 'boolean',
						'description' => 'Update the earliest existing page with the same slug instead of creating a duplicate page.',
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
						'title'                => $payload['title'],
						'slug'                 => $payload['slug'],
						'status'               => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
						'content'              => $payload['content'],
						'upsert_matching_slug' => ! empty( $input['upsert_matching_slug'] ),
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
		'gutenberg/insert-pattern-into-post',
		array(
			'label'               => 'Insert Pattern Into Post',
			'description'         => 'Append, prepend, or replace a post or page with a registered block pattern.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id', 'pattern_name' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Target post or page ID.',
					),
					'pattern_name' => array(
						'type'        => 'string',
						'description' => 'Registered pattern name.',
					),
					'position' => array(
						'type'        => 'string',
						'description' => 'How to apply the pattern content.',
						'enum'        => array( 'append', 'prepend', 'replace' ),
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
				return mcp_abilities_gutenberg_insert_pattern_into_post( is_array( $input ) ? $input : array() );
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
		'gutenberg/transform-blocks',
		array(
			'label'               => 'Transform Gutenberg Blocks',
			'description'         => 'Apply structural transforms to a normalized Gutenberg block tree, such as wrapping in a group or replacing/removing top-level blocks.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'operation', 'blocks' ),
				'properties' => array(
					'operation' => array(
						'type'        => 'string',
						'enum'        => array( 'wrap-in-group', 'unwrap-single-group', 'append-block', 'prepend-block', 'replace-block', 'remove-block' ),
						'description' => 'Transform operation to apply.',
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'block' => array(
						'type'        => 'object',
						'description' => 'Replacement/appended/prepended block for relevant operations.',
					),
					'index' => array(
						'type'        => 'integer',
						'description' => 'Top-level block index for replace/remove operations.',
					),
					'attrs' => array(
						'type'        => 'object',
						'description' => 'Optional group attributes for wrap-in-group.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'operation' => array( 'type' => 'string' ),
					'content'   => array( 'type' => 'string' ),
					'summary'   => array( 'type' => 'object' ),
					'blocks'    => array( 'type' => 'array' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_transform_blocks( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/mutate-block-tree',
		array(
			'label'               => 'Mutate Gutenberg Block Tree',
			'description'         => 'Apply a nested path-based mutation to a normalized Gutenberg block tree.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'operation', 'path', 'blocks' ),
				'properties' => array(
					'operation' => array(
						'type' => 'string',
						'enum' => array( 'update-attrs', 'replace-block', 'remove-block' ),
					),
					'path' => array(
						'type'        => 'array',
						'description' => 'Nested zero-based block path, for example [0,1,2].',
						'items'       => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'attrs' => array(
						'type'        => 'object',
						'description' => 'Attributes to merge into the target block.',
					),
					'block' => array(
						'type'        => 'object',
						'description' => 'Replacement block for replace-block.',
					),
					'allow_unsafe_static_markup' => array(
						'type'        => 'boolean',
						'description' => 'Allow attr changes on static blocks even when saved HTML may go stale. Use only if markup will be regenerated separately.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'   => array( 'type' => 'boolean' ),
					'operation' => array( 'type' => 'string' ),
					'path'      => array( 'type' => 'array' ),
					'content'   => array( 'type' => 'string' ),
					'summary'   => array( 'type' => 'object' ),
					'blocks'    => array( 'type' => 'array' ),
					'render_risks' => array( 'type' => 'array' ),
					'render_safe'  => array( 'type' => 'boolean' ),
					'message'   => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_mutate_block_tree( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					$response = array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
					$error_data = $result->get_error_data();
					if ( is_array( $error_data ) && isset( $error_data['render_risks'] ) && is_array( $error_data['render_risks'] ) ) {
						$response['render_risks'] = $error_data['render_risks'];
						$response['render_safe']  = false;
					}
					return $response;
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/set-block-lock',
		array(
			'label'               => 'Set Block Lock',
			'description'         => 'Set `lock.move` and `lock.remove` attributes on a block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'lock_move' => array(
						'type'        => 'boolean',
						'description' => 'Prevent moving the block.',
					),
					'lock_remove' => array(
						'type'        => 'boolean',
						'description' => 'Prevent removing the block.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_set_block_lock( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/set-allowed-blocks',
		array(
			'label'               => 'Set Allowed Blocks',
			'description'         => 'Set `allowedBlocks` on a container block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks', 'allowed_blocks' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'allowed_blocks' => array(
						'type'        => 'array',
						'description' => 'Block names allowed inside the target container.',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_set_allowed_blocks( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/set-template-lock',
		array(
			'label'               => 'Set Template Lock',
			'description'         => 'Set `templateLock` on a block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks', 'template_lock' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'template_lock' => array(
						'type'        => array( 'string', 'boolean' ),
						'description' => 'false, all, insert, or contentOnly.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_set_template_lock( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/insert-inner-block',
		array(
			'label'               => 'Insert Inner Block',
			'description'         => 'Insert a child block into a container block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks', 'block' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'block' => array(
						'type' => 'object',
					),
					'position' => array(
						'type'        => 'integer',
						'description' => 'Optional insertion position inside inner_blocks.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'path'    => array( 'type' => 'array' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_insert_inner_block( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/duplicate-block',
		array(
			'label'               => 'Duplicate Block',
			'description'         => 'Duplicate a block at a nested path and insert the copy nearby or at a chosen sibling position.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'position' => array(
						'type'        => 'integer',
						'description' => 'Optional insertion position among the target block siblings.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'path'     => array( 'type' => 'array' ),
					'position' => array( 'type' => 'integer' ),
					'content'  => array( 'type' => 'string' ),
					'summary'  => array( 'type' => 'object' ),
					'blocks'   => array( 'type' => 'array' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_duplicate_block( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/move-block',
		array(
			'label'               => 'Move Block',
			'description'         => 'Move a block from one nested path to a root or container destination.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'from_path', 'to_parent_path', 'blocks' ),
				'properties' => array(
					'from_path' => array(
						'type'        => 'array',
						'description' => 'Path to the block being moved.',
						'items'       => array( 'type' => 'integer' ),
					),
					'to_parent_path' => array(
						'type'        => 'array',
						'description' => 'Destination parent path; use [] for the root block list.',
						'items'       => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'position' => array(
						'type'        => 'integer',
						'description' => 'Optional insertion position within the destination children.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'        => array( 'type' => 'boolean' ),
					'from_path'      => array( 'type' => 'array' ),
					'to_parent_path' => array( 'type' => 'array' ),
					'position'       => array( 'type' => 'integer' ),
					'content'        => array( 'type' => 'string' ),
					'summary'        => array( 'type' => 'object' ),
					'blocks'         => array( 'type' => 'array' ),
					'message'        => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_move_block( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/replace-block-text',
		array(
			'label'               => 'Replace Block Text',
			'description'         => 'Replace text across a full block tree or within a subtree at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'blocks', 'search', 'replace' ),
				'properties' => array(
					'path' => array(
						'type'        => 'array',
						'description' => 'Optional subtree path to limit the replacement scope.',
						'items'       => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'search' => array(
						'type'        => 'string',
						'description' => 'Text to find.',
					),
					'replace' => array(
						'type'        => 'string',
						'description' => 'Replacement text.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'           => array( 'type' => 'boolean' ),
					'content'           => array( 'type' => 'string' ),
					'summary'           => array( 'type' => 'object' ),
					'blocks'            => array( 'type' => 'array' ),
					'replacement_count' => array( 'type' => 'integer' ),
					'message'           => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_replace_block_text( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/get-block-bindings',
		array(
			'label'               => 'Get Block Bindings',
			'description'         => 'Return `metadata.bindings` and related metadata for a block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'path'     => array( 'type' => 'array' ),
					'bindings' => array( 'type' => 'object' ),
					'metadata' => array( 'type' => 'object' ),
					'block'    => array( 'type' => 'object' ),
					'message'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_get_block_bindings( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/set-block-bindings',
		array(
			'label'               => 'Set Block Bindings',
			'description'         => 'Set `metadata.bindings` for a block at a nested path.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'path', 'blocks', 'bindings' ),
				'properties' => array(
					'path' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'bindings' => array(
						'type'        => 'object',
						'description' => 'Binding map keyed by attribute name.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_set_block_bindings( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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
		'gutenberg/normalize-heading-levels',
		array(
			'label'               => 'Normalize Heading Levels',
			'description'         => 'Normalize heading levels across a Gutenberg block tree starting from a chosen level.',
			'category'            => 'block-editor',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'blocks' ),
				'properties' => array(
					'blocks' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'object' ),
					),
					'start_level' => array(
						'type'        => 'integer',
						'description' => 'Starting level for the first encountered heading.',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'content' => array( 'type' => 'string' ),
					'summary' => array( 'type' => 'object' ),
					'blocks'  => array( 'type' => 'array' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$result = mcp_abilities_gutenberg_normalize_heading_levels( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $result ) ) {
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
					);
				}
				return array_merge( array( 'success' => true ), $result );
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

				$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
				if ( is_wp_error( $layout_guard ) ) {
					return array(
						'success' => false,
						'message' => $layout_guard->get_error_message(),
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
