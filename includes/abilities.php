<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register block-editor MCP abilities.
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
					return mcp_abilities_gutenberg_error_response( $details );
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
					return mcp_abilities_gutenberg_error_response( $styles );
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
					return mcp_abilities_gutenberg_error_response( $pattern );
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
					return mcp_abilities_gutenberg_error_response( $pattern );
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
				if ( is_wp_error( $payload ) ) {
					return mcp_abilities_gutenberg_error_response( $payload );
				}

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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
						return mcp_abilities_gutenberg_error_response( $post );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $blocks );
				}

				$content = mcp_abilities_gutenberg_serialize_blocks_for_editor( $blocks );

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
					return mcp_abilities_gutenberg_error_response( $post );
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
					return mcp_abilities_gutenberg_error_response( $template );
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
					return mcp_abilities_gutenberg_error_response( $part );
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
					return mcp_abilities_gutenberg_error_response( $navigation );
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
			'permission_callback' => 'mcp_abilities_gutenberg_site_editor_permission_callback',
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
					return mcp_abilities_gutenberg_error_response( $usage );
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
					return mcp_abilities_gutenberg_error_response( $usage );
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
					return mcp_abilities_gutenberg_error_response( $usage );
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
					'allow_design_markup_loss' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Allow upsert to replace an existing page even when GenerateBlocks/design markup would be removed. Defaults to false.',
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
					'allow_design_markup_loss' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Allow upsert to replace an existing page even when GenerateBlocks/design markup would be removed. Defaults to false.',
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
				if ( is_wp_error( $payload ) ) {
					return mcp_abilities_gutenberg_error_response( $payload );
				}

				$create  = mcp_abilities_gutenberg_create_page_from_input(
					array(
						'title'                => $payload['title'],
						'slug'                 => $payload['slug'],
						'status'               => isset( $input['status'] ) ? (string) $input['status'] : 'draft',
						'content'              => $payload['content'],
						'upsert_matching_slug' => ! empty( $input['upsert_matching_slug'] ),
						'allow_design_markup_loss' => ! empty( $input['allow_design_markup_loss'] ),
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
					'allow_design_markup_loss' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Allow replace mode to remove existing GenerateBlocks/design markup. Defaults to false.',
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					$response   = mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					return mcp_abilities_gutenberg_error_response( $result );
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
					'allow_design_markup_loss' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Allow replacing content even when existing GenerateBlocks/design markup would be removed. Defaults to false.',
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
					return mcp_abilities_gutenberg_error_response( $post );
				}

				$content = mcp_abilities_gutenberg_build_editor_safe_content_from_input( is_array( $input ) ? $input : array() );
				if ( is_wp_error( $content ) ) {
					return mcp_abilities_gutenberg_error_response( $content );
				}

				$update_args = array(
				);

				if ( isset( $input['title'] ) && is_string( $input['title'] ) ) {
					$update_args['post_title'] = $input['title'];
				}

				if ( isset( $input['status'] ) && is_string( $input['status'] ) ) {
					$update_args['post_status'] = $input['status'];
				}

				$result = mcp_abilities_gutenberg_update_block_document_post(
					$post,
					$content,
					is_array( $input ) ? $input : array(),
					$update_args,
					'Post updated successfully.'
				);
				if ( is_wp_error( $result ) ) {
					return mcp_abilities_gutenberg_error_response( $result );
				}

				return $result;
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
