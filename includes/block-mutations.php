<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block path lookup and tree mutation helpers.
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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

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

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );

	return array(
		'content' => $content,
		'summary' => mcp_abilities_gutenberg_content_summary( $content ),
		'blocks'  => $mutated,
	);
}
