<?php
/**
 * Easy Shuttle integration.
 *
 * @package Bookingly
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes that render the shuttle booking UI rather than prose.
 *
 * @return string[]
 */
function bookingly_easy_shuttle_shortcodes() {
	/**
	 * Filters the shuttle shortcodes treated as application UI.
	 *
	 * @param string[] $shortcodes Shortcode tags.
	 */
	return (array) apply_filters(
		'bookingly_easy_shuttle_shortcodes',
		array( 'mpsb_shuttle_search', 'mpsb_shuttle_list', 'mpsb_my_bookings', 'mpsb_find_booking' )
	);
}

/**
 * Is this page an Easy Shuttle booking screen?
 *
 * @param int $post_id Post being rendered.
 * @return bool
 */
function bookingly_is_easy_shuttle_page( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_queried_object_id();

	if ( ! $post_id ) {
		return false;
	}

	if ( ! defined( 'MPSB_PLUGIN_URL' ) && ! class_exists( 'Mpsb_ShuttleSearchClass' ) ) {
		return false;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	foreach ( bookingly_easy_shuttle_shortcodes() as $shortcode ) {
		if ( has_shortcode( $post->post_content, $shortcode ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Give Easy Shuttle pages the full content width.
 *
 * The booking form is a twelve-column grid — route, pickup, date and time
 * across one row — and the plugin sizes it against its own container. At the
 * 760px reading measure that grid reflows to two fields per row, which is
 * correct but does not match the single shuttle template, where the plugin
 * supplies its own 1280px wrapper and the fields sit four across. Handing the
 * shortcode the whole 1180px keeps both routes to a booking looking alike.
 *
 * @param string $class   Wrapper classes.
 * @param int    $post_id Post being rendered.
 * @return string
 */
function bookingly_easy_shuttle_page_width( $class, $post_id ) {
	return bookingly_is_easy_shuttle_page( $post_id ) ? 'hv-wrap' : $class;
}
add_filter( 'bookingly_page_content_class', 'bookingly_easy_shuttle_page_width', 10, 2 );
