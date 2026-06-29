<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg content analysis, design review, copy review, and write-safety guardrails.
 */
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
			'faq_schema_missing_risk',
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
 * Determine whether a class string reads like a stacked/list treatment token.
 *
 * @param string $class_name Raw class string.
 * @param array<int,string> $tokens Tokens to test.
 * @return bool
 */
function mcp_abilities_gutenberg_class_has_layout_token( string $class_name, array $tokens ): bool {
	$class_name = strtolower( trim( $class_name ) );
	if ( '' === $class_name ) {
		return false;
	}

	foreach ( $tokens as $token ) {
		$token = strtolower( trim( $token ) );
		if ( '' === $token ) {
			continue;
		}

		if ( preg_match( '/(?:^|[\s_-])' . preg_quote( $token, '/' ) . '(?:$|[\s_-])/', $class_name ) ) {
			return true;
		}
	}

	return false;
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
 * Check whether CSS declarations look like a compressed proof/token rail treatment.
 *
 * @param string $declarations CSS declarations.
 * @return bool
 */
function mcp_abilities_gutenberg_css_looks_like_compressed_proof_treatment( string $declarations ): bool {
	$properties = mcp_abilities_gutenberg_extract_css_property_values(
		$declarations,
		array( 'display', 'flex-wrap', 'align-items', 'white-space' )
	);

	$display    = strtolower( trim( (string) ( $properties['display'] ?? '' ) ) );
	$flex_wrap  = strtolower( trim( (string) ( $properties['flex-wrap'] ?? '' ) ) );
	$align      = strtolower( trim( (string) ( $properties['align-items'] ?? '' ) ) );
	$whitespace = strtolower( trim( (string) ( $properties['white-space'] ?? '' ) ) );

	if ( in_array( $display, array( 'flex', 'inline-flex' ), true ) ) {
		return true;
	}

	if ( 'nowrap' === $whitespace ) {
		return true;
	}

	if ( '' !== $align && in_array( $align, array( 'baseline', 'center' ), true ) && 'nowrap' === $flex_wrap ) {
		return true;
	}

	return false;
}

/**
 * Check whether a selector likely belongs to proof/support metadata rather than a major layout section.
 *
 * @param string $selector CSS selector.
 * @return bool
 */
function mcp_abilities_gutenberg_selector_suggests_proof_family( string $selector ): bool {
	return 1 === preg_match( '/(proof|meta|pill|chip|badge|tag|assurance|reassurance|guarantee|trust)/i', $selector );
}

/**
 * Detect proof rows that compress substantive copy into a rail/chip presentation.
 *
 * @param string $css CSS to inspect.
 * @param string $html Rendered HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_css_substantive_proof_compression_issues( string $css, string $html, string $source ): array {
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

		if ( ! mcp_abilities_gutenberg_css_looks_like_compressed_proof_treatment( $declarations ) ) {
			continue;
		}

		$selectors = array_map( 'trim', explode( ',', $selector_block ) );
		foreach ( $selectors as $selector ) {
			if ( '' === $selector || ! mcp_abilities_gutenberg_selector_suggests_proof_family( $selector ) ) {
				continue;
			}

			$class_matches = array();
			preg_match_all( '/\.([a-zA-Z0-9_-]+)/', $selector, $class_matches );
			$class_names = array_values( array_unique( array_map( 'strval', $class_matches[1] ?? array() ) ) );
			if ( empty( $class_names ) ) {
				continue;
			}

			$matched_nodes = array();
			foreach ( $class_names as $class_name ) {
				$nodes = $xpath->query(
					sprintf( './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class_name ),
					$root
				);
				if ( ! $nodes instanceof DOMNodeList || 0 === $nodes->length ) {
					continue;
				}

				foreach ( $nodes as $node ) {
					if ( ! $node instanceof DOMElement || mcp_abilities_gutenberg_dom_element_is_interactive( $node ) ) {
						continue;
					}

					$label_nodes = $xpath->query( './/strong|.//b|.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $node );
					$body_nodes  = $xpath->query( './/span|.//small|.//p|.//em', $node );
					if ( ! $label_nodes instanceof DOMNodeList || 0 === $label_nodes->length || ! $body_nodes instanceof DOMNodeList || 0 === $body_nodes->length ) {
						continue;
					}

					$body_words  = 0;
					$total_words = mcp_abilities_gutenberg_count_words( (string) $node->textContent );
					foreach ( $body_nodes as $body_node ) {
						if ( $body_node instanceof DOMNode ) {
							$body_words += mcp_abilities_gutenberg_count_words( (string) $body_node->textContent );
						}
					}

					if ( $body_words < 4 || $total_words < 7 ) {
						continue;
					}

					$matched_nodes[] = $node;
				}
			}

			if ( count( $matched_nodes ) < 2 ) {
				continue;
			}

			$key = md5( $selector . '|' . $declarations );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$examples = array();
			foreach ( array_slice( $matched_nodes, 0, 4 ) as $node ) {
				$examples[] = mcp_abilities_gutenberg_describe_dom_element( $node );
			}

			$issues[] = array(
				'type'      => 'substantive_proof_compression_risk',
				'severity'  => 'notice',
				'source'    => $source,
				'selector'  => mcp_abilities_gutenberg_compact_css_snippet( $selector ),
				'examples'  => array_values( array_unique( $examples ) ),
				'count'     => count( $matched_nodes ),
				'message'   => 'Proof items with real explanatory copy are being compressed into a rail/chip treatment. Once a proof point has both a label and a supporting line, it should read as an open proof list or module, not as squeezed metadata.',
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
		$row_key      = $row_selector . '|' . md5( trim( preg_replace( '/\s+/u', ' ', (string) $row->textContent ) ) );
		if ( isset( $seen[ $row_key ] ) ) {
			continue;
		}

		$children = array();
		$row_class = ' ' . strtolower( trim( (string) $row->getAttribute( 'class' ) ) ) . ' ';
		$is_columns_row = false !== strpos( $row_class, ' wp-block-columns ' );
		$has_stack_token = mcp_abilities_gutenberg_class_has_layout_token( $row_class, array( 'list', 'stack' ) );
		$has_horizontal_token = mcp_abilities_gutenberg_class_has_layout_token( $row_class, array( 'row', 'grid', 'columns', 'items', 'strip', 'process' ) );

		if ( ! $is_columns_row && $has_stack_token && ! $has_horizontal_token ) {
			continue;
		}

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

		$seen[ $row_key ] = true;
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
 * Check whether content already contains FAQPage schema.
 *
 * @param string $content Raw content.
 * @return bool
 */
function mcp_abilities_gutenberg_content_has_faq_schema( string $content ): bool {
	return 1 === preg_match( '/"@type"\s*:\s*"FAQPage"|"@type":"FAQPage"/', $content );
}

/**
 * Detect visually structured FAQ sections that are missing matching FAQ schema.
 *
 * @param string $content Raw content.
 * @param string $html Rendered HTML.
 * @param string $source Source label.
 * @return array<int,array<string,mixed>>
 */
function mcp_abilities_gutenberg_collect_rendered_faq_schema_issues( string $content, string $html, string $source ): array {
	if ( '' === trim( $html ) || mcp_abilities_gutenberg_content_has_faq_schema( $content ) ) {
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

	$headings = $xpath->query( './/h2|.//h3|.//h4|.//h5|.//h6', $root );
	if ( ! $headings instanceof DOMNodeList || 0 === $headings->length ) {
		return array();
	}

	$faq_pairs = array();
	foreach ( $headings as $heading ) {
		if ( ! $heading instanceof DOMElement ) {
			continue;
		}

		$question = trim( preg_replace( '/\s+/u', ' ', (string) $heading->textContent ) );
		if ( '' === $question || ! preg_match( '/\?\s*$/u', $question ) ) {
			continue;
		}

		$container = $heading->parentNode instanceof DOMElement ? $heading->parentNode : null;
		if ( ! $container instanceof DOMElement ) {
			continue;
		}

		$answer_nodes = $xpath->query( './/p[normalize-space()]', $container );
		if ( ! $answer_nodes instanceof DOMNodeList || 0 === $answer_nodes->length ) {
			continue;
		}

		$answer_text = '';
		foreach ( $answer_nodes as $answer_node ) {
			if ( ! $answer_node instanceof DOMElement ) {
				continue;
			}

			$answer_text = trim( preg_replace( '/\s+/u', ' ', (string) $answer_node->textContent ) );
			if ( '' !== $answer_text ) {
				break;
			}
		}

		if ( mcp_abilities_gutenberg_count_words( $answer_text ) < 6 ) {
			continue;
		}

		$faq_pairs[] = array(
			'question' => $question,
			'selector' => mcp_abilities_gutenberg_describe_dom_element( $container ),
		);
	}

	if ( count( $faq_pairs ) < 2 ) {
		return array();
	}

	return array(
		array(
			'type'      => 'faq_schema_missing_risk',
			'severity'  => 'warning',
			'source'    => $source,
			'count'     => count( $faq_pairs ),
			'selectors' => array_values( array_unique( array_slice( array_map( 'strval', wp_list_pluck( $faq_pairs, 'selector' ) ), 0, 6 ) ) ),
			'questions' => array_values( array_slice( array_map( 'strval', wp_list_pluck( $faq_pairs, 'question' ) ), 0, 4 ) ),
			'message'   => 'The page visually presents a real FAQ, but it does not include matching FAQ schema. Keep the design if it works, but add machine-readable FAQ structure underneath it.',
		),
	);
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
	$alignfull_neutralization_snippets = array();
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
			$alignfull_neutralization_snippets = array_merge(
				$alignfull_neutralization_snippets,
				mcp_abilities_gutenberg_detect_alignfull_neutralization_css_snippets( $css )
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
	$alignfull_neutralization_snippets = array_values( array_unique( $alignfull_neutralization_snippets ) );
	$alignfull_nodes = $xpath->query(
		'.//*[contains(concat(" ", normalize-space(@class), " "), " alignfull ")]',
		$root
	);
	$alignfull_count = $alignfull_nodes instanceof DOMNodeList ? $alignfull_nodes->length : 0;
	$has_custom_width_system = ! empty( $content_measures ) || ! empty( $full_width_shell_css_snippets );
	if ( $alignfull_count > 0 && $has_custom_width_system && empty( $alignfull_neutralization_snippets ) ) {
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
			'snippets'  => array_slice( array_merge( $full_width_shell_css_snippets, array_map( static function ( array $entry ): string { return (string) ( $entry['value'] ?? '' ); }, array_values( $content_measures ) ) ), 0, 4 ),
			'message'   => 'Content mixes alignfull Gutenberg blocks with custom page-width CSS but does not explicitly neutralize alignfull breakout margins. That can create browser-dependent horizontal scrolling even when the page mostly looks correct. Prefer neutralized alignfull margins or non-breakout full-width wrappers.',
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
 * Detect CSS that explicitly neutralizes Gutenberg alignfull breakout margins.
 *
 * @param string $css CSS to inspect.
 * @return array<int,string>
 */
function mcp_abilities_gutenberg_detect_alignfull_neutralization_css_snippets( string $css ): array {
	$snippets = array();
	$patterns = array(
		'/\.alignfull[^{]*\{[^}]*margin-left\s*:\s*0[^}]*margin-right\s*:\s*0[^}]*\}/i',
		'/\.alignfull[^{]*\{[^}]*width\s*:\s*100%[^}]*max-width\s*:\s*none[^}]*\}/i',
		'/\.alignfull[^{]*\{[^}]*max-width\s*:\s*none[^}]*width\s*:\s*100%[^}]*\}/i',
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
 * Detect builder/design markup that should not disappear during broad writes.
 *
 * @param string $content Post content.
 * @return string[]
 */
function mcp_abilities_gutenberg_detect_design_markup_markers( string $content ): array {
	$markers = array();

	if ( false !== strpos( $content, '<!-- wp:generateblocks/' ) || false !== strpos( $content, 'gb-container-' ) || false !== strpos( $content, 'gb-grid-wrapper-' ) || false !== strpos( $content, 'gb-headline-' ) || false !== strpos( $content, 'gb-button-' ) ) {
		$markers[] = 'generateblocks';
	}

	if ( preg_match( '/\bdv-page-\d+[-_a-z0-9]*\b/i', $content ) ) {
		$markers[] = 'devenia-design-classes';
	}

	return array_values( array_unique( $markers ) );
}

/**
 * Block accidental replacement of existing designed content with plain blocks.
 *
 * @param string $old_content Existing post content.
 * @param string $new_content Proposed post content.
 * @param array  $input       Ability input.
 * @return true|WP_Error
 */
function mcp_abilities_gutenberg_assert_design_markup_preserved( string $old_content, string $new_content, array $input ) {
	if ( ! empty( $input['allow_design_markup_loss'] ) ) {
		return true;
	}

	$old_markers = mcp_abilities_gutenberg_detect_design_markup_markers( $old_content );
	if ( empty( $old_markers ) ) {
		return true;
	}

	$new_markers = mcp_abilities_gutenberg_detect_design_markup_markers( $new_content );
	$lost        = array_values( array_diff( $old_markers, $new_markers ) );
	if ( empty( $lost ) ) {
		return true;
	}

	return new WP_Error(
		'mcp_gutenberg_design_markup_loss_blocked',
		'Blocked save because it would remove existing design markup (' . implode( ', ', $lost ) . '). Use a targeted block mutation or pass allow_design_markup_loss=true only when intentionally replacing the page design.'
	);
}

/**
 * Build editor-safe Gutenberg content from an ability input.
 *
 * @param array<string,mixed> $input Input data.
 * @return string|WP_Error
 */
function mcp_abilities_gutenberg_build_editor_safe_content_from_input( array $input ) {
	$content = null;
	if ( isset( $input['content'] ) && is_string( $input['content'] ) ) {
		$raw_guard = mcp_abilities_gutenberg_assert_valid_gutenberg_content( $input['content'] );
		if ( is_wp_error( $raw_guard ) ) {
			return $raw_guard;
		}
		$content = $input['content'];
	} elseif ( array_key_exists( 'blocks', $input ) ) {
		$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}

		$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $blocks );
	}

	if ( null === $content ) {
		return new WP_Error( 'mcp_gutenberg_missing_content', 'Provide either content or blocks.' );
	}

	return mcp_abilities_gutenberg_prepare_content_for_editor_save( $content );
}

/**
 * Validate a prepared Gutenberg write before persistence.
 *
 * @param string              $content     Prepared Gutenberg content.
 * @param array<string,mixed> $input       Ability input.
 * @param string|null         $old_content Existing content when replacing/updating.
 * @return true|WP_Error
 */
function mcp_abilities_gutenberg_assert_block_document_write_safe( string $content, array $input, ?string $old_content = null ) {
	$validity_guard = mcp_abilities_gutenberg_assert_valid_gutenberg_content( $content );
	if ( is_wp_error( $validity_guard ) ) {
		return $validity_guard;
	}

	$layout_guard = mcp_abilities_gutenberg_assert_layout_safe_for_write( $content );
	if ( is_wp_error( $layout_guard ) ) {
		return $layout_guard;
	}

	if ( null !== $old_content ) {
		$design_guard = mcp_abilities_gutenberg_assert_design_markup_preserved( $old_content, $content, $input );
		if ( is_wp_error( $design_guard ) ) {
			return $design_guard;
		}
	}

	return true;
}

/**
 * Return the standard post payload after a block-document write.
 *
 * @param WP_Post $post Post object after persistence.
 * @return array<string,mixed>
 */
function mcp_abilities_gutenberg_block_document_post_payload( WP_Post $post ): array {
	return array(
		'id'       => (int) $post->ID,
		'type'     => (string) $post->post_type,
		'status'   => (string) $post->post_status,
		'slug'     => (string) $post->post_name,
		'title'    => get_the_title( $post ),
		'url'      => get_permalink( $post ),
		'modified' => (string) $post->post_modified_gmt,
	);
}

/**
 * Update an existing post with prepared Gutenberg content.
 *
 * @param WP_Post             $post        Existing post.
 * @param string              $content     Prepared Gutenberg content.
 * @param array<string,mixed> $input       Ability input.
 * @param array<string,mixed> $update_args Additional `wp_update_post()` args.
 * @param string              $message     Success message.
 * @param bool                $preserve_design_markup Whether to block design-markup loss.
 * @return array<string,mixed>|WP_Error
 */
function mcp_abilities_gutenberg_update_block_document_post(
	WP_Post $post,
	string $content,
	array $input = array(),
	array $update_args = array(),
	string $message = 'Post updated successfully.',
	bool $preserve_design_markup = true
) {
	$write_guard = mcp_abilities_gutenberg_assert_block_document_write_safe( $content, $input, $preserve_design_markup ? (string) $post->post_content : null );
	if ( is_wp_error( $write_guard ) ) {
		return $write_guard;
	}

	$update_args = array_merge(
		$update_args,
		array(
			'ID'           => (int) $post->ID,
			'post_content' => $content,
		)
	);

	$result = wp_update_post( wp_slash( $update_args ), true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$updated_post    = get_post( (int) $post->ID );
	$updated_content = $updated_post ? (string) $updated_post->post_content : $content;

	return array(
		'success' => true,
		'message' => $message,
		'post'    => $updated_post ? mcp_abilities_gutenberg_block_document_post_payload( $updated_post ) : mcp_abilities_gutenberg_block_document_post_payload( $post ),
		'content' => $updated_content,
		'summary' => mcp_abilities_gutenberg_content_summary( $updated_content ),
		'blocks'  => mcp_abilities_gutenberg_normalize_blocks( parse_blocks( $updated_content ) ),
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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

			if ( 'core/button' === $name ) {
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
		mcp_abilities_gutenberg_collect_rendered_boxed_module_issues( $rendered_html, 'rendered-html' ),
		mcp_abilities_gutenberg_collect_rendered_faq_schema_issues( $content, $rendered_html, 'rendered-html' )
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
		} elseif ( 'faq_schema_missing_risk' === $type ) {
			$score -= 14;
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
		$recommendations[] = 'When short reassurance statements live inside a support card, let them stack calmly and use the available card width. Do not force them into cramped mini-rows or treat them like compressed footer metadata.';
	}
	if ( in_array( 'followup_cluster_detachment_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'Keep follow-up proof rows, metadata strips, and support clusters visually attached to the CTA or copy they belong to. If the gap gets too loose, the cluster stops feeling like part of the same selling moment.';
	}
	if ( in_array( 'faq_schema_missing_risk', $signals['issue_types'], true ) ) {
		$recommendations[] = 'If the page clearly behaves like an FAQ, do not stop at the visual layout. Preserve the design if it works, but add matching FAQ schema underneath it so the content is machine-readable too.';
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
		$recommendations[] = 'If a card ends with short parallel proof statements, prefer a calm labeled micro-proof stack over decorative icons, chip rails, or fake CTA markers.';
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
					'Inside a support card, turn short parallel proof statements into a simple stacked micro-list and let each support line use the full card width instead of squeezing it into a mini-grid.',
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
		} elseif ( 'faq_schema_missing_risk' === $type ) {
			$suggestions[] = array(
				'type'      => $type,
				'selectors' => array_values( array_map( 'strval', is_array( $issue['selectors'] ?? null ) ? $issue['selectors'] : array() ) ),
				'problem'   => 'The page visually presents a real FAQ, but there is no matching FAQ schema underneath it.',
				'fixes'     => array(
					'Keep the current FAQ design if it is working visually; do not flatten it just to chase schema.',
					'Add matching `FAQPage` JSON-LD or the owning plugin block underneath the visual FAQ so the questions and answers are machine-readable too.',
					'Make sure the schema questions and answers match the visible FAQ copy exactly instead of drifting into a second version.',
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
					'When these proof statements live inside a support card, use a small label plus calm stacked rows instead of decorative markers or loose bold line breaks.',
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
