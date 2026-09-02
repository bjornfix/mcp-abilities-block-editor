<?php
/**
 * Plugin Name: MCP Abilities - Block Editor
 * Plugin URI: https://devenia.com/plugins/mcp-abilities-block-editor/
 * Description: WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.
 * Version: 0.20.31
 * Author: basicus
 * Author URI: https://profiles.wordpress.org/basicus/
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
				echo '<div class="notice notice-error"><p><strong>MCP Abilities - Block Editor</strong> requires the <a href="https://developer.wordpress.org/apis/abilities-api/">WordPress Abilities API</a> in WordPress 6.9 or newer.</p></div>';
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
 * Permission callback for post/page write abilities.
 *
 * @param array<string,mixed> $input Ability input.
 */
function mcp_abilities_gutenberg_content_write_permission_callback( $input = array() ): bool {
	$input   = is_array( $input ) ? $input : array();
	$post_id = absint( $input['post_id'] ?? 0 );
	$post    = $post_id ? get_post( $post_id ) : null;
	$type    = $post instanceof WP_Post ? (string) $post->post_type : 'page';
	$object  = get_post_type_object( $type );
	$edit_cap = $post instanceof WP_Post
		? current_user_can( 'edit_post', $post_id )
		: current_user_can( $object && ! empty( $object->cap->create_posts ) ? (string) $object->cap->create_posts : 'edit_pages' );
	if ( ! $edit_cap ) {
		return false;
	}

	$status = sanitize_key( (string) ( $input['status'] ?? ( $post instanceof WP_Post ? $post->post_status : 'draft' ) ) );
	if ( ! in_array( $status, array( 'publish', 'future', 'private' ), true ) ) {
		return true;
	}

	return current_user_can( $object && ! empty( $object->cap->publish_posts ) ? (string) $object->cap->publish_posts : 'publish_pages' );
}

/**
 * Permission callback for site-editor write abilities.
 */
function mcp_abilities_gutenberg_site_editor_permission_callback(): bool {
	return current_user_can( 'edit_theme_options' );
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
	if ( ! isset( $args['category'] ) || ! is_string( $args['category'] ) || '' === $args['category'] ) {
		$args['category'] = 'block-editor';
	}
	if (
		isset( $args['input_schema'] )
		&& is_array( $args['input_schema'] )
		&& isset( $args['input_schema']['type'] )
		&& 'object' === $args['input_schema']['type']
		&& empty( $args['input_schema']['properties'] )
	) {
		$args['input_schema']['type'] = array( 'object', 'array', 'null' );
	}

	if ( isset( $args['output_schema']['properties'] ) && is_array( $args['output_schema']['properties'] ) ) {
		$args['output_schema']['properties'] = array_merge(
			array(
				'success' => array( 'type' => 'boolean' ),
				'message' => array( 'type' => 'string' ),
				'code'    => array( 'type' => 'string' ),
				'issues'  => array(
					'type'  => 'array',
					'items' => array( 'type' => array( 'object', 'string' ) ),
				),
				'data'    => array( 'type' => array( 'object', 'array', 'string', 'number', 'integer', 'boolean' ) ),
			),
			$args['output_schema']['properties']
		);
	}

	if ( doing_action( 'wp_abilities_api_init' ) ) {
		wp_register_ability( $name, $args );
		return;
	}

	$registry = class_exists( 'WP_Abilities_Registry' ) ? WP_Abilities_Registry::get_instance() : null;
	if ( $registry && ! $registry->is_registered( $name ) ) {
		$registry->register( $name, $args );
	}
}

require_once __DIR__ . '/includes/core-blocks.php';
require_once __DIR__ . '/includes/catalogs-site-editor.php';
require_once __DIR__ . '/includes/content-analysis.php';
require_once __DIR__ . '/includes/block-mutations.php';
require_once __DIR__ . '/includes/generation.php';
require_once __DIR__ . '/includes/abilities.php';

add_action( 'wp_abilities_api_categories_init', 'mcp_abilities_gutenberg_register_category' );
add_action( 'wp_abilities_api_init', 'mcp_abilities_gutenberg_register_abilities' );

if ( did_action( 'init' ) ) {
	mcp_abilities_gutenberg_register_category();
	mcp_abilities_gutenberg_register_abilities();
}
