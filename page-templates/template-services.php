<?php
/**
 * Template Name: Bookingly Services
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

$contact_page = bookingly_get_page_by_template( 'page-templates/template-contact.php' );

bookingly_section(
	'page_hero',
	array( 'eyebrow' => __( 'Everything We Book', 'bookingly' ) )
);

bookingly_section(
	'services',
	array(
		'limit'       => (int) bookingly_option( 'services.archive_limit', -1 ),
		'show_head'   => 0,
		'show_filter' => bookingly_option( 'services.show_filters', true ) ? 1 : 0,
		'show_images' => bookingly_option( 'services.show_images', true ) ? 1 : 0,
		'show_prices' => bookingly_option( 'services.show_prices', true ) ? 1 : 0,
		'show_button' => 0,
		'class'       => 'hv-section--flush-top',
	)
);

if ( bookingly_option( 'services.show_archive_cta', true ) ) {
	bookingly_section(
		'cta_band',
		array(
			'eyebrow'      => __( 'Can\'t find a category?', 'bookingly' ),
			'title'        => __( 'Tell us what you need booked and we\'ll find a provider.', 'bookingly' ),
			'button_label' => __( 'Contact Us', 'bookingly' ),
			'button_url'   => $contact_page ? get_permalink( $contact_page ) : '',
		)
	);
}

get_footer();
