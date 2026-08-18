<?php
/**
 * Bookingly functions and definitions.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BOOKINGLY_VERSION', '2.0.0' );
define( 'BOOKINGLY_DIR', get_template_directory() );
define( 'BOOKINGLY_URI', get_template_directory_uri() );

/**
 * Content width used by core for oEmbeds and wide images.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 1180;
}

require_once BOOKINGLY_DIR . '/inc/legacy-migration.php';
require_once BOOKINGLY_DIR . '/inc/defaults.php';
require_once BOOKINGLY_DIR . '/inc/helpers.php';
require_once BOOKINGLY_DIR . '/inc/icons.php';
require_once BOOKINGLY_DIR . '/inc/class-bookingly-email-delivery.php';
require_once BOOKINGLY_DIR . '/inc/class-bookingly-seo.php';
require_once BOOKINGLY_DIR . '/inc/class-bookingly-seo-meta.php';
require_once BOOKINGLY_DIR . '/inc/setup.php';
require_once BOOKINGLY_DIR . '/inc/customizer.php';
require_once BOOKINGLY_DIR . '/inc/page-builders.php';
require_once BOOKINGLY_DIR . '/inc/sections.php';
require_once BOOKINGLY_DIR . '/inc/section-renderers.php';
require_once BOOKINGLY_DIR . '/inc/class-bookingly-theme-options.php';
require_once BOOKINGLY_DIR . '/inc/class-bookingly-setup-wizard.php';
require_once BOOKINGLY_DIR . '/inc/woocommerce.php';
require_once BOOKINGLY_DIR . '/inc/easy-shuttle.php';

Bookingly_Seo::init();
Bookingly_Seo_Meta::init();

/*
 * Page-builder bridges.
 *
 * Shortcodes and blocks are always available; the Elementor and Divi adapters
 * cost nothing until those builders are actually loaded.
 */
require_once BOOKINGLY_DIR . '/inc/builders/shortcodes.php';
require_once BOOKINGLY_DIR . '/inc/builders/blocks.php';
require_once BOOKINGLY_DIR . '/inc/patterns.php';
require_once BOOKINGLY_DIR . '/inc/builders/build-mode.php';
require_once BOOKINGLY_DIR . '/inc/builders/elementor-templates.php';

if ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) ) {
	require_once BOOKINGLY_DIR . '/inc/builders/elementor.php';
}

if ( class_exists( 'ET_Builder_Module' ) || defined( 'ET_BUILDER_VERSION' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
	require_once BOOKINGLY_DIR . '/inc/builders/divi.php';
}

/**
 * Load the Elementor bridge if Elementor boots after the theme.
 */
function bookingly_maybe_load_elementor_bridge() {
	if ( ! function_exists( 'bookingly_elementor_widget_map' ) ) {
		require_once BOOKINGLY_DIR . '/inc/builders/elementor.php';
	}
}
add_action( 'elementor/loaded', 'bookingly_maybe_load_elementor_bridge' );

/**
 * Load the Divi bridge if the builder boots after the theme.
 */
function bookingly_maybe_load_divi_bridge() {
	if ( ! function_exists( 'bookingly_divi_module_map' ) ) {
		require_once BOOKINGLY_DIR . '/inc/builders/divi.php';
	}
}
add_action( 'et_builder_framework_loaded', 'bookingly_maybe_load_divi_bridge' );
