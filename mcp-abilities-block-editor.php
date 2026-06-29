<?php
/**
 * Plugin Name: MCP Abilities - Block Editor
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-block-editor
 * Description: WordPress block-editor abilities for MCP. Parse, validate, inspect, generate, and update Gutenberg content safely.
 * Version: 0.20.14
 * Author: basicus
 * Author URI: https://profiles.wordpress.org/basicus/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 7.0
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
