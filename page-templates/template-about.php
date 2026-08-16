<?php
/**
 * Template Name: Bookingly About
 * Template Post Type: page
 *
 * @package Bookingly
 */

get_header();

if ( bookingly_use_page_builder_content() ) {
	bookingly_render_builder_content( true );
	get_footer();
	return;
}

$services_page = bookingly_get_page_by_template( 'page-templates/template-services.php' );

bookingly_section(
	'page_hero',
	array( 'eyebrow' => __( 'Our Story', 'bookingly' ) )
);

// Uses the page's own content when it has some, otherwise the Theme Options text.
bookingly_section( 'story', array( 'class' => 'hv-section--flush-top' ) );

bookingly_section( 'value_props' );

if ( bookingly_option( 'about.show_stats', true ) ) {
	bookingly_section( 'stats' );
}

if ( bookingly_option( 'about.show_team', true ) ) {
	bookingly_section( 'team' );
}

bookingly_section(
	'cta_band',
	array(
		'button_url' => $services_page ? get_permalink( $services_page ) : '',
	)
);

get_footer();
