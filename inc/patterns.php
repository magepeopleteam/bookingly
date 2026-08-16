<?php
/**
 * Block patterns for the Bookingly page layouts.
 *
 * The theme templates render their sections in PHP, which means editing the
 * Home page shows an empty canvas — there is nothing in post_content to edit.
 * These patterns give an editor the same layout as real, movable blocks, so a
 * site can switch from "theme renders it" to "I lay it out myself" without
 * losing the design or hand-writing block markup.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Bookingly pattern category.
 */
function bookingly_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'bookingly',
		array( 'label' => __( 'Bookingly', 'bookingly' ) )
	);
}
add_action( 'init', 'bookingly_register_pattern_category', 9 );

/**
 * Turn a list of section ids into block markup.
 *
 * Attributes are deliberately omitted: every Bookingly block falls back to the
 * values in Theme Options, so a freshly inserted pattern reproduces exactly
 * what the PHP template was rendering.
 *
 * @param string[] $sections Section ids.
 * @return string
 */
function bookingly_pattern_markup( $sections ) {
	$markup = '';

	foreach ( $sections as $section ) {
		$block   = 'bookingly/' . str_replace( '_', '-', $section );
		$markup .= '<!-- wp:' . $block . ' /-->' . "\n\n";
	}

	return trim( $markup );
}

/**
 * Register one pattern per page layout.
 */
function bookingly_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$patterns = array(
		'homepage' => array(
			'title'       => __( 'Bookingly — Full homepage', 'bookingly' ),
			'description' => __( 'The complete Bookingly homepage: hero, trust strip, services, how it works, about, testimonials and a call to action.', 'bookingly' ),
			'sections'    => array( 'hero', 'trust_strip', 'services', 'how_it_works', 'about', 'testimonials', 'cta_band' ),
		),
		'about'    => array(
			'title'       => __( 'Bookingly — About page', 'bookingly' ),
			'description' => __( 'Page hero, story, value cards, statistics, team and a call to action.', 'bookingly' ),
			'sections'    => array( 'page_hero', 'story', 'value_props', 'stats', 'team', 'cta_band' ),
		),
		'services' => array(
			'title'       => __( 'Bookingly — Services page', 'bookingly' ),
			'description' => __( 'Page hero, the filterable services grid, and a call to action.', 'bookingly' ),
			'sections'    => array( 'page_hero', 'services', 'cta_band' ),
		),
		'contact'  => array(
			'title'       => __( 'Bookingly — Contact page', 'bookingly' ),
			'description' => __( 'Page hero, contact cards, enquiry form, map and opening hours.', 'bookingly' ),
			'sections'    => array( 'page_hero', 'contact_cards', 'contact_form', 'map', 'business_hours' ),
		),
		'blog'     => array(
			'title'       => __( 'Bookingly — Blog landing', 'bookingly' ),
			'description' => __( 'Page hero, a grid of recent posts, and the follow band.', 'bookingly' ),
			'sections'    => array( 'page_hero', 'blog_posts', 'newsletter' ),
		),
	);

	foreach ( $patterns as $slug => $pattern ) {
		register_block_pattern(
			'bookingly/' . $slug,
			array(
				'title'       => $pattern['title'],
				'description' => $pattern['description'],
				'categories'  => array( 'bookingly' ),
				'keywords'    => array( 'bookingly', 'booking', 'service' ),
				'content'     => bookingly_pattern_markup( $pattern['sections'] ),
			)
		);
	}
}
add_action( 'init', 'bookingly_register_block_patterns', 30 );
