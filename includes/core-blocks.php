<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a consistent ability error response from a WordPress error.
 *
 * @param WP_Error $error Error object.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_error_response( WP_Error $error ): array {
	$data = $error->get_error_data();

	$response = array(
		'success' => false,
		'code'    => $error->get_error_code(),
		'message' => $error->get_error_message(),
	);

	if ( is_array( $data ) && array_key_exists( 'issues', $data ) ) {
		$response['issues'] = $data['issues'];
	}

	if ( null !== $data && array() !== $data ) {
		$response['data'] = $data;
	}

	return $response;
}

/**
 * Core Gutenberg block parsing, serialization, editor-safety, and post helpers.
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
 * Return an element's inner HTML.
 *
 * @param DOMElement $element Element to inspect.
 * @return string
 */
function mcp_abilities_gutenberg_dom_inner_html( DOMElement $element ): string {
	$html = '';
	foreach ( $element->childNodes as $child ) {
		$html .= $element->ownerDocument->saveHTML( $child );
	}

	return $html;
}

/**
 * Repair URL-ish block attribute strings that lost JSON unicode escaping during transport.
 *
 * JSON clients sometimes strip the backslash from `\u0026`, leaving URL attrs like
 * `...?utm_source=siteu0026utm_medium=...`. Gutenberg can then compare a valid saved
 * anchor href against a broken block-comment attr. Keep the repair narrow to URLs.
 *
 * @param mixed $value Attribute value.
 * @return mixed
 */
function mcp_abilities_gutenberg_repair_urlish_attr_value( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $child ) {
			$value[ $key ] = mcp_abilities_gutenberg_repair_urlish_attr_value( $child );
		}
		return $value;
	}

	if ( ! is_string( $value ) || false === strpos( $value, '://' ) ) {
		return $value;
	}

	$decoded = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$decoded = preg_replace( '/(?<=[A-Za-z0-9_%.-])u0026(?=[A-Za-z0-9_.-]+=)/', '&', $decoded );

	return is_string( $decoded ) ? $decoded : $value;
}

/**
 * Normalize legacy core/list markup to the nested list-item shape expected by current Gutenberg.
 *
 * WordPress PHP can round-trip old list markup (`<ul><li>...`) while the editor JS
 * treats the same block as stale because current core/list persists `core/list-item`
 * inner blocks. Normalize before MCP writes so pages reopen cleanly in the editor.
 *
 * @param array<string,mixed> $block Parsed WordPress block.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_normalize_list_block_for_editor( array $block ): array {
	if ( 'core/list' !== ( $block['blockName'] ?? '' ) ) {
		return $block;
	}

	if ( ! empty( $block['innerBlocks'] ) || empty( $block['innerHTML'] ) || ! is_string( $block['innerHTML'] ) ) {
		return $block;
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-list-root">' . $block['innerHTML'] . '</div></body></html>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return $block;
	}

	$xpath      = new DOMXPath( $document );
	$list_nodes = $xpath->query( '//*[@id="mcp-list-root"]/*[self::ul or self::ol][1]' );
	$list       = $list_nodes instanceof DOMNodeList ? $list_nodes->item( 0 ) : null;
	if ( ! $list instanceof DOMElement ) {
		return $block;
	}

	$item_nodes = $xpath->query( './li', $list );
	if ( ! $item_nodes instanceof DOMNodeList || 0 === $item_nodes->length ) {
		return $block;
	}

	$tag_name = strtolower( $list->tagName );
	$attrs    = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	if ( 'ol' === $tag_name ) {
		$attrs['ordered'] = true;
	} elseif ( array_key_exists( 'ordered', $attrs ) && false === (bool) $attrs['ordered'] ) {
		unset( $attrs['ordered'] );
	}

	$class_tokens = preg_split( '/\s+/', trim( (string) $list->getAttribute( 'class' ) ) );
	$class_tokens = is_array( $class_tokens ) ? array_filter( $class_tokens ) : array();
	if ( ! in_array( 'wp-block-list', $class_tokens, true ) ) {
		$class_tokens[] = 'wp-block-list';
	}

	$attribute_parts = array( 'class="' . esc_attr( implode( ' ', array_values( array_unique( $class_tokens ) ) ) ) . '"' );
	if ( 'ol' === $tag_name ) {
		if ( $list->hasAttribute( 'start' ) ) {
			$attribute_parts[] = 'start="' . esc_attr( (string) $list->getAttribute( 'start' ) ) . '"';
		}
		if ( $list->hasAttribute( 'reversed' ) ) {
			$attribute_parts[] = 'reversed';
			$attrs['reversed'] = true;
		}
	}

	$inner_blocks  = array();
	$inner_content = array( '<' . $tag_name . ' ' . implode( ' ', $attribute_parts ) . '>' );

	foreach ( $item_nodes as $item_node ) {
		if ( ! $item_node instanceof DOMElement ) {
			continue;
		}

		$item_attrs = array();
		$raw_value  = trim( mcp_abilities_gutenberg_dom_inner_html( $item_node ) );
		if ( '' !== $raw_value ) {
			$item_attrs['content'] = $raw_value;
		}

		$item_html = '<li>' . mcp_abilities_gutenberg_dom_inner_html( $item_node ) . '</li>';
		$inner_blocks[] = array(
			'blockName'    => 'core/list-item',
			'attrs'        => $item_attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $item_html,
			'innerContent' => array( $item_html ),
		);
		$inner_content[] = null;
	}

	$inner_content[] = '</' . $tag_name . '>';

	$block['attrs']        = $attrs;
	$block['innerBlocks']  = $inner_blocks;
	$block['innerHTML']    = '<' . $tag_name . ' ' . implode( ' ', $attribute_parts ) . '></' . $tag_name . '>';
	$block['innerContent'] = $inner_content;

	return $block;
}

/**
 * Normalize core/button markup to match editor-generated classes for style attrs.
 *
 * @param array<string,mixed> $block Parsed WordPress block.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_normalize_button_block_for_editor( array $block ): array {
	if ( 'core/button' !== ( $block['blockName'] ?? '' ) ) {
		return $block;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$attrs = mcp_abilities_gutenberg_repair_urlish_attr_value( $attrs );
	$block['attrs'] = is_array( $attrs ) ? $attrs : array();

	$border = $block['attrs']['style']['border'] ?? null;
	if ( ! is_array( $border ) || empty( $border['color'] ) || empty( $block['innerHTML'] ) || ! is_string( $block['innerHTML'] ) ) {
		return $block;
	}

	$internal_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument();
	$loaded          = $document->loadHTML(
		'<!DOCTYPE html><html><body><div id="mcp-button-root">' . $block['innerHTML'] . '</div></body></html>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $internal_errors );

	if ( ! $loaded ) {
		return $block;
	}

	$xpath      = new DOMXPath( $document );
	$link_nodes = $xpath->query( '//*[@id="mcp-button-root"]//*[self::a or self::button][1]' );
	$link       = $link_nodes instanceof DOMNodeList ? $link_nodes->item( 0 ) : null;
	if ( ! $link instanceof DOMElement ) {
		return $block;
	}

	$class_tokens = preg_split( '/\s+/', trim( (string) $link->getAttribute( 'class' ) ) );
	$class_tokens = is_array( $class_tokens ) ? array_filter( $class_tokens ) : array();
	if ( ! in_array( 'has-border-color', $class_tokens, true ) ) {
		$class_tokens[] = 'has-border-color';
		$link->setAttribute( 'class', implode( ' ', array_values( array_unique( $class_tokens ) ) ) );
	}

	if ( isset( $block['attrs']['url'] ) && is_string( $block['attrs']['url'] ) && $link->hasAttribute( 'href' ) ) {
		$link->setAttribute( 'href', $block['attrs']['url'] );
	}

	$root_nodes = $xpath->query( '//*[@id="mcp-button-root"]' );
	$root       = $root_nodes instanceof DOMNodeList ? $root_nodes->item( 0 ) : null;
	if ( $root instanceof DOMElement ) {
		$block['innerHTML'] = mcp_abilities_gutenberg_dom_inner_html( $root );
		$block['innerContent'] = array( $block['innerHTML'] );
	}

	return $block;
}

/**
 * Recursively normalize parsed blocks to avoid editor-invalid saved content.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed WordPress blocks.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_normalize_blocks_for_editor_save( array $blocks ): array {
	foreach ( $blocks as $index => $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
			$block['attrs'] = mcp_abilities_gutenberg_repair_urlish_attr_value( $block['attrs'] );
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = mcp_abilities_gutenberg_normalize_blocks_for_editor_save( $block['innerBlocks'] );
		}

		$block = mcp_abilities_gutenberg_normalize_list_block_for_editor( $block );
		$block = mcp_abilities_gutenberg_normalize_button_block_for_editor( $block );

		$blocks[ $index ] = $block;
	}

	return $blocks;
}

/**
 * Serialize blocks through MCP's editor-safety normalizer.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed WordPress blocks.
 * @return string
 */
function mcp_abilities_gutenberg_serialize_blocks_for_editor( array $blocks ): string {
	return serialize_blocks( mcp_abilities_gutenberg_normalize_blocks_for_editor_save( $blocks ) );
}

/**
 * Prepare raw Gutenberg content for editor-safe saving.
 *
 * @param string $content Raw Gutenberg content.
 * @return string
 */
function mcp_abilities_gutenberg_prepare_content_for_editor_save( string $content ): string {
	if ( '' === trim( $content ) || false === strpos( $content, '<!-- wp:' ) ) {
		return $content;
	}

	return mcp_abilities_gutenberg_serialize_blocks_for_editor( parse_blocks( $content ) );
}

/**
 * Collect high-confidence Gutenberg syntax issues that PHP parse_blocks() may tolerate.
 *
 * @param string $content Raw Gutenberg content.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_syntax_issues( string $content ): array {
	$issues = array();
	$stack  = array();

	if ( '' === trim( $content ) ) {
		$issues[] = array(
			'code'    => 'empty_content',
			'message' => 'Content is empty; a Gutenberg write must include at least one block.',
		);
		return $issues;
	}

	if ( false === strpos( $content, '<!-- wp:' ) && false === strpos( $content, '<!-- /wp:' ) ) {
		$issues[] = array(
			'code'    => 'missing_gutenberg_blocks',
			'message' => 'Content does not contain Gutenberg block comments.',
		);
		return $issues;
	}

	preg_match_all( '/<!--(.*?)-->/s', $content, $matches, PREG_OFFSET_CAPTURE );
	foreach ( $matches[1] as $match ) {
		$comment = trim( (string) $match[0] );
		$offset  = (int) $match[1];

		if ( 0 !== strpos( $comment, 'wp:' ) && 0 !== strpos( $comment, '/wp:' ) ) {
			continue;
		}

		if ( preg_match( '/^\/wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)$/i', $comment, $closing ) ) {
			$name = (string) $closing[1];
			if ( false === strpos( $name, '/' ) ) {
				$name = 'core/' . $name;
			}
			$open = array_pop( $stack );
			if ( null === $open ) {
				$issues[] = array(
					'code'       => 'unexpected_closing_block',
					'block_name' => $name,
					'offset'     => $offset,
					'message'    => sprintf( 'Closing block "%s" has no matching opening block.', $name ),
				);
				continue;
			}

			if ( $open['name'] !== $name ) {
				$issues[] = array(
					'code'                => 'mismatched_closing_block',
					'block_name'          => $name,
					'expected_block_name' => $open['name'],
					'offset'              => $offset,
					'open_offset'         => $open['offset'],
					'message'             => sprintf( 'Closing block "%s" does not match open block "%s".', $name, $open['name'] ),
				);
			}
			continue;
		}

		if ( ! preg_match( '/^wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)(.*)$/is', $comment, $opening ) ) {
			$issues[] = array(
				'code'    => 'malformed_block_comment',
				'offset'  => $offset,
				'comment' => mb_substr( $comment, 0, 160 ),
				'message' => 'Block comment starts with wp: but is not a valid Gutenberg block delimiter.',
			);
			continue;
		}

		$name = (string) $opening[1];
		if ( false === strpos( $name, '/' ) ) {
			$name = 'core/' . $name;
		}
		$tail = trim( (string) $opening[2] );
		$self_closing = false;
		if ( '' !== $tail && preg_match( '/\/\s*$/', $tail ) ) {
			$self_closing = true;
			$tail = trim( preg_replace( '/\/\s*$/', '', $tail ) ?? $tail );
		}

		if ( '' !== $tail ) {
			json_decode( $tail, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$issues[] = array(
					'code'       => 'invalid_block_attributes_json',
					'block_name' => $name,
					'offset'     => $offset,
					'json_error' => json_last_error_msg(),
					'message'    => sprintf( 'Block "%s" has invalid JSON attributes: %s.', $name, json_last_error_msg() ),
				);
			}
		}

		if ( ! $self_closing ) {
			$stack[] = array(
				'name'   => $name,
				'offset' => $offset,
			);
		}
	}

	foreach ( array_reverse( $stack ) as $open ) {
		$issues[] = array(
			'code'       => 'unclosed_block',
			'block_name' => $open['name'],
			'offset'     => $open['offset'],
			'message'    => sprintf( 'Opening block "%s" is missing a closing block comment.', $open['name'] ),
		);
	}

	$parsed_blocks = parse_blocks( $content );
	foreach ( $parsed_blocks as $index => $block ) {
		if ( is_array( $block ) && empty( $block['blockName'] ) && '' !== trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) ) ) {
			$issues[] = array(
				'code'    => 'freeform_html_outside_blocks',
				'index'   => (int) $index,
				'message' => 'Content contains freeform HTML outside Gutenberg block comments.',
			);
		}
	}

	return $issues;
}

/**
 * Require valid Gutenberg block syntax and explain concrete failures.
 *
 * @param string $content Raw or prepared Gutenberg content.
 * @return true|WP_Error
 */
function mcp_abilities_gutenberg_assert_valid_gutenberg_content( string $content ) {
	$issues = mcp_abilities_gutenberg_collect_syntax_issues( $content );
	if ( empty( $issues ) ) {
		return true;
	}

	$messages = array();
	foreach ( array_slice( $issues, 0, 5 ) as $issue ) {
		$messages[] = (string) ( $issue['message'] ?? $issue['code'] ?? 'Invalid Gutenberg block syntax.' );
	}

	return new WP_Error(
		'mcp_gutenberg_invalid_block_content',
		'Blocked Gutenberg content because it is not valid block code: ' . implode( ' ', $messages ),
		array(
			'issues' => $issues,
		)
	);
}

/**
 * Detect server-observable editor compatibility issues that PHP round-trips can miss.
 *
 * @param string $content Raw Gutenberg content.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_editor_compatibility_issues( string $content ): array {
	$issues = array();
	$blocks = parse_blocks( $content );

	$walk = static function ( array $nodes, array $path = array() ) use ( &$walk, &$issues ): void {
		foreach ( $nodes as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$current_path = array_merge( $path, array( $index ) );
			$block_name   = (string) ( $block['blockName'] ?? '' );
			$attrs        = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$inner_html   = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';

			if ( 'core/list' === $block_name && empty( $block['innerBlocks'] ) && preg_match( '/<(ul|ol)\\b[^>]*>\\s*<li\\b/i', $inner_html ) ) {
				$issues[] = array(
					'severity'   => 'warning',
					'code'       => 'legacy_core_list_serialization',
					'path'       => $current_path,
					'block_name' => $block_name,
					'message'    => 'core/list uses legacy flat <li> markup without core/list-item inner blocks; current Gutenberg editor may mark it invalid.',
				);
			}

			if ( 'core/button' === $block_name ) {
				$border = $attrs['style']['border'] ?? null;
				if ( is_array( $border ) && ! empty( $border['color'] ) && false === strpos( $inner_html, 'has-border-color' ) ) {
					$issues[] = array(
						'severity'   => 'warning',
						'code'       => 'button_border_class_mismatch',
						'path'       => $current_path,
						'block_name' => $block_name,
						'message'    => 'core/button has border color style but saved anchor markup lacks has-border-color; current Gutenberg editor may mark it invalid.',
					);
				}
			}

			array_walk_recursive(
				$attrs,
				static function ( $value ) use ( &$issues, $current_path, $block_name ): void {
					if ( is_string( $value ) && false !== strpos( $value, '://' ) && preg_match( '/(?<=[A-Za-z0-9_%.-])u0026(?=[A-Za-z0-9_.-]+=)/', $value ) ) {
						$issues[] = array(
							'severity'   => 'warning',
							'code'       => 'url_unicode_escape_backslash_lost',
							'path'       => $current_path,
							'block_name' => $block_name,
							'message'    => 'A URL attribute contains u0026 without the JSON backslash; save normalization will repair it to an ampersand.',
						);
					}
				}
			);

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$walk( $block['innerBlocks'], $current_path );
			}
		}
	};

	$walk( $blocks );

	return $issues;
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
		'templatelock',
		'allowedblocks',
		'metadata',
	);

	$path = strtolower( $path );

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
			'scenario'     => 'Proof points with a short label and a supporting explanation',
			'best_block'   => 'core/columns',
			'alternatives' => array( 'core/group', 'core/list' ),
			'use_when'     => 'Use when each proof point carries two layers: a short headline-like label plus one helpful supporting sentence.',
			'avoid_when'   => 'Avoid squeezing label-plus-explanation proof into chip rails, inline metadata strings, or fake one-line pills.',
			'notes'        => 'If each proof point says something real, give it a calm vertical stack: label first, explanation below. Treat it like an open proof list, not like compressed metadata.',
		),
		array(
			'scenario'     => 'Support card ending with short proof statements',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/list', 'core/paragraph' ),
			'use_when'     => 'Use when a support card ends with two to four short reassurance or working-style statements that belong to the same card, not to a separate section below it.',
			'avoid_when'   => 'Avoid leaving the statements as one loose bottom paragraph, shrinking them below the surrounding text floor, or decorating them with fussy marker clutter that adds more styling than meaning.',
			'notes'        => 'Treat the ending as a labeled micro-proof stack: a quiet label, simple vertical rows, and enough width for each support line to breathe across the card. Keep the support text on the same readable floor as the surrounding body copy instead of making the card footer quietly smaller.',
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
			'scenario'     => 'Custom page-level CSS with alignfull Gutenberg sections',
			'best_block'   => 'core/group',
			'alternatives' => array( 'core/cover', 'core/group' ),
			'use_when'     => 'Use when a page has its own shell or width system and also stacks Gutenberg `alignfull` sections such as heroes, bands, or footers.',
			'avoid_when'   => 'Avoid leaving `alignfull` on default breakout math once the page has custom width rules, shell sizing, or full-width wrapper logic of its own.',
			'notes'        => 'If the page defines its own width system, neutralize `alignfull` margins explicitly or switch to non-breakout full-width wrappers. Otherwise browser-specific scrollbar width can create horizontal rails even when the page appears fine at first glance.',
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
		array(
			'scenario'     => 'Visually designed FAQ section that still needs machine-readable schema',
			'best_block'   => 'Keep the visual FAQ layout and add matching FAQ schema underneath it',
			'alternatives' => array( 'Use the plugin or custom block that owns FAQ output', 'core/group' ),
			'use_when'     => 'Use when the FAQ design works visually, but you still need real FAQ semantics for schema or search features.',
			'avoid_when'   => 'Avoid flattening a good visual FAQ into plain plugin markup just to get schema, and avoid leaving a real FAQ section as headings-only content with no machine-readable structure.',
			'notes'        => 'Treat visual FAQ design and FAQ schema as two layers of the same feature. Preserve the layout if it works, then add matching JSON-LD or the owning plugin block underneath.',
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
