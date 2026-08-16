<?php
/**
 * Divi Builder surface for Bookingly sections.
 *
 * Same adapter pattern as the Elementor bridge: Divi fields are generated from
 * the section schema and the values are passed to the shared render callback.
 * Loaded only when Divi Builder is actually present.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Divi Builder is available in this request.
 *
 * @return bool
 */
function bookingly_has_divi() {
	return class_exists( 'ET_Builder_Module' ) || defined( 'ET_BUILDER_VERSION' );
}

/**
 * Load and instantiate the Divi modules once the builder is ready.
 */
function bookingly_register_divi_modules() {
	if ( ! class_exists( 'ET_Builder_Module' ) ) {
		return;
	}

	require_once BOOKINGLY_DIR . '/inc/builders/class-bookingly-divi-module.php';

	foreach ( bookingly_divi_module_map() as $section_id => $class ) {
		if ( ! bookingly_get_section( $section_id ) || ! class_exists( $class ) ) {
			continue;
		}

		// Divi registers a module by instantiating it.
		new $class();
	}
}
add_action( 'et_builder_ready', 'bookingly_register_divi_modules' );

/**
 * Section id => Divi module class name.
 *
 * @return array<string,string>
 */
function bookingly_divi_module_map() {
	return array(
		'hero'           => 'Bookingly_Divi_Hero',
		'trust_strip'    => 'Bookingly_Divi_Trust_Strip',
		'services'       => 'Bookingly_Divi_Services',
		'how_it_works'   => 'Bookingly_Divi_How_It_Works',
		'about'          => 'Bookingly_Divi_About',
		'testimonials'   => 'Bookingly_Divi_Testimonials',
		'cta_band'       => 'Bookingly_Divi_Cta_Band',
		'stats'          => 'Bookingly_Divi_Stats',
		'value_props'    => 'Bookingly_Divi_Value_Props',
		'team'           => 'Bookingly_Divi_Team',
		'story'          => 'Bookingly_Divi_Story',
		'page_hero'      => 'Bookingly_Divi_Page_Hero',
		'contact_cards'  => 'Bookingly_Divi_Contact_Cards',
		'contact_form'   => 'Bookingly_Divi_Contact_Form',
		'business_hours' => 'Bookingly_Divi_Business_Hours',
		'map'            => 'Bookingly_Divi_Map',
		'blog_posts'     => 'Bookingly_Divi_Blog_Posts',
		'newsletter'     => 'Bookingly_Divi_Newsletter',
	);
}

/**
 * Make the theme stylesheet available inside the Divi visual builder.
 */
function bookingly_divi_builder_styles() {
	if ( ! bookingly_has_divi() ) {
		return;
	}

	wp_enqueue_style(
		'bookingly-main',
		BOOKINGLY_URI . '/assets/css/main.css',
		array(),
		BOOKINGLY_VERSION
	);
}
add_action( 'et_fb_enqueue_assets', 'bookingly_divi_builder_styles' );
