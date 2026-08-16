<?php
/**
 * Elementor surface for Bookingly sections.
 *
 * Widgets are thin adapters. They translate the section schema into Elementor
 * controls and hand the values straight back to the shared render callback —
 * the widget never contains markup of its own. Drop a widget into any Elementor
 * container and it inherits that container's width, exactly like a native one.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Bookingly widget category.
 *
 * @param \Elementor\Elements_Manager $elements_manager Elementor manager.
 */
function bookingly_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'bookingly',
		array(
			'title' => __( 'Bookingly', 'bookingly' ),
			'icon'  => 'eicon-theme-style',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'bookingly_elementor_category' );

/**
 * Load widget classes and register one per section.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
 */
function bookingly_register_elementor_widgets( $widgets_manager ) {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	require_once BOOKINGLY_DIR . '/inc/builders/class-bookingly-elementor-widget.php';

	foreach ( bookingly_elementor_widget_map() as $section_id => $class ) {
		if ( ! bookingly_get_section( $section_id ) || ! class_exists( $class ) ) {
			continue;
		}

		$widgets_manager->register( new $class() );
	}
}
add_action( 'elementor/widgets/register', 'bookingly_register_elementor_widgets' );

/**
 * Section id => widget class name.
 *
 * Elementor rebuilds a widget from its class during render, so each widget type
 * needs a real, distinct class. These subclasses hold nothing but their id.
 *
 * @return array<string,string>
 */
function bookingly_elementor_widget_map() {
	return array(
		'hero'           => 'Bookingly_Elementor_Hero',
		'trust_strip'    => 'Bookingly_Elementor_Trust_Strip',
		'services'       => 'Bookingly_Elementor_Services',
		'how_it_works'   => 'Bookingly_Elementor_How_It_Works',
		'about'          => 'Bookingly_Elementor_About',
		'testimonials'   => 'Bookingly_Elementor_Testimonials',
		'cta_band'       => 'Bookingly_Elementor_Cta_Band',
		'stats'          => 'Bookingly_Elementor_Stats',
		'value_props'    => 'Bookingly_Elementor_Value_Props',
		'team'           => 'Bookingly_Elementor_Team',
		'story'          => 'Bookingly_Elementor_Story',
		'page_hero'      => 'Bookingly_Elementor_Page_Hero',
		'contact_cards'  => 'Bookingly_Elementor_Contact_Cards',
		'contact_form'   => 'Bookingly_Elementor_Contact_Form',
		'business_hours' => 'Bookingly_Elementor_Business_Hours',
		'map'            => 'Bookingly_Elementor_Map',
		'blog_posts'     => 'Bookingly_Elementor_Blog_Posts',
		'newsletter'     => 'Bookingly_Elementor_Newsletter',
	);
}

/**
 * Make the theme stylesheet available inside the Elementor editor canvas.
 */
function bookingly_elementor_editor_styles() {
	wp_enqueue_style(
		'bookingly-main',
		BOOKINGLY_URI . '/assets/css/main.css',
		array(),
		BOOKINGLY_VERSION
	);
}
add_action( 'elementor/editor/after_enqueue_styles', 'bookingly_elementor_editor_styles' );
add_action( 'elementor/preview/enqueue_styles', 'bookingly_elementor_editor_styles' );
