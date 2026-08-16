<?php
/**
 * WooCommerce integration.
 *
 * Everything here is inert until WooCommerce is actually loaded, so the file
 * can be required unconditionally from functions.php.
 *
 * Two things had to be fixed for the shop pages to look like the rest of the
 * theme:
 *
 * 1. Cart and checkout are ordinary pages whose content is a block (or, on
 *    older stores, a shortcode). The generic "this page has blocks, so let the
 *    builder own the layout" rule caught them and rendered them edge to edge
 *    with no title and no gutter. They now go through the normal page shell.
 * 2. Without add_theme_support( 'woocommerce' ) the plugin falls back to its
 *    unsupported-theme shim, which is why /shop/ rendered nothing at all.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether WooCommerce is loaded in this request.
 *
 * @return bool
 */
function bookingly_has_woocommerce() {
	return class_exists( 'WooCommerce', false );
}

/**
 * Declare WooCommerce support.
 *
 * Runs late on after_setup_theme so WooCommerce has already registered itself.
 */
function bookingly_woocommerce_setup() {
	if ( ! bookingly_has_woocommerce() ) {
		return;
	}

	add_theme_support(
		'woocommerce',
		array(
			/*
			 * Catalogue thumbnails are cropped 4:3. Product artwork on a booking
			 * site is photography rather than packshots, and an uncropped ratio
			 * leaves ragged card bottoms in a grid.
			 */
			'thumbnail_image_width' => 480,
			'single_image_width'    => 780,
			'product_grid'          => array(
				'default_columns' => 3,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);

	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'bookingly_woocommerce_setup', 20 );

/**
 * Load the WooCommerce stylesheet only on stores that have the plugin.
 *
 * Enqueued after the main stylesheet so it can lean on the same custom
 * properties without repeating them, and so administrator colour choices
 * written into :root reach WooCommerce components too.
 */
function bookingly_woocommerce_styles() {
	if ( ! bookingly_has_woocommerce() ) {
		return;
	}

	wp_enqueue_style(
		'bookingly-woocommerce',
		BOOKINGLY_URI . '/assets/css/woocommerce.css',
		array( 'bookingly-main' ),
		bookingly_asset_version( '/assets/css/woocommerce.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'bookingly_woocommerce_styles', 20 );

/**
 * Whether the current request is one of WooCommerce's own account/order pages.
 *
 * These are real pages, not archives, so they render through page.php.
 *
 * @return bool
 */
function bookingly_is_woocommerce_page() {
	if ( ! bookingly_has_woocommerce() || is_admin() ) {
		return false;
	}

	return is_cart() || is_checkout() || is_account_page();
}

/**
 * Keep WooCommerce pages in the theme's page shell.
 *
 * The cart and checkout blocks are blocks like any other, so the generic
 * block-content rule handed them to the full-bleed builder wrapper: no page
 * title, no max width and no gutter, with the totals column running off the
 * side of the viewport. They are application screens, not builder layouts.
 *
 * @param bool $use     Whether the page renders its own content.
 * @param int  $post_id Post being rendered.
 * @return bool
 */
function bookingly_woocommerce_page_shell( $use, $post_id ) {
	if ( ! $use || ! bookingly_is_woocommerce_page() ) {
		return $use;
	}

	/*
	 * An Elementor or Divi layout on a WooCommerce page is a deliberate choice
	 * by the site owner, so leave those alone -- only the block/shortcode case
	 * is being corrected here.
	 */
	if ( bookingly_is_elementor_page( $post_id ) || bookingly_is_divi_page( $post_id ) || bookingly_is_elementor_editing() ) {
		return $use;
	}

	return false;
}
add_filter( 'bookingly_use_page_builder_content', 'bookingly_woocommerce_page_shell', 10, 2 );

/**
 * Give WooCommerce pages the full content width.
 *
 * 760px is a reading measure for prose. A cart table with a totals sidebar, or
 * a two-column checkout, needs the whole 1180px or the columns collapse into
 * each other on a desktop screen.
 *
 * @param string $class   Wrapper classes.
 * @param int    $post_id Post being rendered.
 * @return string
 */
function bookingly_woocommerce_page_width( $class, $post_id ) {
	unset( $post_id );

	return bookingly_is_woocommerce_page() ? 'hv-wrap hv-woo' : $class;
}
add_filter( 'bookingly_page_content_class', 'bookingly_woocommerce_page_width', 10, 2 );

/**
 * Body classes for WooCommerce screens.
 *
 * @param array $classes Body classes.
 * @return array
 */
function bookingly_woocommerce_body_classes( $classes ) {
	if ( ! bookingly_has_woocommerce() ) {
		return $classes;
	}

	if ( bookingly_is_woocommerce_page() || is_woocommerce() ) {
		$classes[] = 'bookingly-woo';
	}

	if ( is_checkout() && ! is_order_received_page() ) {
		$classes[] = 'bookingly-woo-checkout';
	}

	if ( is_order_received_page() ) {
		$classes[] = 'bookingly-woo-thankyou';
	}

	return $classes;
}
add_filter( 'body_class', 'bookingly_woocommerce_body_classes' );

/**
 * Open the theme's content wrapper around shop, taxonomy and product templates.
 *
 * Used by woocommerce.php, which is the template WooCommerce picks for every
 * archive and single product once the theme declares support.
 *
 * @param bool $after_hero Whether a page hero was printed directly above. A
 *                         hero already carries the top spacing; without one the
 *                         section has to supply its own or the content starts
 *                         flush against the site header.
 */
function bookingly_woocommerce_wrapper_start( $after_hero = true ) {
	printf(
		'<section class="hv-section%s"><div class="hv-wrap hv-woo">',
		$after_hero ? ' hv-section--flush-top' : ''
	);
}

/**
 * Close the theme's content wrapper.
 */
function bookingly_woocommerce_wrapper_end() {
	echo '</div></section>';
}

/**
 * Suppress WooCommerce's own archive title.
 *
 * woocommerce.php prints the title in the theme's page hero, and Woo prints it
 * again at the top of archive-product.php — two identical <h1>s on /shop/.
 *
 * @return bool
 */
function bookingly_woocommerce_hide_page_title() {
	return false;
}
add_filter( 'woocommerce_show_page_title', 'bookingly_woocommerce_hide_page_title' );

/**
 * Drop the shop sidebar.
 *
 * The theme has no sidebar region, so WooCommerce's call renders nothing but
 * still emits an empty aside that upsets the product grid's column maths.
 */
function bookingly_woocommerce_remove_sidebar() {
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}
add_action( 'init', 'bookingly_woocommerce_remove_sidebar' );

/**
 * Products per row in the catalogue.
 *
 * @return int
 */
function bookingly_woocommerce_loop_columns() {
	return 3;
}
add_filter( 'loop_shop_columns', 'bookingly_woocommerce_loop_columns' );

/*
 * Catalogue cards are aligned with flexbox in woocommerce.css rather than by
 * injecting a wrapper element. WooCommerce closes the card's product link on
 * woocommerce_after_shop_loop_item, so any div opened before it and closed
 * after would straddle that closing tag and produce invalid nesting.
 */
