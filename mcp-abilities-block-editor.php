<?php
/**
 * Plugin Name: MCP Abilities - Block Editor
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-block-editor
 * Description: WordPress block-editor abilities for MCP. Parse, serialize, inspect, and update Gutenberg content safely.
 * Version: 0.1.0
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
 * Accepts both the normalized snake_case shape exposed by this plugin and the
 * native `parse_blocks()` shape so callers do not need a separate adapter.
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
			'scenario'         => 'Plain body copy or a standard text section',
			'best_block'       => 'core/paragraph',
			'alternatives'     => array( 'core/heading', 'core/list' ),
			'use_when'         => 'Use for normal prose, introductions, supporting details, and most article text.',
			'avoid_when'       => 'Avoid when the content is actually a heading, list, quote, callout, or button.',
			'notes'            => 'Paragraph should be the default text block. Do not fake headings with bold paragraphs.',
		),
		array(
			'scenario'         => 'Section title or content hierarchy',
			'best_block'       => 'core/heading',
			'alternatives'     => array( 'core/paragraph' ),
			'use_when'         => 'Use for titles and subheadings that define page structure.',
			'avoid_when'       => 'Avoid for decorative large text that is not actually a heading in the document outline.',
			'notes'            => 'Pick the heading level based on hierarchy, not visual size alone.',
		),
		array(
			'scenario'         => 'Steps, checklists, feature lists, or grouped bullets',
			'best_block'       => 'core/list',
			'alternatives'     => array( 'core/paragraph', 'core/columns' ),
			'use_when'         => 'Use when items are parallel, sequential, or scan-oriented.',
			'avoid_when'       => 'Avoid when each item needs rich nested layout; use columns or groups then.',
			'notes'            => 'Prefer ordered lists for steps and unordered lists for collections.',
		),
		array(
			'scenario'         => 'Standalone quote, testimonial, or cited statement',
			'best_block'       => 'core/quote',
			'alternatives'     => array( 'core/pullquote', 'core/paragraph' ),
			'use_when'         => 'Use when the quoted wording itself matters and should be semantically distinct.',
			'avoid_when'       => 'Avoid for normal emphasis or marketing copy that is not a real quotation.',
			'notes'            => 'Use pullquote only when the visual emphasis is intentional and the theme supports it well.',
		),
		array(
			'scenario'         => 'Primary call to action',
			'best_block'       => 'core/buttons',
			'alternatives'     => array( 'core/button' ),
			'use_when'         => 'Use for actions like contact, buy, book, download, or sign up.',
			'avoid_when'       => 'Avoid embedding button-like links inside paragraph text for primary actions.',
			'notes'            => 'Use the buttons container even for one button so layout stays extensible.',
		),
		array(
			'scenario'         => 'Image with optional caption',
			'best_block'       => 'core/image',
			'alternatives'     => array( 'core/gallery', 'core/media-text' ),
			'use_when'         => 'Use for a single visual asset that should stand on its own.',
			'avoid_when'       => 'Avoid when the image is tightly paired with explanatory text; use media-text then.',
			'notes'            => 'Prefer captions for credit or essential explanatory context, not duplicate alt text.',
		),
		array(
			'scenario'         => 'Image and text side by side',
			'best_block'       => 'core/media-text',
			'alternatives'     => array( 'core/columns', 'core/image', 'core/paragraph' ),
			'use_when'         => 'Use when one media item and one text region belong together as a single unit.',
			'avoid_when'       => 'Avoid when the layout needs more than two content regions or complex nesting.',
			'notes'            => 'Media-text is usually cleaner than manually balancing image and paragraph blocks in columns.',
		),
		array(
			'scenario'         => 'Multi-column comparison or side-by-side sections',
			'best_block'       => 'core/columns',
			'alternatives'     => array( 'core/group', 'core/media-text' ),
			'use_when'         => 'Use for comparisons, paired offers, or short parallel sections.',
			'avoid_when'       => 'Avoid very dense or deeply nested content that collapses poorly on mobile.',
			'notes'            => 'Keep column content balanced and stack-friendly.',
		),
		array(
			'scenario'         => 'Grouped section with shared background, padding, or layout wrapper',
			'best_block'       => 'core/group',
			'alternatives'     => array( 'core/columns', 'core/cover' ),
			'use_when'         => 'Use when several blocks belong together semantically or visually.',
			'avoid_when'       => 'Avoid adding groups with no styling or structural purpose.',
			'notes'            => 'Group is the safe general-purpose wrapper block.',
		),
		array(
			'scenario'         => 'Hero section or text over a visual background',
			'best_block'       => 'core/cover',
			'alternatives'     => array( 'core/group', 'core/image' ),
			'use_when'         => 'Use for banners, hero areas, and visually led intro sections.',
			'avoid_when'       => 'Avoid for long-form text blocks where readability depends on complex overlays.',
			'notes'            => 'Check contrast carefully; cover blocks are easy to make unreadable.',
		),
		array(
			'scenario'         => 'Separated pieces of content or layout rhythm',
			'best_block'       => 'core/separator',
			'alternatives'     => array( 'core/spacer' ),
			'use_when'         => 'Use when you need a visible section break.',
			'avoid_when'       => 'Avoid using separators just to add empty space.',
			'notes'            => 'Use spacer for whitespace and separator for actual visual division.',
		),
		array(
			'scenario'         => 'Vertical whitespace only',
			'best_block'       => 'core/spacer',
			'alternatives'     => array( 'core/group' ),
			'use_when'         => 'Use for intentional spacing between sections where theme spacing is insufficient.',
			'avoid_when'       => 'Avoid stacking many spacers instead of fixing the surrounding layout.',
			'notes'            => 'Use sparingly; excess spacers usually signal layout problems elsewhere.',
		),
		array(
			'scenario'         => 'Reusable structured data such as FAQ, how-to, or custom plugin output',
			'best_block'       => 'Use the plugin or custom block that owns the feature',
			'alternatives'     => array( 'core/group', 'core/paragraph' ),
			'use_when'         => 'Use when semantics or rendering rely on a specific block from a plugin or theme.',
			'avoid_when'       => 'Avoid recreating complex plugin output with plain paragraphs and headings.',
			'notes'            => 'If a feature has its own block, prefer that over manual imitation.',
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
 * Register Gutenberg abilities.
 */
function mcp_abilities_gutenberg_register_abilities(): void {
	if ( ! mcp_abilities_gutenberg_check_dependencies() ) {
		return;
	}

	wp_register_ability(
		'gutenberg/block-guidance',
		array(
			'label'               => 'Get Gutenberg Block Guidance',
			'description'         => 'Recommend which Gutenberg block to use for common content scenarios.',
			'category'            => 'content',
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

	wp_register_ability(
		'gutenberg/parse-content',
		array(
			'label'               => 'Parse Gutenberg Content',
			'description'         => 'Parse raw post content into a normalized Gutenberg block tree.',
			'category'            => 'content',
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
					'success'  => array( 'type' => 'boolean' ),
					'message'  => array( 'type' => 'string' ),
					'summary'  => array( 'type' => 'object' ),
					'blocks'   => array( 'type' => 'array' ),
					'content'  => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => function ( $input = array() ): array {
				$content = isset( $input['content'] ) ? (string) $input['content'] : '';

				$blocks = parse_blocks( $content );

				return array(
					'success' => true,
					'message' => 'Content parsed successfully.',
					'summary' => mcp_abilities_gutenberg_content_summary( $content ),
					'blocks'  => mcp_abilities_gutenberg_normalize_blocks( $blocks ),
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

	wp_register_ability(
		'gutenberg/serialize-blocks',
		array(
			'label'               => 'Serialize Gutenberg Blocks',
			'description'         => 'Serialize a normalized Gutenberg block tree into valid block markup.',
			'category'            => 'content',
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

	wp_register_ability(
		'gutenberg/get-post-blocks',
		array(
			'label'               => 'Get Post Gutenberg Blocks',
			'description'         => 'Load a post or page and return its raw content plus normalized Gutenberg blocks.',
			'category'            => 'content',
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
					'success'      => array( 'type' => 'boolean' ),
					'message'      => array( 'type' => 'string' ),
					'post'         => array( 'type' => 'object' ),
					'content'      => array( 'type' => 'string' ),
					'summary'      => array( 'type' => 'object' ),
					'blocks'       => array( 'type' => 'array' ),
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

	wp_register_ability(
		'gutenberg/update-post-blocks',
		array(
			'label'               => 'Update Post Gutenberg Blocks',
			'description'         => 'Update a post or page using normalized Gutenberg blocks or raw content.',
			'category'            => 'content',
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

				$updated_post = get_post( $post_id );
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
add_action( 'wp_abilities_api_init', 'mcp_abilities_gutenberg_register_abilities' );
