<?php
/**
 * Front page template.
 *
 * Composition only. Every block of markup comes from the shared section
 * renderers, so the homepage, a shortcode, a block, an Elementor widget and a
 * Divi module all draw exactly the same HTML.
 *
 * @package Bookingly
 */

get_header();

$front_page_id = (int) get_option( 'page_on_front' );

if ( $front_page_id && bookingly_use_page_builder_content( $front_page_id ) ) {
	bookingly_render_builder_content( true );
	get_footer();
	return;
}

bookingly_section( 'hero' );

if ( bookingly_option( 'homepage.show_trust_strip', true ) ) {
	bookingly_section( 'trust_strip' );
}

if ( bookingly_option( 'homepage.show_services', true ) ) {
	bookingly_section( 'services', array( 'anchor' => 'services' ) );
}

if ( bookingly_option( 'homepage.show_how', true ) ) {
	bookingly_section( 'how_it_works', array( 'anchor' => 'how' ) );
}

if ( bookingly_option( 'homepage.show_about', true ) ) {
	bookingly_section( 'about', array( 'anchor' => 'about' ) );
}

if ( bookingly_option( 'homepage.show_testimonials', true ) ) {
	bookingly_section( 'testimonials', array( 'anchor' => 'reviews' ) );
}

if ( bookingly_option( 'homepage.show_cta', true ) ) {
	bookingly_section( 'cta_band' );
}

get_footer();
