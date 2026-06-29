<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Style book, section recipes, page generation, pattern insertion, and media assignment helpers.
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
 * Finalize generated Gutenberg content before returning it to callers.
 *
 * @param array<string,mixed> $payload Generated payload containing a content string.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_finalize_generated_content_payload( array $payload ) {
	$content = isset( $payload['content'] ) ? (string) $payload['content'] : '';
	$content = mcp_abilities_gutenberg_prepare_content_for_editor_save( $content );

	$valid = mcp_abilities_gutenberg_assert_valid_gutenberg_content( $content );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$payload['content'] = $content;
	$payload['summary'] = mcp_abilities_gutenberg_content_summary( $content );
	$payload['blocks']  = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $content ) );

	return $payload;
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
				. '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->'
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
				. '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
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
				. '<!-- wp:columns {"verticalAlignment":"top"} --><div class="wp-block-columns are-vertically-aligned-top">'
				. '<!-- wp:column {"verticalAlignment":"top","width":"40%"} --><div class="wp-block-column is-vertically-aligned-top" style="flex-basis:40%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /--></div><!-- /wp:column -->'
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

	return mcp_abilities_gutenberg_finalize_generated_content_payload(
		array(
			'recipe'  => $recipe,
			'content' => $content,
			'query'   => $query_attrs['query'],
		)
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
			$content = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_paragraph_block( $eyebrow )
				. mcp_abilities_gutenberg_heading_block( $title, 1 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. mcp_abilities_gutenberg_buttons_block( $cta )
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
			$content = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
				. mcp_abilities_gutenberg_heading_block( $title, 2 )
				. mcp_abilities_gutenberg_paragraph_block( $body )
				. mcp_abilities_gutenberg_buttons_block( $cta )
				. '</div><!-- /wp:group -->';
			break;

		case 'pricing':
			$columns = '';
			foreach ( array_slice( $items, 0, 3 ) as $index => $item ) {
				$price = '$' . (string) ( 12 + ( $index * 8 ) );
				$columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
					. mcp_abilities_gutenberg_heading_block( $item, 3 )
					. mcp_abilities_gutenberg_paragraph_block( $price )
					. mcp_abilities_gutenberg_list_block( array( $body, 'Package details', 'Clear next step' ) )
					. mcp_abilities_gutenberg_buttons_block( $cta )
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
				$columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
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
				. mcp_abilities_gutenberg_list_block( array( 'Address line', 'Opening hours', 'Phone or email' ) )
				. mcp_abilities_gutenberg_buttons_block( $cta )
				. '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"top"} --><div class="wp-block-column is-vertically-aligned-top">'
				. mcp_abilities_gutenberg_paragraph_block( 'Add a map, directions, or service-area details here.' )
				. '</div><!-- /wp:column --></div><!-- /wp:columns -->';
			break;

		default:
			return new WP_Error( 'mcp_gutenberg_unknown_section', 'Unsupported section recipe.' );
	}

	return mcp_abilities_gutenberg_finalize_generated_content_payload(
		array(
			'section' => $section,
			'content' => $content,
		)
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
	$syntax_issues       = mcp_abilities_gutenberg_collect_syntax_issues( $content );

	$top_level_names = array();
	foreach ( $normalized as $block ) {
		$name = isset( $block['block_name'] ) ? (string) $block['block_name'] : '';
		if ( '' !== $name ) {
			$top_level_names[] = $name;
		}
	}

	$all_block_names = mcp_abilities_gutenberg_collect_block_names( $normalized );
	$layout_risks    = mcp_abilities_gutenberg_collect_content_layout_risks( $content );
	$editor_compatibility_issues = mcp_abilities_gutenberg_collect_editor_compatibility_issues( $content );
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
	if ( ! empty( $editor_compatibility_issues ) ) {
		$issue_codes = array_values(
			array_unique(
				array_map(
					static function ( array $issue ): string {
						return (string) ( $issue['code'] ?? '' );
					},
					$editor_compatibility_issues
				)
			)
		);
		$warnings[] = 'Editor-compatibility normalization recommended before save: ' . implode( ', ', $issue_codes ) . '.';
	}

	return array(
		'is_valid_gutenberg'      => empty( $syntax_issues ),
		'syntax_issues'          => $syntax_issues,
		'syntax_issue_count'     => count( $syntax_issues ),
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
		'editor_compatibility'    => array(
			'issue_count' => count( $editor_compatibility_issues ),
			'issues'      => $editor_compatibility_issues,
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
	$tone          = isset( $input['tone'] ) ? (string) $input['tone'] : 'clear, useful, and confident';
	$cta_primary   = isset( $input['primary_cta_text'] ) ? (string) $input['primary_cta_text'] : 'Get Started';
	$cta_secondary = isset( $input['secondary_cta_text'] ) ? (string) $input['secondary_cta_text'] : 'See Details';

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
 * Build a buttons block with one or two plain button labels.
 *
 * @param string      $primary Primary button label.
 * @param string|null $secondary Optional secondary button label.
 * @return string
 */
function mcp_abilities_gutenberg_buttons_block( string $primary, ?string $secondary = null ): string {
	$buttons = '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">' . esc_html( $primary ) . '</a></div><!-- /wp:button -->';

	if ( null !== $secondary && '' !== trim( $secondary ) ) {
		$buttons .= '<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">' . esc_html( $secondary ) . '</a></div><!-- /wp:button -->';
	}

	return '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"left"}} --><div class="wp-block-buttons">' . $buttons . '</div><!-- /wp:buttons -->';
}

/**
 * Build a plain list block.
 *
 * @param array<int,string> $items List items.
 * @param bool              $ordered Whether to output an ordered list.
 * @return string
 */
function mcp_abilities_gutenberg_list_block( array $items, bool $ordered = false ): string {
	$tag  = $ordered ? 'ol' : 'ul';
	$list = '';

	foreach ( $items as $item ) {
		$list .= '<li>' . wp_kses_post( $item ) . '</li>';
	}

	return '<!-- wp:list' . ( $ordered ? ' {"ordered":true}' : '' ) . ' --><' . $tag . '>' . $list . '</' . $tag . '><!-- /wp:list -->';
}

/**
 * Generate landing page block content.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_generate_landing_page_payload( array $input ) {
	$business_name = isset( $input['business_name'] ) ? trim( (string) $input['business_name'] ) : 'Local Business';
	$industry      = isset( $input['industry'] ) ? trim( (string) $input['industry'] ) : 'business';
	$tone          = isset( $input['tone'] ) ? trim( (string) $input['tone'] ) : 'clear, useful, and confident';
	$slug          = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : sanitize_title( $business_name );
	$primary_cta   = isset( $input['primary_cta_text'] ) ? trim( (string) $input['primary_cta_text'] ) : 'Get Started';
	$secondary_cta = isset( $input['secondary_cta_text'] ) ? trim( (string) $input['secondary_cta_text'] ) : 'See Details';
	$offerings     = isset( $input['offerings'] ) && is_array( $input['offerings'] ) ? array_values( array_filter( array_map( 'strval', $input['offerings'] ) ) ) : array(
		'Primary offer',
		'Support option',
		'Next-step consultation',
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
		$offer_columns .= '<!-- wp:column --><div class="wp-block-column"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. mcp_abilities_gutenberg_heading_block( $offering, 3 )
			. mcp_abilities_gutenberg_paragraph_block( 'Describe the concrete outcome, who it helps, and what the visitor should understand next.' )
			. '</div><!-- /wp:group --></div><!-- /wp:column -->';
	}

	$content  = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
		. mcp_abilities_gutenberg_paragraph_block( $industry )
		. mcp_abilities_gutenberg_heading_block( $business_name, 1 )
		. mcp_abilities_gutenberg_paragraph_block( $business_name . ' presents a ' . $tone . ' offer for people who need a clear reason to choose the next step.' )
		. mcp_abilities_gutenberg_buttons_block( $primary_cta, $secondary_cta )
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
		. mcp_abilities_gutenberg_heading_block( 'What you get', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'Use this section to make the offer concrete before adding detail. Each item should carry a distinct reason to keep reading.' )
		. '<!-- wp:columns {"style":{"spacing":{"blockGap":"1.25rem"}}} --><div class="wp-block-columns">' . $offer_columns . '</div><!-- /wp:columns -->'
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"56px"} --><div style="height:56px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
		. mcp_abilities_gutenberg_heading_block( 'Why people choose ' . $business_name, 2 )
		. mcp_abilities_gutenberg_list_block(
			array(
				'A clear promise that matches the visitor’s immediate problem.',
				'Specific proof points that make the promise easier to believe.',
				'A focused path from interest to action without unnecessary choices.',
			)
		)
		. '</div><!-- /wp:group -->';

	$content .= '<!-- wp:spacer {"height":"56px"} --><div style="height:56px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:columns {"style":{"spacing":{"blockGap":"2rem"}}} --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">'
		. mcp_abilities_gutenberg_heading_block( 'How it works', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'Use this section to explain the route from first contact to outcome. Keep it practical and easy to scan.' )
		. '</div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column">'
		. mcp_abilities_gutenberg_list_block(
			array(
				'Clarify the need and the best-fit option.',
				'Agree on the next step and what success looks like.',
				'Deliver the work with clear checkpoints and a visible result.',
			),
			true
		)
		. '</div><!-- /wp:column --></div><!-- /wp:columns -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:quote {"className":"is-style-large"} --><blockquote class="wp-block-quote is-style-large"><p>Add a short proof point, customer quote, or internal result that supports the promise.</p><cite>Proof point</cite></blockquote><!-- /wp:quote -->';

	$content .= '<!-- wp:spacer {"height":"48px"} --><div style="height:48px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
	$content .= '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
	$content .= '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
		. mcp_abilities_gutenberg_heading_block( 'Ready to take the next step with ' . $business_name . '?', 2 )
		. mcp_abilities_gutenberg_paragraph_block( 'Use this final call to action for the main conversion path once the operational details are confirmed.' )
		. mcp_abilities_gutenberg_buttons_block( $primary_cta )
		. '</div><!-- /wp:group -->';

	$title = isset( $input['title'] ) && '' !== trim( (string) $input['title'] ) ? trim( (string) $input['title'] ) : $business_name;

	return mcp_abilities_gutenberg_finalize_generated_content_payload(
		array(
			'title'     => $title,
			'slug'      => $slug,
			'content'   => $content,
			'blueprint' => $blueprint,
		)
	);
}

/**
 * Create a page from content or blocks.
 *
 * @param array<string,mixed> $input Input data.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_create_page_from_input( array $input ): array {
	$content = mcp_abilities_gutenberg_build_editor_safe_content_from_input( $input );
	if ( is_wp_error( $content ) ) {
		return mcp_abilities_gutenberg_error_response( $content );
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
		$update_result = mcp_abilities_gutenberg_update_block_document_post(
			$existing_post,
			$content,
			$input,
			array(
				'post_title'  => $title,
				'post_status' => $status,
			),
			'Existing page updated successfully.'
		);

		if ( is_wp_error( $update_result ) ) {
			return mcp_abilities_gutenberg_error_response( $update_result );
		}

		return $update_result;
	}

	$write_guard = mcp_abilities_gutenberg_assert_block_document_write_safe( $content, $input );
	if ( is_wp_error( $write_guard ) ) {
		return mcp_abilities_gutenberg_error_response( $write_guard );
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
		return mcp_abilities_gutenberg_error_response( $post_id );
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
		return mcp_abilities_gutenberg_error_response( $pattern );
	}

	$create = mcp_abilities_gutenberg_create_page_from_input(
		array(
			'title'                => isset( $input['title'] ) ? (string) $input['title'] : (string) $pattern['title'],
			'slug'                 => isset( $input['slug'] ) ? (string) $input['slug'] : sanitize_title( (string) $pattern['title'] ),
			'status'               => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
			'upsert_matching_slug' => ! empty( $input['upsert_matching_slug'] ),
			'allow_design_markup_loss' => ! empty( $input['allow_design_markup_loss'] ),
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
		return mcp_abilities_gutenberg_error_response( $post );
	}

	$pattern = mcp_abilities_gutenberg_get_pattern_details( $pattern_name );
	if ( is_wp_error( $pattern ) ) {
		return mcp_abilities_gutenberg_error_response( $pattern );
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
	$content = mcp_abilities_gutenberg_prepare_content_for_editor_save( $content );

	$result = mcp_abilities_gutenberg_update_block_document_post(
		$post,
		$content,
		$input,
		array(),
		'Pattern inserted successfully.'
	);
	if ( is_wp_error( $result ) ) {
		return mcp_abilities_gutenberg_error_response( $result );
	}

	return $result;
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
		return mcp_abilities_gutenberg_error_response( $post );
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
