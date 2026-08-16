<?php
/**
 * Bookingly layouts for Elementor.
 *
 * Elementor and the block editor both own post_content but store completely
 * different formats, and neither converts to the other. So a page laid out with
 * Bookingly blocks opens as an empty Elementor canvas — there is nothing there
 * Elementor understands.
 *
 * This builds the same section list as the block patterns as Elementor data, so
 * a page can be handed to Elementor with its layout intact. The block markup in
 * post_content is left untouched: Elementor takes precedence only while
 * _elementor_data exists, so removing it restores the block layout.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section lists per layout, mirroring the block patterns.
 *
 * @return array<string,array<string,mixed>>
 */
function bookingly_elementor_layouts() {
	return array(
		'homepage' => array(
			'label'    => __( 'Bookingly — Full homepage', 'bookingly' ),
			'sections' => array( 'hero', 'trust_strip', 'services', 'how_it_works', 'about', 'testimonials', 'cta_band' ),
		),
		'about'    => array(
			'label'    => __( 'Bookingly — About page', 'bookingly' ),
			'sections' => array( 'page_hero', 'story', 'value_props', 'stats', 'team', 'cta_band' ),
		),
		'services' => array(
			'label'    => __( 'Bookingly — Services page', 'bookingly' ),
			'sections' => array( 'page_hero', 'services', 'cta_band' ),
		),
		'contact'  => array(
			'label'    => __( 'Bookingly — Contact page', 'bookingly' ),
			'sections' => array( 'page_hero', 'contact_cards', 'contact_form', 'map', 'business_hours' ),
		),
		'blog'     => array(
			'label'    => __( 'Bookingly — Blog landing', 'bookingly' ),
			'sections' => array( 'page_hero', 'blog_posts', 'newsletter' ),
		),
	);
}

/**
 * Deterministic 7-character element id, the shape Elementor expects.
 *
 * @param string $seed Unique seed.
 * @return string
 */
function bookingly_elementor_element_id( $seed ) {
	return substr( md5( $seed ), 0, 7 );
}

/**
 * Build Elementor data for a list of sections.
 *
 * Each section becomes a full-width container holding one Bookingly widget, which
 * is the structure an Elementor user expects to find: containers they can
 * restyle, reorder and delete, not one opaque blob.
 *
 * @param string[] $sections Section ids.
 * @param string   $seed     Seed for element ids.
 * @return array<int,array<string,mixed>>
 */
function bookingly_elementor_layout_data( $sections, $seed = 'bookingly' ) {
	$data = array();

	foreach ( array_values( $sections ) as $index => $section ) {
		if ( ! bookingly_get_section( $section ) ) {
			continue;
		}

		$widget_type = 'bookingly-' . str_replace( '_', '-', $section );

		$data[] = array(
			'id'       => bookingly_elementor_element_id( $seed . '-c-' . $index ),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array(
				'content_width' => 'full',
				'padding'       => array(
					'unit'     => 'px',
					'top'      => '0',
					'right'    => '0',
					'bottom'   => '0',
					'left'     => '0',
					'isLinked' => true,
				),
			),
			'elements' => array(
				array(
					'id'         => bookingly_elementor_element_id( $seed . '-w-' . $index ),
					'elType'     => 'widget',
					'widgetType' => $widget_type,
					'isInner'    => false,
					'settings'   => array(),
					'elements'   => array(),
				),
			),
		);
	}

	return $data;
}

/**
 * Hand a page to Elementor with a Bookingly layout.
 *
 * @param int    $post_id Page ID.
 * @param string $layout  Layout key from bookingly_elementor_layouts().
 * @return true|WP_Error
 */
function bookingly_apply_elementor_layout( $post_id, $layout ) {
	$post_id = (int) $post_id;
	$layouts = bookingly_elementor_layouts();

	if ( ! $post_id || ! get_post( $post_id ) ) {
		return new WP_Error( 'bookingly_no_post', __( 'That page no longer exists.', 'bookingly' ) );
	}

	if ( ! isset( $layouts[ $layout ] ) ) {
		return new WP_Error( 'bookingly_no_layout', __( 'Unknown layout.', 'bookingly' ) );
	}

	if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
		return new WP_Error( 'bookingly_no_elementor', __( 'Elementor is not active.', 'bookingly' ) );
	}

	if ( bookingly_layout_is_user_owned( $post_id ) ) {
		return new WP_Error( 'bookingly_layout_user_owned', __( 'This page contains an authored layout, so Bookingly did not replace it.', 'bookingly' ) );
	}

	if ( ! bookingly_layout_ensure_backup( $post_id ) ) {
		return new WP_Error( 'bookingly_layout_backup_failed', __( 'The original page state could not be backed up.', 'bookingly' ) );
	}

	$data = bookingly_elementor_layout_data( $layouts[ $layout ]['sections'], 'bookingly-' . $layout . '-' . $post_id );

	if ( empty( $data ) ) {
		return new WP_Error( 'bookingly_empty_layout', __( 'That layout has no sections.', 'bookingly' ) );
	}

	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
	// wp_slash: update_post_meta unslashes, and the JSON must survive intact.
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	$stored = get_post_meta( $post_id, '_elementor_data', true );
	if ( json_decode( (string) $stored, true ) !== $data ) {
		return new WP_Error( 'bookingly_elementor_verify_failed', __( 'Elementor did not preserve the generated layout.', 'bookingly' ) );
	}

	bookingly_layout_mark_generated( $post_id, 'elementor', $layout );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}

	return true;
}

/**
 * Which Bookingly layout belongs to a page, if any.
 *
 * @param int $post_id Page ID.
 * @return string Layout key, or '' when the page is not a Bookingly page.
 */
function bookingly_layout_key_for_page( $post_id ) {
	if ( ! function_exists( 'bookingly_managed_pages' ) ) {
		return '';
	}

	$key = array_search( (int) $post_id, bookingly_managed_pages(), true );

	return $key ? $key : '';
}

/**
 * Seed a Bookingly layout when a page is opened in Elementor with nothing to edit.
 *
 * Opening a block-built page in Elementor otherwise shows an empty canvas:
 * the layout lives in post_content as blocks, which Elementor cannot read, and
 * there is no Elementor data yet. Rather than leave that dead end, give the
 * page the same sections as widgets the first time it is opened.
 *
 * post_content is untouched, so removing the Elementor data restores the blocks.
 */
function bookingly_seed_elementor_layout_on_open() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor verifies the request itself; this only reads the target id.
	$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Never overwrite content or any builder state, regardless of its markup.
	$existing = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! empty( $existing ) && '[]' !== trim( (string) $existing ) ) {
		return;
	}

	if ( '' !== trim( (string) get_post_field( 'post_content', $post_id ) ) || bookingly_layout_has_builder_meta( $post_id ) ) {
		return;
	}

	$layout = bookingly_layout_key_for_page( $post_id );
	if ( ! $layout ) {
		// Not a Bookingly page: a blank canvas is the correct starting point.
		return;
	}

	bookingly_apply_elementor_layout( $post_id, $layout );
}
add_action( 'admin_action_elementor', 'bookingly_seed_elementor_layout_on_open', 1 );

/**
 * Handle the "build with Elementor" / "restore blocks" admin actions.
 */
function bookingly_handle_elementor_layout_action() {
	if ( empty( $_GET['bookingly_elementor_action'] ) || empty( $_GET['post'] ) ) {
		return;
	}

	$action  = sanitize_key( wp_unslash( $_GET['bookingly_elementor_action'] ) );
	$post_id = (int) $_GET['post'];

	if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change this page layout.', 'bookingly' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'bookingly_elementor_layout_' . $post_id );

	$notice = 'applied';

	if ( 'clear' === $action ) {
		$result = bookingly_restore_layout_backup( $post_id );
		$notice = is_wp_error( $result ) ? 'error' : 'cleared';
	} else {
		$result = bookingly_apply_elementor_layout( $post_id, $action );
		if ( is_wp_error( $result ) ) {
			$notice = 'error';
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'bookingly_layout'  => $notice,
			),
			Bookingly_Theme_Options::page_url( 'builders' )
		)
	);
	exit;
}
add_action( 'admin_init', 'bookingly_handle_elementor_layout_action' );
