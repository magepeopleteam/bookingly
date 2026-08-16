<?php
/**
 * Page builder compatibility (Elementor, Divi, Block Editor).
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register builder-related theme supports.
 */
function bookingly_register_page_builder_support() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/main.css' );

	if ( defined( 'ELEMENTOR_VERSION' ) ) {
		add_theme_support( 'elementor' );
	}

	// Divi Builder plugin on third-party themes.
	if ( function_exists( 'et_divi_builder_init_plugin' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
		add_theme_support( 'divi-builder' );
	}
}
add_action( 'after_setup_theme', 'bookingly_register_page_builder_support', 20 );

/**
 * Register Elementor Theme Builder locations when Elementor Pro is active.
 */
function bookingly_register_elementor_locations( $elementor_theme_manager ) {
	$elementor_theme_manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'bookingly_register_elementor_locations' );

/**
 * Whether a post was built with Elementor.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_is_elementor_page( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id || ! did_action( 'elementor/loaded' ) ) {
		return false;
	}

	if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		return false;
	}

	/*
	 * Elementor stamps a page as "builder" the moment its editor is opened,
	 * even if nothing is ever placed or saved. Handing the page to Elementor on
	 * that flag alone means one abandoned editor session silently takes over a
	 * page the theme was rendering perfectly well. Require real Elementor data.
	 */
	$data = get_post_meta( $post_id, '_elementor_data', true );

	if ( empty( $data ) ) {
		return false;
	}

	if ( is_string( $data ) ) {
		$decoded = json_decode( $data, true );
		return ! empty( $decoded );
	}

	return ! empty( $data );
}

/**
 * Whether a post was built with Divi Builder.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_is_divi_page( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	return 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true );
}

/**
 * Whether post content contains blocks.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_has_block_content( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return false;
	}

	$content = get_post_field( 'post_content', $post_id );
	return $content && function_exists( 'has_blocks' ) && has_blocks( $content );
}

/**
 * Should the current page render builder/page content instead of a PHP section template?
 *
 * The decision is filterable so integrations can opt a page out without this
 * file having to know about them. WooCommerce needs exactly that: its cart and
 * checkout are ordinary pages holding blocks, which would otherwise be treated
 * as builder-authored and rendered edge to edge with no page shell.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_use_page_builder_content( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_queried_object_id();

	/**
	 * Filters whether a page renders its own content instead of a PHP section template.
	 *
	 * @param bool $use     Whether to render page/builder content.
	 * @param int  $post_id Post ID being rendered.
	 */
	return (bool) apply_filters( 'bookingly_use_page_builder_content', bookingly_detect_page_builder_content( $post_id ), $post_id );
}

/**
 * Work out, from the page itself, whether it holds builder or block content.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_detect_page_builder_content( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_queried_object_id();
	if ( ! $post_id ) {
		return false;
	}

	/*
	 * Elementor's editor scans the rendered preview for the output of
	 * the_content() and refuses to open the page without it ("the content area
	 * was not found"). A template that renders PHP sections never calls it, so
	 * whenever Elementor is editing or previewing, hand it the content area it
	 * requires — regardless of what this page would normally render.
	 */
	if ( bookingly_is_elementor_editing() ) {
		return true;
	}

	$template = get_page_template_slug( $post_id );

	if ( in_array( $template, array( 'page-templates/template-blank.php', 'page-templates/template-full-width.php' ), true ) ) {
		return true;
	}

	if ( bookingly_is_elementor_page( $post_id ) || bookingly_is_divi_page( $post_id ) ) {
		return true;
	}

	if ( is_front_page() && 'page_content' === bookingly_option( 'homepage.source', 'theme' ) ) {
		return true;
	}

	/*
	 * If the editor laid the page out with Bookingly section blocks, show those
	 * instead of the template's PHP sections — on any template, including
	 * Bookingly's own About/Services/Contact templates.
	 *
	 * The test is specifically for Bookingly blocks, not "any block": a page can
	 * legitimately hold a paragraph or two that a PHP section pulls in through
	 * the_content() (the Story section does exactly that), and that must keep
	 * working.
	 */
	if ( bookingly_has_bookingly_blocks( $post_id ) ) {
		return true;
	}

	/*
	 * Otherwise, generic block content only takes over on the default template.
	 *
	 * get_page_template_slug() returns '' for the default template, never the
	 * string 'default', so the original comparison could not match and blocks
	 * added to a page were silently ignored in favour of the PHP sections.
	 */
	if ( bookingly_has_block_content( $post_id ) && in_array( $template, array( '', 'default' ), true ) ) {
		return true;
	}

	return false;
}

/**
 * Whether Elementor is currently editing or previewing a page.
 *
 * Checks Elementor's own API when it is ready, and falls back to the request
 * arguments, because this question is asked during template selection — earlier
 * than Elementor's preview/editor objects are guaranteed to be initialised.
 *
 * @return bool
 */
function bookingly_is_elementor_editing() {
	if ( ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
		return false;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only mode detection; Elementor performs its own capability and nonce checks.
	if ( isset( $_GET['elementor-preview'] ) ) {
		return true;
	}

	if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
		return true;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$plugin = \Elementor\Plugin::$instance;

		if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
			return true;
		}

		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether a post was laid out using Bookingly section blocks.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function bookingly_has_bookingly_blocks( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_queried_object_id();
	if ( ! $post_id ) {
		return false;
	}

	$content = get_post_field( 'post_content', $post_id );

	return $content && false !== strpos( $content, '<!-- wp:bookingly/' );
}

/**
 * Render standard page builder content wrapper.
 *
 * @param bool $full_width Use full-width builder wrapper.
 */
function bookingly_render_builder_content( $full_width = false ) {
	$wrap_class = $full_width ? 'hv-builder-full' : 'hv-builder-content hv-wrap';
	?>
	<div class="hv-builder-section">
		<div class="<?php echo esc_attr( $wrap_class ); ?>">
			<?php
			while ( have_posts() ) {
				the_post();
				the_content();
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Add body classes for builder pages.
 *
 * @param array $classes Body classes.
 * @return array
 */
function bookingly_page_builder_body_classes( $classes ) {
	if ( bookingly_is_elementor_page() ) {
		$classes[] = 'bookingly-elementor-page';
	}
	if ( bookingly_is_divi_page() ) {
		$classes[] = 'bookingly-divi-page';
	}
	if ( bookingly_has_block_content() ) {
		$classes[] = 'bookingly-block-page';
	}
	return $classes;
}
add_filter( 'body_class', 'bookingly_page_builder_body_classes' );

/**
 * Keep Elementor canvas pages from inheriting narrow theme padding.
 */
function bookingly_elementor_canvas_support() {
	if ( ! bookingly_is_elementor_page() ) {
		return;
	}
	add_action(
		'wp_enqueue_scripts',
		function () {
			$width = max( 960, min( 1440, absint( bookingly_option( 'layout.content_width', 1180 ) ) ) );
			wp_add_inline_style(
				'bookingly-main',
				'.bookingly-elementor-page .hv-builder-section{padding:0}.bookingly-elementor-page .elementor-section.elementor-section-boxed>.elementor-container{max-width:' . $width . 'px}'
			);
		},
		30
	);
}
add_action( 'wp', 'bookingly_elementor_canvas_support' );
