<?php
/**
 * Theme setup and activation helpers.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme supports and menus.
 */
function bookingly_setup() {
	load_theme_textdomain( 'bookingly', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	register_nav_menus(
		array(
			'primary'         => __( 'Primary Menu', 'bookingly' ),
			'footer_explore'  => __( 'Footer Explore Menu', 'bookingly' ),
			'footer_services' => __( 'Footer Services Menu', 'bookingly' ),
		)
	);

	/*
	 * 16:9 rather than 3:2. Blog and service artwork is overwhelmingly
	 * banner-shaped (~1.8:1), and a 3:2 hard crop shaved ~8% off each side —
	 * enough to cut the first letter off left-aligned titles baked into the art.
	 */
	add_image_size( 'bookingly-card', 640, 360, true );
	add_image_size( 'bookingly-hero', 1200, 700, true );

	/*
	 * Social share image. 1200x630 is the 1.91:1 frame Facebook, LinkedIn and
	 * WhatsApp all crop previews to, and it exists so a share never points at
	 * the full-size original: a 1696x1061 screenshot is a ~1MB PNG, and while
	 * Facebook tolerates that, WhatsApp silently drops any preview image over
	 * roughly 300KB — the link arrives with no picture at all.
	 */
	add_image_size( 'bookingly-share', 1200, 630, true );
}
add_action( 'after_setup_theme', 'bookingly_setup' );

/**
 * Apply the administrator-selected content width to WordPress core embeds.
 */
function bookingly_apply_content_width() {
	global $content_width;

	$content_width = max( 960, min( 1440, absint( bookingly_option( 'layout.content_width', 1180 ) ) ) );
}
add_action( 'after_setup_theme', 'bookingly_apply_content_width', 30 );

/**
 * Keep the block editor's wide boundary aligned with the saved site width.
 *
 * @param WP_Theme_JSON_Data $theme_json Theme JSON data wrapper.
 * @return WP_Theme_JSON_Data
 */
function bookingly_apply_theme_json_width( $theme_json ) {
	if ( ! $theme_json instanceof WP_Theme_JSON_Data ) {
		return $theme_json;
	}

	$width = max( 960, min( 1440, absint( bookingly_option( 'layout.content_width', 1180 ) ) ) );
	return $theme_json->update_with(
		array(
			'version'  => 3,
			'settings' => array(
				'layout' => array( 'wideSize' => $width . 'px' ),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'bookingly_apply_theme_json_width' );

/**
 * Version string for a theme asset, based on its last modified time.
 *
 * Pinning assets to the theme version means an edited stylesheet keeps the same
 * URL, so browsers keep serving the copy they already cached and changes appear
 * not to land. Falling back to the theme version keeps output deterministic if
 * the file is ever unreadable.
 *
 * @param string $relative_path Path relative to the theme root, with a leading slash.
 * @return string
 */
function bookingly_asset_version( $relative_path ) {
	$file = BOOKINGLY_DIR . $relative_path;

	if ( is_readable( $file ) ) {
		$modified = filemtime( $file );
		if ( $modified ) {
			return BOOKINGLY_VERSION . '.' . $modified;
		}
	}

	return BOOKINGLY_VERSION;
}

/**
 * Enqueue theme assets.
 */
function bookingly_enqueue_assets() {
	/*
	 * Fonts and icons are served from the theme. No Google Fonts, no icon CDN:
	 * that removes two third-party connections from the critical path and keeps
	 * visitor IPs from leaving the site (a GDPR requirement in the EU).
	 * The @font-face rules live at the top of main.css.
	 */
	wp_enqueue_style(
		'bookingly-main',
		BOOKINGLY_URI . '/assets/css/main.css',
		array(),
		bookingly_asset_version( '/assets/css/main.css' )
	);

	wp_enqueue_style(
		'bookingly-style',
		get_stylesheet_uri(),
		array( 'bookingly-main' ),
		bookingly_asset_version( '/style.css' )
	);

	wp_enqueue_script(
		'bookingly-main',
		BOOKINGLY_URI . '/assets/js/main.js',
		array(),
		bookingly_asset_version( '/assets/js/main.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'bookingly-main',
		'bookinglyData',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'bookingly_contact' ),
			'serviceShown'  => /* translators: %d: number of visible services. */ __( '%d service shown', 'bookingly' ),
			'servicesShown' => /* translators: %d: number of visible services. */ __( '%d services shown', 'bookingly' ),
			'sending'       => __( 'Sending…', 'bookingly' ),
			'sent'          => __( 'Message sent', 'bookingly' ),
			'genericError'  => __( 'Unable to send your message. Please try again.', 'bookingly' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'bookingly_enqueue_assets' );

/**
 * Preload the two font files that are needed to paint the first screen.
 *
 * Only the latin subsets are preloaded. The latin-ext and italic faces are
 * left to unicode-range so a purely English page never downloads them.
 */
function bookingly_preload_fonts() {
	$fonts = array(
		'/assets/fonts/jakarta-latin.woff2',
		'/assets/fonts/fraunces-latin.woff2',
	);

	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( BOOKINGLY_URI . $font )
		);
	}
}
add_action( 'wp_head', 'bookingly_preload_fonts', 1 );

/**
 * Drop core's emoji scripts.
 *
 * Two files and an inline blob on every request, for a feature modern browsers
 * render natively.
 */
function bookingly_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'bookingly_disable_emojis' );

/**
 * Body classes for layout states.
 *
 * @param array $classes Existing classes.
 * @return array
 */
function bookingly_body_classes( $classes ) {
	$classes[] = bookingly_option( 'header.sticky', true ) ? 'hv-header-sticky' : 'hv-header-static';
	$classes[] = bookingly_option( 'header.show_mobile_cta', true ) ? 'hv-mobile-cta-visible' : 'hv-mobile-cta-hidden';

	if ( 'reduced' === bookingly_option( 'layout.motion', 'system' ) ) {
		$classes[] = 'hv-motion-reduced';
	}

	if ( is_singular( bookingly_service_post_type() ) ) {
		$classes[] = 'bookingly-service-single';
	}

	if ( is_front_page() ) {
		$classes[] = 'bookingly-front-page';
	}

	return $classes;
}
add_filter( 'body_class', 'bookingly_body_classes' );

/**
 * Create starter pages and fill missing reading settings.
 *
 * Called only after an administrator explicitly chooses Prepare Pages in the
 * setup wizard. Theme activation itself never creates content or changes the
 * site's reading configuration.
 *
 * Existing pages are never edited. The return value lets the setup wizard
 * distinguish pages it created from pages that were already present.
 *
 * @return array<string,mixed> Page creation result.
 */
function bookingly_create_starter_pages() {
	$original_front_mode = (string) get_option( 'show_on_front' );
	$original_front_page = (int) get_option( 'page_on_front' );
	$original_posts_page = (int) get_option( 'page_for_posts' );
	$valid_front_page    = $original_front_page > 0 && 'publish' === get_post_status( $original_front_page );
	$valid_posts_page    = $original_posts_page > 0 && 'publish' === get_post_status( $original_posts_page );

	$pages = array(
		'home'     => array(
			'title'    => 'Home',
			'template' => '',
		),
		'about'    => array(
			'title'    => 'About',
			'template' => 'page-templates/template-about.php',
		),
		'services' => array(
			'title'    => 'Services',
			'template' => 'page-templates/template-services.php',
		),
		'contact'  => array(
			'title'    => 'Contact',
			'template' => 'page-templates/template-contact.php',
		),
		'blog'     => array(
			'title'    => 'Blog',
			'template' => '',
		),
	);

	$page_ids = array();
	$results  = array();
	$errors   = array();

	foreach ( $pages as $slug => $page ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			if ( 'publish' !== $existing->post_status ) {
				$message  = __( 'An existing page uses this URL but is not published. Publish it before continuing.', 'bookingly' );
				$errors[] = $message;
				$results[ $slug ] = array(
					'id'      => (int) $existing->ID,
					'label'   => $page['title'],
					'status'  => 'error',
					'message' => $message,
				);
				continue;
			}

			$page_ids[ $slug ] = $existing->ID;
			$results[ $slug ]  = array(
				'id'      => (int) $existing->ID,
				'label'   => $page['title'],
				'status'  => 'existing',
				'message' => __( 'Already exists. Existing content was kept.', 'bookingly' ),
			);
			continue;
		}

		$page_ids[ $slug ] = wp_insert_post(
			array(
				'post_title'   => $page['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $page_ids[ $slug ] ) ) {
			$message = $page_ids[ $slug ]->get_error_message();
			$errors[] = $message;
			$results[ $slug ] = array(
				'id'      => 0,
				'label'   => $page['title'],
				'status'  => 'error',
				'message' => $message,
			);
			continue;
		}

		if ( $page['template'] ) {
			update_post_meta( $page_ids[ $slug ], '_wp_page_template', $page['template'] );
		}

		$results[ $slug ] = array(
			'id'      => (int) $page_ids[ $slug ],
			'label'   => $page['title'],
			'status'  => 'created',
			'message' => __( 'Created.', 'bookingly' ),
		);
	}

	if ( ! $valid_front_page && ! empty( $page_ids['home'] ) && ! is_wp_error( $page_ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_ids['home'] );
	}

	if ( ! $valid_posts_page && ! empty( $page_ids['blog'] ) && ! is_wp_error( $page_ids['blog'] ) ) {
		update_option( 'page_for_posts', $page_ids['blog'] );
	}

	return array(
		'success'    => empty( $errors ),
		'pages'      => $results,
		'errors'     => $errors,
		'front_page' => (int) get_option( 'page_on_front' ),
		'posts_page' => (int) get_option( 'page_for_posts' ),
		'preserved'  => array(
			'show_on_front' => $valid_front_page ? $original_front_mode : (string) get_option( 'show_on_front' ),
			'front_page'    => $valid_front_page ? $original_front_page : (int) get_option( 'page_on_front' ),
			'posts_page'    => $valid_posts_page ? $original_posts_page : (int) get_option( 'page_for_posts' ),
		),
	);
}

/**
 * Fallback primary menu when no menu is assigned.
 */
function bookingly_fallback_menu() {
	/*
	 * Resolve real permalinks instead of guessing "/about/". Guessed slugs 404
	 * on any site running plain permalinks, on a site in a subdirectory, or
	 * whenever a starter page was renamed — which made every header link but
	 * Home dead. get_permalink() is correct under every permalink structure.
	 */
	$links = array(
		array(
			'url'   => home_url( '/' ),
			'label' => __( 'Home', 'bookingly' ),
		),
	);

	$templates = array(
		'page-templates/template-about.php'    => __( 'About', 'bookingly' ),
		'page-templates/template-services.php' => __( 'Services', 'bookingly' ),
	);

	foreach ( $templates as $template => $label ) {
		$page = bookingly_get_page_by_template( $template );
		if ( $page ) {
			$links[] = array(
				'url'   => get_permalink( $page ),
				'label' => $label,
			);
		}
	}

	$blog_page_id = (int) get_option( 'page_for_posts' );
	if ( $blog_page_id && 'publish' === get_post_status( $blog_page_id ) ) {
		$links[] = array(
			'url'   => get_permalink( $blog_page_id ),
			'label' => get_the_title( $blog_page_id ),
		);
	}

	$contact_page = bookingly_get_page_by_template( 'page-templates/template-contact.php' );
	if ( $contact_page ) {
		$links[] = array(
			'url'   => get_permalink( $contact_page ),
			'label' => __( 'Contact', 'bookingly' ),
		);
	}

	echo '<ul class="hv-nav-links">';
	foreach ( $links as $link ) {
		printf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( $link['url'] ),
			esc_html( $link['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Unicode-safe input length.
 *
 * @param string $value Input value.
 * @return int
 */
function bookingly_contact_text_length( $value ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
}

/**
 * Validate and process a contact request without terminating the PHP request.
 *
 * This separation keeps the public AJAX wrapper small and makes every delivery
 * branch testable without sending a real HTTP response or email.
 *
 * @param array<string,mixed> $request   Untrusted request values.
 * @param string              $client_ip Direct REMOTE_ADDR value.
 * @return array<string,mixed>|WP_Error
 */
function bookingly_process_contact_submission( $request, $client_ip = '' ) {
	$request = is_array( $request ) ? wp_unslash( $request ) : array();

	if ( ! empty( $request['website'] ) ) {
		return array(
			'accepted'             => true,
			'confirmation_accepted' => false,
			'message'              => __( 'Your message was received successfully.', 'bookingly' ),
		);
	}

	$submission_id = isset( $request['submission_id'] ) ? sanitize_text_field( $request['submission_id'] ) : '';
	if ( ! Bookingly_Email_Delivery::valid_submission_id( $submission_id ) ) {
		return new WP_Error( 'bookingly_contact_invalid_submission', __( 'Please refresh the page and try again.', 'bookingly' ) );
	}

	$raw = array(
		'name'    => isset( $request['name'] ) ? (string) $request['name'] : '',
		'email'   => isset( $request['email'] ) ? (string) $request['email'] : '',
		'phone'   => isset( $request['phone'] ) ? (string) $request['phone'] : '',
		'topic'   => isset( $request['topic'] ) ? (string) $request['topic'] : '',
		'message' => isset( $request['message'] ) ? (string) $request['message'] : '',
	);
	$limits = array( 'name' => 120, 'email' => 254, 'phone' => 60, 'topic' => 120, 'message' => 5000 );
	foreach ( $limits as $field => $limit ) {
		if ( bookingly_contact_text_length( $raw[ $field ] ) > $limit ) {
			return new WP_Error( 'bookingly_contact_invalid_fields', __( 'One or more fields are too long. Please shorten your message and try again.', 'bookingly' ) );
		}
	}

	$fields = array(
		'name'    => sanitize_text_field( $raw['name'] ),
		'email'   => sanitize_email( $raw['email'] ),
		'phone'   => sanitize_text_field( $raw['phone'] ),
		'topic'   => sanitize_text_field( $raw['topic'] ),
		'message' => sanitize_textarea_field( $raw['message'] ),
	);

	if ( '' === $fields['name'] || '' === $fields['email'] || '' === $fields['message'] ) {
		return new WP_Error( 'bookingly_contact_required_fields', __( 'Please fill in all required fields.', 'bookingly' ) );
	}
	if ( ! is_email( $fields['email'] ) ) {
		return new WP_Error( 'bookingly_contact_invalid_email', __( 'Enter a valid email address.', 'bookingly' ) );
	}
	if ( '' === $fields['topic'] ) {
		$fields['topic'] = __( 'General Inquiry', 'bookingly' );
	}

	$rate_limit = Bookingly_Email_Delivery::consume_rate_limit( $fields['email'], $client_ip );
	if ( is_wp_error( $rate_limit ) ) {
		return $rate_limit;
	}

	$state = Bookingly_Email_Delivery::submission_state( $submission_id );
	if ( empty( $state['admin'] ) ) {
		$admin = Bookingly_Email_Delivery::send_admin( $fields, $submission_id );
		if ( empty( $admin['accepted'] ) ) {
			return new WP_Error( 'bookingly_contact_delivery_failed', __( 'Unable to send your message right now. Please try again later.', 'bookingly' ) );
		}
		Bookingly_Email_Delivery::mark_submission_stage( $submission_id, 'admin' );
	}

	$confirmation_accepted = false;
	if ( Bookingly_Email_Delivery::customer_confirmation_enabled() ) {
		$state = Bookingly_Email_Delivery::submission_state( $submission_id );
		if ( empty( $state['customer'] ) ) {
			$customer = Bookingly_Email_Delivery::send_customer( $fields, $submission_id );
			if ( ! empty( $customer['accepted'] ) ) {
				Bookingly_Email_Delivery::mark_submission_stage( $submission_id, 'customer' );
				$confirmation_accepted = true;
			}
		} else {
			$confirmation_accepted = true;
		}
	}

	return array(
		'accepted'              => true,
		'confirmation_accepted' => $confirmation_accepted,
		'message'               => Bookingly_Email_Delivery::customer_confirmation_enabled() && ! $confirmation_accepted
			? __( 'Your message was received, but we could not send the confirmation email.', 'bookingly' )
			: __( 'Your message was received successfully.', 'bookingly' ),
	);
}

/** Handle contact form submissions. */
function bookingly_handle_contact_form() {
	check_ajax_referer( 'bookingly_contact', 'nonce' );
	$client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$result    = bookingly_process_contact_submission( $_POST, $client_ip );

	if ( is_wp_error( $result ) ) {
		$status = 'bookingly_contact_rate_limited' === $result->get_error_code() ? 429 : ( 'bookingly_contact_delivery_failed' === $result->get_error_code() ? 502 : 400 );
		wp_send_json_error( array( 'message' => $result->get_error_message() ), $status );
	}

	wp_send_json_success( array( 'message' => $result['message'] ) );
}
add_action( 'wp_ajax_bookingly_contact', 'bookingly_handle_contact_form' );
add_action( 'wp_ajax_nopriv_bookingly_contact', 'bookingly_handle_contact_form' );
