<?php
/**
 * Shortcode surface for Bookingly sections.
 *
 * Registered for every section in the registry. Shortcodes are the lowest
 * common denominator: they work in the block editor's shortcode block, Divi
 * text/code modules, WPBakery, Beaver Builder, widgets and raw post content,
 * so a section is reachable even from a builder Bookingly has no adapter for.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register [bookingly_*] shortcodes from the section registry.
 */
function bookingly_register_section_shortcodes() {
	foreach ( array_keys( bookingly_sections() ) as $id ) {
		add_shortcode( 'bookingly_' . $id, 'bookingly_shortcode_dispatch' );
	}
}
add_action( 'init', 'bookingly_register_section_shortcodes' );

/**
 * Render a section shortcode.
 *
 * @param array<string,mixed>|string $atts    Shortcode attributes.
 * @param string|null                $content Enclosed content (unused).
 * @param string                     $tag     Shortcode tag.
 * @return string
 */
function bookingly_shortcode_dispatch( $atts, $content = null, $tag = '' ) {
	unset( $content );

	$id      = preg_replace( '/^bookingly_/', '', $tag );
	$section = bookingly_get_section( $id );

	if ( ! $section ) {
		return '';
	}

	$atts = is_array( $atts ) ? $atts : array();

	// Only keep keys the section actually declares, plus wrapper controls.
	$allowed = array_merge( array_keys( $section['fields'] ), array( 'class', 'anchor' ) );
	$clean   = array();

	foreach ( $allowed as $key ) {
		if ( isset( $atts[ $key ] ) ) {
			$clean[ $key ] = $atts[ $key ];
		}
	}

	return bookingly_get_section_html( $id, $clean );
}

/**
 * Expose the shortcode list to editors as a reference table.
 *
 * @return array<string,string> Tag => label.
 */
function bookingly_get_shortcode_reference() {
	$out = array();

	foreach ( bookingly_sections() as $id => $section ) {
		$out[ '[bookingly_' . $id . ']' ] = $section['label'];
	}

	return $out;
}
