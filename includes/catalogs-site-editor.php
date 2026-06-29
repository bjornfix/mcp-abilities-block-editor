<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block catalog, theme context, site editor, synced pattern, navigation, and media helpers.
 */
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
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return array(
			'success' => false,
			'message' => 'You are not allowed to edit site editor templates.',
		);
	}

	$content = mcp_abilities_gutenberg_build_editor_safe_content_from_input( $input );
	if ( is_wp_error( $content ) ) {
		return mcp_abilities_gutenberg_error_response( $content );
	}

	$write_guard = mcp_abilities_gutenberg_assert_block_document_write_safe( $content, $input );
	if ( is_wp_error( $write_guard ) ) {
		return mcp_abilities_gutenberg_error_response( $write_guard );
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
		return mcp_abilities_gutenberg_error_response( $result );
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
	$content = mcp_abilities_gutenberg_build_editor_safe_content_from_input( $input );
	if ( is_wp_error( $content ) ) {
		return mcp_abilities_gutenberg_error_response( $content );
	}

	$write_guard = mcp_abilities_gutenberg_assert_block_document_write_safe( $content, $input );
	if ( is_wp_error( $write_guard ) ) {
		return mcp_abilities_gutenberg_error_response( $write_guard );
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
		return mcp_abilities_gutenberg_error_response( $result );
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
		return mcp_abilities_gutenberg_error_response( $path );
	}

	$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
	$post    = null;
	if ( $post_id > 0 ) {
		$post = mcp_abilities_gutenberg_get_editable_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return mcp_abilities_gutenberg_error_response( $post );
		}
		$normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( (string) $post->post_content ) );
	} else {
		$blocks = mcp_abilities_gutenberg_denormalize_blocks( $input['blocks'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return mcp_abilities_gutenberg_error_response( $blocks );
		}
		$normalized = mcp_abilities_gutenberg_normalize_blocks( $blocks );
	}

	$target_block = mcp_abilities_gutenberg_get_block_by_path( $normalized, $path );
	if ( is_wp_error( $target_block ) ) {
		return mcp_abilities_gutenberg_error_response( $target_block );
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
			return mcp_abilities_gutenberg_error_response( $mutated );
		}

		$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
		if ( is_wp_error( $denormalized ) ) {
			return mcp_abilities_gutenberg_error_response( $denormalized );
		}

		$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );
		if ( $post instanceof WP_Post ) {
			$result = mcp_abilities_gutenberg_update_block_document_post(
				$post,
				$content,
				$input,
				array(),
				'Synced pattern extracted and source replaced with pattern reference.',
				false
			);
			if ( is_wp_error( $result ) ) {
				return mcp_abilities_gutenberg_error_response( $result );
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
		'content'      => $replace_source ? mcp_abilities_gutenberg_serialize_blocks_for_editor( mcp_abilities_gutenberg_denormalize_blocks( $mutated ) ) : '',
		'summary'      => $replace_source ? mcp_abilities_gutenberg_content_summary( mcp_abilities_gutenberg_serialize_blocks_for_editor( mcp_abilities_gutenberg_denormalize_blocks( $mutated ) ) ) : array(),
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
		return mcp_abilities_gutenberg_error_response( $post );
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
		return mcp_abilities_gutenberg_error_response( $pattern );
	}

	$normalized = mcp_abilities_gutenberg_normalize_blocks( parse_blocks( (string) $post->post_content ) );
	$parent     = isset( $input['parent_path'] ) ? mcp_abilities_gutenberg_normalize_block_path( $input['parent_path'] ) : array();
	if ( is_wp_error( $parent ) ) {
		return mcp_abilities_gutenberg_error_response( $parent );
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
		return mcp_abilities_gutenberg_error_response( $mutated );
	}

	$denormalized = mcp_abilities_gutenberg_denormalize_blocks( $mutated );
	if ( is_wp_error( $denormalized ) ) {
		return mcp_abilities_gutenberg_error_response( $denormalized );
	}

	$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $denormalized );
	$result = mcp_abilities_gutenberg_update_block_document_post(
		$post,
		$content,
		$input,
		array(),
		'Synced pattern reference inserted successfully.'
	);
	if ( is_wp_error( $result ) ) {
		return mcp_abilities_gutenberg_error_response( $result );
	}

	$result['pattern'] = $pattern;
	$result['blocks']  = $mutated;

	return $result;
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
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return array(
			'success' => false,
			'message' => 'You are not allowed to edit site navigation.',
		);
	}

	$content = mcp_abilities_gutenberg_build_editor_safe_content_from_input( $input );
	if ( is_wp_error( $content ) ) {
		return mcp_abilities_gutenberg_error_response( $content );
	}

	$write_guard = mcp_abilities_gutenberg_assert_block_document_write_safe( $content, $input );
	if ( is_wp_error( $write_guard ) ) {
		return mcp_abilities_gutenberg_error_response( $write_guard );
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
		return mcp_abilities_gutenberg_error_response( $result );
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
