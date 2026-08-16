<?php
/**
 * Bookingly Theme Options admin panel.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme options page controller.
 */
final class Bookingly_Theme_Options {

	const OPTION_KEY = 'bookingly_theme_options';
	const PAGE_SLUG  = 'bookingly-theme-options';
	const CAPABILITY = 'edit_theme_options';

	/** @var string Exact WordPress hook suffix for the top-level page. */
	private static $page_hook = '';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_page' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_color_variables' ), 20 );
		add_filter( 'option_page_capability_bookingly_theme_options_group', array( __CLASS__, 'settings_capability' ) );
		add_filter( 'pre_update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_migrate_legacy_mods' ), 5, 2 );
	}

	/**
	 * Register the dedicated Bookingly parent and its first submenu label.
	 */
	public static function register_menu() {
		self::$page_hook = add_menu_page(
			__( 'Bookingly Theme Options', 'bookingly' ),
			__( 'Bookingly', 'bookingly' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-admin-customizer',
			58
		);

		// Empty callback: the top-level page already owns the single renderer.
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Bookingly Theme Options', 'bookingly' ),
			__( 'Theme Options', 'bookingly' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			''
		);
	}

	/** @return string Exact registered page hook. */
	public static function page_hook() {
		return self::$page_hook;
	}

	/** @return string Dedicated Theme Options URL. */
	public static function page_url( $section = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		return $section ? add_query_arg( 'section', sanitize_key( $section ), $url ) : $url;
	}

	/** Redirect only the two former Appearance-page bookmarks. */
	public static function maybe_redirect_legacy_page() {
		global $pagenow;
		if ( 'themes.php' !== $pagenow || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only legacy route.
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only legacy route.
		if ( self::PAGE_SLUG === $page && current_user_can( self::CAPABILITY ) ) {
			$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserved navigation state.
			wp_safe_redirect( self::page_url( $section ) );
			exit;
		}

		if ( 'bookingly-setup' === $page && current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bookingly-setup' ) );
			exit;
		}
	}

	/**
	 * Register the single serialized option.
	 */
	public static function register_settings() {
		register_setting(
			'bookingly_theme_options_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => bookingly_default_options(),
			)
		);
	}

	/**
	 * Keep the options.php capability aligned with the page capability.
	 *
	 * @return string
	 */
	public static function settings_capability() {
		return self::CAPABILITY;
	}

	/**
	 * Enqueue admin assets on the options page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( ! self::$page_hook || self::$page_hook !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'bookingly-theme-options',
			BOOKINGLY_URI . '/assets/admin/theme-options.css',
			array(),
			BOOKINGLY_VERSION
		);
		wp_enqueue_script(
			'bookingly-theme-options',
			BOOKINGLY_URI . '/assets/admin/theme-options.js',
			array( 'jquery', 'wp-a11y' ),
			BOOKINGLY_VERSION,
			true
		);

		wp_localize_script(
			'bookingly-theme-options',
			'bookinglyThemeOptions',
			array(
				'mediaTitle'       => __( 'Select an image', 'bookingly' ),
				'mediaButton'      => __( 'Use this image', 'bookingly' ),
				'unsaved'          => __( 'You have unsaved changes.', 'bookingly' ),
				'saving'           => __( 'Saving theme options…', 'bookingly' ),
				'sectionChanged'   => __( 'Configuration section opened:', 'bookingly' ),
				'imageSelected'    => __( 'Image selected.', 'bookingly' ),
				'imageRemoved'     => __( 'Image removed.', 'bookingly' ),
				'noImage'          => __( 'No image selected', 'bookingly' ),
			)
		);
	}

	/**
	 * Output CSS custom properties from saved design options.
	 */
	public static function output_color_variables() {
		$colors = bookingly_option( 'colors', array() );
		$layout = bookingly_option( 'layout', array() );
		$colors = is_array( $colors ) ? $colors : array();
		$layout = is_array( $layout ) ? $layout : array();

		$primary      = sanitize_hex_color( $colors['primary'] ?? '#2B6E58' ) ?: '#2B6E58';
		$primary_dark = sanitize_hex_color( $colors['primary_dark'] ?? '#1E4F3F' ) ?: '#1E4F3F';
		$accent       = sanitize_hex_color( $colors['accent'] ?? '#E7A33E' ) ?: '#E7A33E';
		$background   = sanitize_hex_color( $colors['background'] ?? '#F5F7F5' ) ?: '#F5F7F5';
		$content_width = max( 960, min( 1440, absint( $layout['content_width'] ?? 1180 ) ) );
		$spacing       = sanitize_key( $layout['section_spacing'] ?? 'comfortable' );
		$corners       = sanitize_key( $layout['corner_style'] ?? 'rounded' );
		$section_space = array(
			'compact'     => 'clamp(52px,6vw,72px)',
			'comfortable' => 'clamp(70px,8vw,96px)',
			'spacious'    => 'clamp(88px,10vw,120px)',
		)[ $spacing ] ?? 'clamp(70px,8vw,96px)';
		$radius = array(
			'soft'    => array( '6px', '10px', '14px' ),
			'rounded' => array( '9px', '14px', '22px' ),
			'square'  => array( '0px', '0px', '0px' ),
		)[ $corners ] ?? array( '9px', '14px', '22px' );

		/*
		 * These must be the --hv-* names the stylesheet actually reads. The
		 * short --primary/--accent aliases are emitted too so any custom CSS a
		 * site added against the 1.0.0 variable names keeps working.
		 */
		printf(
			'<style id="bookingly-theme-colors">:root{--hv-primary:%1$s;--hv-primary-dark:%2$s;--hv-accent:%3$s;--hv-bg:%4$s;--hv-primary-tint:%5$s;--hv-accent-tint:%6$s;--hv-wrap:%7$dpx;--hv-content-width:%7$dpx;--hv-section-y:%8$s;--hv-section-space:%8$s;--hv-radius-sm:%9$s;--hv-radius-md:%10$s;--hv-radius-lg:%11$s;--hv-radius:%10$s;--primary:%1$s;--primary-dark:%2$s;--accent:%3$s;--bg:%4$s;--primary-tint:%5$s;--accent-tint:%6$s;}%12$s</style>',
			esc_attr( $primary ),
			esc_attr( $primary_dark ),
			esc_attr( $accent ),
			esc_attr( $background ),
			esc_attr( self::hex_to_rgba_string( $primary, '0.12' ) ),
			esc_attr( self::hex_to_rgba_string( $accent, '0.15' ) ),
			$content_width,
			esc_attr( $section_space ),
			esc_attr( $radius[0] ),
			esc_attr( $radius[1] ),
			esc_attr( $radius[2] ),
			'reduced' === ( $layout['motion'] ?? 'system' ) ? 'html{scroll-behavior:auto}' : ''
		);
	}

	/**
	 * Sanitize submitted options.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize_options( $input ) {
		$raw_existing = get_option( self::OPTION_KEY, array() );
		$raw_existing = is_array( $raw_existing ) ? $raw_existing : array();
		$existing     = bookingly_merge_options( $raw_existing );

		if ( ! is_array( $input ) ) {
			return $raw_existing;
		}

		$posted_revision = isset( $_POST['bookingly_options_revision'] ) ? sanitize_text_field( wp_unslash( $_POST['bookingly_options_revision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies the Settings API nonce.
		$current_revision = self::options_revision( $raw_existing );
		if ( $posted_revision && ! hash_equals( $current_revision, $posted_revision ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'bookingly_stale_options',
				__( 'These settings changed in another tab or session. Nothing was overwritten; reload and apply your changes again.', 'bookingly' ),
				'error'
			);
			return $raw_existing;
		}

		if ( isset( $input['email'] ) && is_array( $input['email'] ) ) {
			$existing_key = isset( $existing['email']['brevo_api_key'] ) ? (string) $existing['email']['brevo_api_key'] : '';
			if ( ! empty( $input['email']['clear_brevo_api_key'] ) ) {
				$input['email']['brevo_api_key'] = '';
			} elseif ( array_key_exists( 'brevo_api_key', $input['email'] ) && '' === trim( (string) $input['email']['brevo_api_key'] ) ) {
				$input['email']['brevo_api_key'] = $existing_key;
			}
			unset( $input['email']['clear_brevo_api_key'] );
		}

		$input = self::deep_merge_options( $existing, $input );

		$clean    = bookingly_default_options();
		$checkbox = static function ( $value ) {
			return ! empty( $value ) ? 1 : 0;
		};
		$cap_text = static function ( $value, $length ) {
			$value = (string) $value;
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
		};

		$layout = $input['layout'] ?? array();
		$spacing = sanitize_key( $layout['section_spacing'] ?? 'comfortable' );
		$corners = sanitize_key( $layout['corner_style'] ?? 'rounded' );
		$motion  = sanitize_key( $layout['motion'] ?? 'system' );
		$clean['layout']['content_width']   = max( 960, min( 1440, absint( $layout['content_width'] ?? 1180 ) ) );
		$clean['layout']['section_spacing'] = in_array( $spacing, array( 'compact', 'comfortable', 'spacious' ), true ) ? $spacing : 'comfortable';
		$clean['layout']['corner_style']    = in_array( $corners, array( 'soft', 'rounded', 'square' ), true ) ? $corners : 'rounded';
		$clean['layout']['motion']          = in_array( $motion, array( 'system', 'reduced' ), true ) ? $motion : 'system';

		$clean['brand']['logo_id']        = absint( $input['brand']['logo_id'] ?? 0 );
		$clean['brand']['footer_logo_id'] = absint( $input['brand']['footer_logo_id'] ?? 0 );
		$clean['brand']['footer_icon']    = sanitize_text_field( $input['brand']['footer_icon'] ?? 'ti-spa' );
		$clean['brand']['show_site_name'] = $checkbox( $input['brand']['show_site_name'] ?? 1 );
		$clean['brand']['site_name']      = sanitize_text_field( $input['brand']['site_name'] ?? '' );

		$clean['header']['show_phone']     = $checkbox( $input['header']['show_phone'] ?? 1 );
		$clean['header']['sticky']         = $checkbox( $input['header']['sticky'] ?? 1 );
		$clean['header']['show_cta']       = $checkbox( $input['header']['show_cta'] ?? 1 );
		$clean['header']['show_mobile_cta'] = $checkbox( $input['header']['show_mobile_cta'] ?? 1 );
		$clean['header']['phone']          = sanitize_text_field( $input['header']['phone'] ?? '' );
		$clean['header']['book_now_label'] = sanitize_text_field( $input['header']['book_now_label'] ?? '' );
		$clean['header']['book_now_url']   = esc_url_raw( $input['header']['book_now_url'] ?? '' );

		$clean['footer']['description']      = sanitize_textarea_field( $input['footer']['description'] ?? '' );
		$clean['footer']['copyright']        = sanitize_text_field( $input['footer']['copyright'] ?? '' );
		$clean['footer']['privacy_url']      = esc_url_raw( $input['footer']['privacy_url'] ?? '' );
		$clean['footer']['terms_url']        = esc_url_raw( $input['footer']['terms_url'] ?? '' );
		$clean['footer']['explore_heading']  = sanitize_text_field( $input['footer']['explore_heading'] ?? '' );
		$clean['footer']['services_heading'] = sanitize_text_field( $input['footer']['services_heading'] ?? '' );
		$clean['footer']['contact_heading']  = sanitize_text_field( $input['footer']['contact_heading'] ?? '' );
		$clean['footer']['show_social']      = $checkbox( $input['footer']['show_social'] ?? 1 );
		$clean['footer']['show_service_links'] = $checkbox( $input['footer']['show_service_links'] ?? 1 );
		$clean['footer']['show_contact']       = $checkbox( $input['footer']['show_contact'] ?? 1 );
		$clean['footer']['services_limit']   = max( 1, min( 12, absint( $input['footer']['services_limit'] ?? 4 ) ) );

		$clean['contact']['email']           = sanitize_email( $input['contact']['email'] ?? '' );
		$clean['contact']['address']         = sanitize_text_field( $input['contact']['address'] ?? '' );
		$map_loading = sanitize_key( $input['contact']['map_loading'] ?? 'immediate' );
		$clean['contact']['map_loading']     = in_array( $map_loading, array( 'immediate', 'consent' ), true ) ? $map_loading : 'immediate';
		$clean['contact']['phone_hours']     = sanitize_text_field( $input['contact']['phone_hours'] ?? '' );
		$clean['contact']['email_note']      = sanitize_text_field( $input['contact']['email_note'] ?? '' );
		$clean['contact']['support_url']     = esc_url_raw( $input['contact']['support_url'] ?? '' );
		$clean['contact']['support_label']   = $cap_text( sanitize_text_field( $input['contact']['support_label'] ?? '' ), 80 );
		$clean['contact']['support_note']    = $cap_text( sanitize_text_field( $input['contact']['support_note'] ?? '' ), 160 );
		$clean['contact']['contact_form_to'] = sanitize_email( $input['contact']['contact_form_to'] ?? '' );
		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) as $weekday ) {
			$clean['contact'][ 'hours_' . $weekday ] = $cap_text( sanitize_text_field( $input['contact'][ 'hours_' . $weekday ] ?? '' ), 60 );
		}

		// Legacy grouped fields are preserved so older saved data still resolves.
		foreach ( array( 'hours_monday', 'hours_tue_fri', 'hours_saturday', 'hours_sunday' ) as $legacy ) {
			$clean['contact'][ $legacy ] = sanitize_text_field( $input['contact'][ $legacy ] ?? '' );
		}

		$email_options = $input['email'] ?? array();
		$provider = sanitize_key( $email_options['provider'] ?? 'wordpress' );
		$clean['email']['provider']              = in_array( $provider, array( 'wordpress', 'brevo' ), true ) ? $provider : 'wordpress';
		$clean['email']['brevo_api_key']         = $cap_text( sanitize_text_field( $email_options['brevo_api_key'] ?? '' ), 512 );
		$clean['email']['sender_name']           = $cap_text( sanitize_text_field( $email_options['sender_name'] ?? '' ), 120 );
		$clean['email']['sender_email']          = sanitize_email( $email_options['sender_email'] ?? '' );
		$clean['email']['customer_confirmation'] = $checkbox( $email_options['customer_confirmation'] ?? 1 );
		$clean['email']['rate_limit_client']    = max( 1, min( 50, absint( $email_options['rate_limit_client'] ?? 5 ) ) );
		$clean['email']['rate_limit_recipient'] = max( 1, min( 50, absint( $email_options['rate_limit_recipient'] ?? 3 ) ) );
		$clean['email']['rate_limit_window']    = max( 1, min( 1440, absint( $email_options['rate_limit_window'] ?? 10 ) ) );
		$clean['email']['admin_subject']         = $cap_text( sanitize_text_field( $email_options['admin_subject'] ?? '' ), 180 );
		$clean['email']['customer_subject']      = $cap_text( sanitize_text_field( $email_options['customer_subject'] ?? '' ), 180 );
		$clean['email']['customer_message']      = $cap_text( sanitize_textarea_field( $email_options['customer_message'] ?? '' ), 12000 );

		$seo = $input['seo'] ?? array();
		$clean['seo']['enable']           = $checkbox( $seo['enable'] ?? 1 );
		$clean['seo']['output_schema']    = $checkbox( $seo['output_schema'] ?? 1 );
		$clean['seo']['noindex_search']   = $checkbox( $seo['noindex_search'] ?? 1 );
		$clean['seo']['home_title']       = $cap_text( sanitize_text_field( $seo['home_title'] ?? '' ), 200 );
		$clean['seo']['home_description'] = $cap_text( sanitize_textarea_field( $seo['home_description'] ?? '' ), 320 );
		$clean['seo']['title_separator']  = $cap_text( sanitize_text_field( $seo['title_separator'] ?? '' ), 8 );
		$clean['seo']['share_image_id']   = absint( $seo['share_image_id'] ?? 0 );
		$clean['seo']['price_range']      = $cap_text( sanitize_text_field( $seo['price_range'] ?? '' ), 40 );
		$clean['seo']['twitter_site']     = $cap_text( preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $seo['twitter_site'] ?? '' ) ), 15 );

		// A non-image attachment would produce a broken sharing card.
		if ( $clean['seo']['share_image_id'] && ! wp_attachment_is_image( $clean['seo']['share_image_id'] ) ) {
			$clean['seo']['share_image_id'] = 0;
		}

		foreach ( array( 'facebook', 'instagram', 'x', 'linkedin' ) as $network ) {
			$clean['social'][ $network ] = esc_url_raw( $input['social'][ $network ] ?? '' );
		}

		$hp = $input['homepage'] ?? array();
		$source = sanitize_key( $hp['source'] ?? 'theme' );
		$clean['homepage']['source']               = in_array( $source, array( 'theme', 'page_content' ), true ) ? $source : 'theme';
		$clean['homepage']['show_trust_strip']     = $checkbox( $hp['show_trust_strip'] ?? 1 );
		$clean['homepage']['show_services']        = $checkbox( $hp['show_services'] ?? 1 );
		$clean['homepage']['show_how']             = $checkbox( $hp['show_how'] ?? 1 );
		$clean['homepage']['show_about']           = $checkbox( $hp['show_about'] ?? 1 );
		$clean['homepage']['show_cta']             = $checkbox( $hp['show_cta'] ?? 1 );
		$clean['homepage']['hero_eyebrow']         = sanitize_text_field( $hp['hero_eyebrow'] ?? '' );
		$clean['homepage']['hero_title_before']    = sanitize_text_field( $hp['hero_title_before'] ?? '' );
		$clean['homepage']['hero_title_emphasis']  = sanitize_text_field( $hp['hero_title_emphasis'] ?? '' );
		$clean['homepage']['hero_title_after']     = sanitize_text_field( $hp['hero_title_after'] ?? '' );
		$clean['homepage']['hero_lead']            = sanitize_textarea_field( $hp['hero_lead'] ?? '' );
		$clean['homepage']['hero_cta_primary']     = sanitize_text_field( $hp['hero_cta_primary'] ?? '' );
		$clean['homepage']['hero_cta_secondary']   = sanitize_text_field( $hp['hero_cta_secondary'] ?? '' );
		$clean['homepage']['hero_image_id']        = absint( $hp['hero_image_id'] ?? 0 );
		$clean['homepage']['hero_badge_text']      = sanitize_text_field( $hp['hero_badge_text'] ?? '' );
		$clean['homepage']['hero_services_limit']  = max( 1, min( 12, absint( $hp['hero_services_limit'] ?? 6 ) ) );
		$clean['homepage']['strip_items']          = sanitize_textarea_field( $hp['strip_items'] ?? '' );
		$clean['homepage']['services_eyebrow']     = sanitize_text_field( $hp['services_eyebrow'] ?? '' );
		$clean['homepage']['services_title']       = sanitize_text_field( $hp['services_title'] ?? '' );
		$clean['homepage']['services_description'] = sanitize_textarea_field( $hp['services_description'] ?? '' );
		$clean['homepage']['services_button']      = sanitize_text_field( $hp['services_button'] ?? '' );
		$clean['homepage']['how_eyebrow']          = sanitize_text_field( $hp['how_eyebrow'] ?? '' );
		$clean['homepage']['how_title']            = sanitize_text_field( $hp['how_title'] ?? '' );
		$clean['homepage']['about_eyebrow']          = sanitize_text_field( $hp['about_eyebrow'] ?? '' );
		$clean['homepage']['about_title']            = sanitize_text_field( $hp['about_title'] ?? '' );
		$clean['homepage']['about_text']             = sanitize_textarea_field( $hp['about_text'] ?? '' );
		$clean['homepage']['about_image_id']         = absint( $hp['about_image_id'] ?? 0 );
		$clean['homepage']['about_pill_value']       = sanitize_text_field( $hp['about_pill_value'] ?? '' );
		$clean['homepage']['about_pill_text']        = sanitize_text_field( $hp['about_pill_text'] ?? '' );
		$clean['homepage']['about_points']           = sanitize_textarea_field( $hp['about_points'] ?? '' );
		$clean['homepage']['about_button']           = sanitize_text_field( $hp['about_button'] ?? '' );
		$clean['homepage']['show_testimonials']      = $checkbox( $hp['show_testimonials'] ?? 1 );
		$clean['homepage']['testimonials_eyebrow']   = sanitize_text_field( $hp['testimonials_eyebrow'] ?? '' );
		$clean['homepage']['testimonials_title']     = sanitize_text_field( $hp['testimonials_title'] ?? '' );
		$clean['homepage']['testimonials_desc']      = sanitize_textarea_field( $hp['testimonials_desc'] ?? '' );
		$clean['homepage']['cta_eyebrow']            = sanitize_text_field( $hp['cta_eyebrow'] ?? '' );
		$clean['homepage']['cta_title']              = sanitize_text_field( $hp['cta_title'] ?? '' );
		$clean['homepage']['cta_button']             = sanitize_text_field( $hp['cta_button'] ?? '' );

		$clean['homepage']['how_steps'] = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$step = $hp['how_steps'][ $i ] ?? array();
			$clean['homepage']['how_steps'][] = array(
				'number' => sanitize_text_field( $step['number'] ?? sprintf( '%02d', $i + 1 ) ),
				'title'  => sanitize_text_field( $step['title'] ?? '' ),
				'text'   => sanitize_textarea_field( $step['text'] ?? '' ),
			);
		}

		$clean['homepage']['stats'] = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$stat = $hp['stats'][ $i ] ?? array();
			$clean['homepage']['stats'][] = array(
				'value'     => sanitize_text_field( $stat['value'] ?? '' ),
				'label'     => sanitize_text_field( $stat['label'] ?? '' ),
				'show_star' => $checkbox( $stat['show_star'] ?? 0 ),
			);
		}

		$clean['homepage']['testimonials'] = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$item = $hp['testimonials'][ $i ] ?? array();
			$clean['homepage']['testimonials'][] = array(
				'quote'     => sanitize_textarea_field( $item['quote'] ?? '' ),
				'name'      => sanitize_text_field( $item['name'] ?? '' ),
				'role'      => sanitize_text_field( $item['role'] ?? '' ),
				'avatar_id' => absint( $item['avatar_id'] ?? 0 ),
				'stars'     => max( 1, min( 5, absint( $item['stars'] ?? 5 ) ) ),
			);
		}

		$about = $input['about'] ?? array();
		$clean['about']['story_eyebrow']    = sanitize_text_field( $about['story_eyebrow'] ?? '' );
		$clean['about']['story_title']      = sanitize_text_field( $about['story_title'] ?? '' );
		$clean['about']['story_text']       = $cap_text( sanitize_textarea_field( $about['story_text'] ?? '' ), 4000 );
		$clean['about']['story_image_1']    = absint( $about['story_image_1'] ?? 0 );
		$clean['about']['story_image_2']    = absint( $about['story_image_2'] ?? 0 );
		$clean['about']['story_image_3']    = absint( $about['story_image_3'] ?? 0 );
		$clean['about']['story_pill_value'] = sanitize_text_field( $about['story_pill_value'] ?? '' );
		$clean['about']['story_pill_text']  = sanitize_text_field( $about['story_pill_text'] ?? '' );
		$clean['about']['values_eyebrow']   = sanitize_text_field( $about['values_eyebrow'] ?? '' );
		$clean['about']['values_title']     = sanitize_text_field( $about['values_title'] ?? '' );
		$clean['about']['team_eyebrow']     = sanitize_text_field( $about['team_eyebrow'] ?? '' );
		$clean['about']['team_title']       = sanitize_text_field( $about['team_title'] ?? '' );
		$clean['about']['show_stats']       = $checkbox( $about['show_stats'] ?? 0 );
		$clean['about']['show_team']        = $checkbox( $about['show_team'] ?? 0 );

		$clean['about']['values'] = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$value = $about['values'][ $i ] ?? array();
			$clean['about']['values'][] = array(
				// Only icons that exist in the sprite survive, so a typo cannot
				// render an empty <use> reference on the front end.
				'icon'  => bookingly_normalize_icon( $value['icon'] ?? '' ),
				'title' => sanitize_text_field( $value['title'] ?? '' ),
				'text'  => sanitize_textarea_field( $value['text'] ?? '' ),
			);
		}

		$clean['about']['stats'] = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$stat = $about['stats'][ $i ] ?? array();
			$clean['about']['stats'][] = array(
				'value' => sanitize_text_field( $stat['value'] ?? '' ),
				'label' => sanitize_text_field( $stat['label'] ?? '' ),
			);
		}

		$clean['about']['team'] = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$member = $about['team'][ $i ] ?? array();
			$clean['about']['team'][] = array(
				'name'     => sanitize_text_field( $member['name'] ?? '' ),
				'role'     => sanitize_text_field( $member['role'] ?? '' ),
				'photo_id' => absint( $member['photo_id'] ?? 0 ),
			);
		}

		$colors = $input['colors'] ?? array();
		$clean['colors']['primary']      = sanitize_hex_color( $colors['primary'] ?? '#2B6E58' ) ?: '#2B6E58';
		$clean['colors']['primary_dark'] = sanitize_hex_color( $colors['primary_dark'] ?? '#1E4F3F' ) ?: '#1E4F3F';
		$clean['colors']['accent']       = sanitize_hex_color( $colors['accent'] ?? '#E7A33E' ) ?: '#E7A33E';
		$clean['colors']['background']   = sanitize_hex_color( $colors['background'] ?? '#F5F7F5' ) ?: '#F5F7F5';

		$blog = $input['blog'] ?? array();
		$clean['blog']['show_featured_image'] = $checkbox( $blog['show_featured_image'] ?? 1 );
		$clean['blog']['eyebrow']          = sanitize_text_field( $blog['eyebrow'] ?? '' );
		$clean['blog']['title']            = sanitize_text_field( $blog['title'] ?? '' );
		$clean['blog']['description']      = sanitize_textarea_field( $blog['description'] ?? '' );
		$clean['blog']['newsletter_eyebrow'] = sanitize_text_field( $blog['newsletter_eyebrow'] ?? '' );
		$clean['blog']['newsletter_title'] = sanitize_text_field( $blog['newsletter_title'] ?? '' );
		$clean['blog']['newsletter_text']  = sanitize_textarea_field( $blog['newsletter_text'] ?? '' );

		$services = $input['services'] ?? array();
		$archive_limit = (int) ( $services['archive_limit'] ?? -1 );
		$clean['services']['show_images']      = $checkbox( $services['show_images'] ?? 1 );
		$clean['services']['show_prices']      = $checkbox( $services['show_prices'] ?? 1 );
		$clean['services']['show_filters']     = $checkbox( $services['show_filters'] ?? 1 );
		$clean['services']['archive_limit']    = -1 === $archive_limit ? -1 : max( 1, min( 48, $archive_limit ) );
		$clean['services']['show_archive_cta'] = $checkbox( $services['show_archive_cta'] ?? 1 );

		$pages = $input['pages'] ?? array();
		$clean['pages']['show_breadcrumbs'] = $checkbox( $pages['show_breadcrumbs'] ?? 1 );

		if ( $clean['brand']['logo_id'] ) {
			set_theme_mod( 'custom_logo', $clean['brand']['logo_id'] );
		}

		return self::merge_clean_preserving_raw( $raw_existing, $clean, bookingly_default_options() );
	}

	/**
	 * Import legacy Customizer mods once if options are empty.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 * @return mixed
	 */
	public static function maybe_migrate_legacy_mods( $value, $old_value ) {
		unset( $old_value );
		if ( get_option( 'bookingly_options_migrated' ) ) {
			return $value;
		}

		$legacy_keys = array(
			'bookingly_site_tagline_short' => array( 'homepage', 'hero_eyebrow' ),
			'bookingly_phone'              => array( 'header', 'phone' ),
			'bookingly_email'              => array( 'contact', 'email' ),
			'bookingly_address'            => array( 'contact', 'address' ),
			'bookingly_footer_desc'        => array( 'footer', 'description' ),
			'bookingly_facebook'           => array( 'social', 'facebook' ),
			'bookingly_instagram'          => array( 'social', 'instagram' ),
			'bookingly_x'                  => array( 'social', 'x' ),
		);

		if ( ! is_array( $value ) ) {
			$value = bookingly_default_options();
		}

		foreach ( $legacy_keys as $mod_key => $path ) {
			$legacy = get_theme_mod( $mod_key, '' );
			if ( $legacy ) {
				$value[ $path[0] ][ $path[1] ] = $legacy;
			}
		}

		update_option( 'bookingly_options_migrated', 1, false );
		return $value;
	}

	/**
	 * Build the non-blocking site readiness cards shown above Theme Options.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function advisory_items() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$booking_active = is_plugin_active( 'service-booking-manager/MPWPB_Plugin.php' )
			|| ( is_multisite() && is_plugin_active_for_network( 'service-booking-manager/MPWPB_Plugin.php' ) );
		$post_type      = bookingly_service_post_type();
		$service_counts = wp_count_posts( $post_type );
		$service_count  = isset( $service_counts->publish ) ? (int) $service_counts->publish : 0;
		$front_id       = (int) get_option( 'page_on_front' );
		$front_ready    = 'page' === get_option( 'show_on_front' ) && $front_id && 'publish' === get_post_status( $front_id );
		$menu_ready     = has_nav_menu( 'primary' );
		$logo_id        = absint( bookingly_option( 'brand.logo_id', get_theme_mod( 'custom_logo', 0 ) ) );
		$logo_ready     = $logo_id && 'attachment' === get_post_type( $logo_id );
		$email          = sanitize_email( bookingly_option( 'contact.email', '' ) );
		$contact_ready  = $email && is_email( $email ) && ! preg_match( '/\.example$/i', $email );
		$email_config   = class_exists( 'Bookingly_Email_Delivery' ) ? Bookingly_Email_Delivery::configuration() : array();
		$email_ready    = ! empty( $email_config['admin_recipient'] )
			&& ( 'brevo' !== ( $email_config['provider'] ?? 'wordpress' ) || ! empty( $email_config['api_key'] ) );
		$build_mode     = function_exists( 'bookingly_get_build_mode' ) ? bookingly_get_build_mode() : 'theme';
		$build_modes    = function_exists( 'bookingly_build_modes' ) ? bookingly_build_modes() : array();
		$build_label    = isset( $build_modes[ $build_mode ]['label'] ) ? $build_modes[ $build_mode ]['label'] : ucwords( str_replace( '_', ' ', $build_mode ) );
		$service_message = $service_count
			? sprintf(
				/* translators: %d: number of published services. */
				_n( '%d service is ready.', '%d services are ready.', $service_count, 'bookingly' ),
				$service_count
			)
			: __( 'Add the first service visitors can book.', 'bookingly' );
		$build_message = sprintf(
			/* translators: %s: selected page editor name. */
			__( 'Current editor: %s.', 'bookingly' ),
			$build_label
		);

		return array(
			array(
				'label'      => __( 'Booking engine', 'bookingly' ),
				'message'    => $booking_active ? __( 'Service Booking Manager is active.', 'bookingly' ) : __( 'Activate the booking engine to publish bookable services.', 'bookingly' ),
				'status'     => $booking_active ? 'ready' : 'attention',
				'url'        => $booking_active ? admin_url( 'edit.php?post_type=' . $post_type ) : admin_url( 'admin.php?page=bookingly-setup' ),
				'link_label' => $booking_active ? __( 'View services', 'bookingly' ) : __( 'Open setup', 'bookingly' ),
			),
			array(
				'label'      => __( 'Published services', 'bookingly' ),
				'message'    => $service_message,
				'status'     => $service_count ? 'ready' : 'attention',
				'url'        => $service_count ? admin_url( 'edit.php?post_type=' . $post_type ) : admin_url( 'post-new.php?post_type=' . $post_type ),
				'link_label' => $service_count ? __( 'Manage services', 'bookingly' ) : __( 'Add service', 'bookingly' ),
			),
			array(
				'label'      => __( 'Front page', 'bookingly' ),
				'message'    => $front_ready ? __( 'A published static front page is assigned.', 'bookingly' ) : __( 'Choose a published static front page.', 'bookingly' ),
				'status'     => $front_ready ? 'ready' : 'attention',
				'url'        => $front_ready ? get_edit_post_link( $front_id, 'raw' ) : admin_url( 'options-reading.php' ),
				'link_label' => $front_ready ? __( 'Edit page', 'bookingly' ) : __( 'Reading settings', 'bookingly' ),
			),
			array(
				'label'      => __( 'Primary menu', 'bookingly' ),
				'message'    => $menu_ready ? __( 'A menu is assigned to the primary location.', 'bookingly' ) : __( 'Assign the site’s main navigation.', 'bookingly' ),
				'status'     => $menu_ready ? 'ready' : 'attention',
				'url'        => admin_url( 'nav-menus.php' ),
				'link_label' => __( 'Manage menus', 'bookingly' ),
			),
			array(
				'label'      => __( 'Logo', 'bookingly' ),
				'message'    => $logo_ready ? __( 'A valid site logo is selected.', 'bookingly' ) : __( 'Add a logo for a complete brand identity.', 'bookingly' ),
				'status'     => $logo_ready ? 'ready' : 'attention',
				'url'        => admin_url( 'customize.php?autofocus[section]=title_tagline' ),
				'link_label' => __( 'Site identity', 'bookingly' ),
			),
			array(
				'label'      => __( 'Contact details', 'bookingly' ),
				'message'    => $contact_ready ? __( 'A public business email is configured.', 'bookingly' ) : __( 'Replace the starter email with your business address.', 'bookingly' ),
				'status'     => $contact_ready ? 'ready' : 'attention',
				'url'        => self::page_url( 'contact' ),
				'link_label' => __( 'Contact options', 'bookingly' ),
			),
			array(
				'label'      => __( 'Email delivery', 'bookingly' ),
				'message'    => $email_ready ? __( 'Contact-form delivery is configured.', 'bookingly' ) : __( 'Complete the Brevo or WordPress email settings.', 'bookingly' ),
				'status'     => $email_ready ? 'ready' : 'attention',
				'url'        => self::page_url( 'email' ),
				'link_label' => __( 'Email options', 'bookingly' ),
			),
			array(
				'label'      => __( 'Build mode', 'bookingly' ),
				'message'    => $build_message,
				'status'     => 'info',
				'url'        => self::page_url( 'builders' ),
				'link_label' => __( 'Builder options', 'bookingly' ),
			),
		);
	}

	/**
	 * Render admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$options     = bookingly_get_options();
		$raw_options = get_option( self::OPTION_KEY, array() );
		$raw_options = is_array( $raw_options ) ? $raw_options : array();
		$requested_section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( ! $requested_section && isset( $_GET['tab'] ) ) {
			$requested_section = sanitize_key( wp_unslash( $_GET['tab'] ) );
		}

		$sections = array(
			'layout'   => array(
				'label'       => __( 'Layout & motion', 'bookingly' ),
				'description' => __( 'Set the site width, vertical rhythm, corner treatment, and motion preference.', 'bookingly' ),
				'callback'    => 'render_layout_tab',
			),
			'brand'    => array(
				'label'       => __( 'Brand & logo', 'bookingly' ),
				'description' => __( 'Set the identity shown in the site header and footer.', 'bookingly' ),
				'callback'    => 'render_brand_tab',
			),
			'header'   => array(
				'label'       => __( 'Header actions', 'bookingly' ),
				'description' => __( 'Control the contact details and booking action in the site header.', 'bookingly' ),
				'callback'    => 'render_header_tab',
			),
			'homepage' => array(
				'label'       => __( 'Homepage', 'bookingly' ),
				'description' => __( 'Choose the homepage source, then edit each built-in homepage section.', 'bookingly' ),
				'callback'    => 'render_homepage_tab',
			),
			'services' => array(
				'label'       => __( 'Services', 'bookingly' ),
				'description' => __( 'Control service cards, filters, archive size, and the archive booking prompt.', 'bookingly' ),
				'callback'    => 'render_services_tab',
			),
			'pages'    => array(
				'label'       => __( 'Pages', 'bookingly' ),
				'description' => __( 'Set presentation details shared by the theme page templates.', 'bookingly' ),
				'callback'    => 'render_pages_tab',
			),
			'about'    => array(
				'label'       => __( 'About page', 'bookingly' ),
				'description' => __( 'Edit the story, values, statistics, and team shown on the About template.', 'bookingly' ),
				'callback'    => 'render_about_tab',
			),
			'contact'  => array(
				'label'       => __( 'Contact details', 'bookingly' ),
				'description' => __( 'Enter the public contact information and opening hours visitors should see.', 'bookingly' ),
				'callback'    => 'render_contact_tab',
			),
			'email'    => array(
				'label'       => __( 'Email delivery', 'bookingly' ),
				'description' => __( 'Configure administrator notifications, customer acknowledgements, and optional Brevo delivery.', 'bookingly' ),
				'callback'    => 'render_email_tab',
			),
			'seo'      => array(
				'label'       => __( 'SEO', 'bookingly' ),
				'description' => __( 'Search titles, descriptions, social sharing cards, and structured data. Everything works automatically; these settings only refine it.', 'bookingly' ),
				'callback'    => 'render_seo_tab',
			),
			'social'   => array(
				'label'       => __( 'Social profiles', 'bookingly' ),
				'description' => __( 'Link only the social profiles your business actively maintains.', 'bookingly' ),
				'callback'    => 'render_social_tab',
			),
			'footer'   => array(
				'label'       => __( 'Footer', 'bookingly' ),
				'description' => __( 'Configure footer copy, legal links, headings, and service limits.', 'bookingly' ),
				'callback'    => 'render_footer_tab',
			),
			'colors'   => array(
				'label'       => __( 'Colors', 'bookingly' ),
				'description' => __( 'Adjust the small set of brand colors used throughout the theme.', 'bookingly' ),
				'callback'    => 'render_colors_tab',
			),
			'blog'     => array(
				'label'       => __( 'Blog', 'bookingly' ),
				'description' => __( 'Edit the blog introduction and the honest RSS follow prompt.', 'bookingly' ),
				'callback'    => 'render_blog_tab',
			),
			'builders' => array(
				'label'       => __( 'Page builders', 'bookingly' ),
				'description' => __( 'Use these Bookingly sections inside Elementor, Divi, the block editor, or any shortcode field.', 'bookingly' ),
				'callback'    => 'render_builders_tab',
			),
		);

		if ( ! isset( $sections[ $requested_section ] ) ) {
			$requested_section = 'layout';
		}

		$setup_complete = class_exists( 'Bookingly_Setup_Wizard' ) && Bookingly_Setup_Wizard::is_setup_complete();
		$advisories     = self::advisory_items();
		?>
		<div class="wrap bookingly-options-wrap" data-start-section="<?php echo esc_attr( $requested_section ); ?>">
			<h1><?php esc_html_e( 'Bookingly Theme Options', 'bookingly' ); ?></h1>
			<section class="bookingly-options-overview <?php echo $setup_complete ? 'is-complete' : 'needs-setup'; ?>" aria-labelledby="bookingly-options-overview-title">
				<span class="bookingly-options-overview-icon dashicons <?php echo $setup_complete ? 'dashicons-yes-alt' : 'dashicons-admin-tools'; ?>" aria-hidden="true"></span>
				<div class="bookingly-options-overview-copy">
					<span class="bookingly-options-overview-kicker">
						<?php echo $setup_complete ? esc_html__( 'Site ready', 'bookingly' ) : esc_html__( 'Setup needs attention', 'bookingly' ); ?>
					</span>
					<h2 id="bookingly-options-overview-title">
						<?php echo $setup_complete ? esc_html__( 'Fine-tune your Bookingly site', 'bookingly' ) : esc_html__( 'Complete the essentials first', 'bookingly' ); ?>
					</h2>
					<p>
						<?php echo $setup_complete ? esc_html__( 'Your required plugins and core pages are ready. Use the sections below to customize your site.', 'bookingly' ) : esc_html__( 'Finish the required plugins and pages, then return here to customize your site.', 'bookingly' ); ?>
					</p>
				</div>
				<a class="button <?php echo $setup_complete ? 'button-secondary' : 'button-primary'; ?>" href="<?php echo esc_url( $setup_complete ? home_url( '/' ) : admin_url( 'admin.php?page=bookingly-setup' ) ); ?>">
					<?php echo $setup_complete ? esc_html__( 'View site', 'bookingly' ) : esc_html__( 'Continue setup', 'bookingly' ); ?>
				</a>
			</section>
			<section class="bookingly-readiness" aria-labelledby="bookingly-readiness-title">
				<div class="bookingly-readiness__heading">
					<div>
						<span class="bookingly-options-overview-kicker"><?php esc_html_e( 'Site checklist', 'bookingly' ); ?></span>
						<h2 id="bookingly-readiness-title"><?php esc_html_e( 'Professional launch essentials', 'bookingly' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'These recommendations do not block setup or change your content.', 'bookingly' ); ?></p>
				</div>
				<div class="bookingly-readiness__grid" role="list">
					<?php foreach ( $advisories as $item ) : ?>
						<article class="bookingly-readiness-card is-<?php echo esc_attr( $item['status'] ); ?>" role="listitem">
							<span class="dashicons <?php echo 'ready' === $item['status'] ? 'dashicons-yes-alt' : ( 'info' === $item['status'] ? 'dashicons-info-outline' : 'dashicons-warning' ); ?>" aria-hidden="true"></span>
							<div>
								<h3><?php echo esc_html( $item['label'] ); ?></h3>
								<p><?php echo esc_html( $item['message'] ); ?></p>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?><span aria-hidden="true"> →</span></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
			<?php settings_errors(); ?>

			<form method="post" action="options.php" class="bookingly-options-form">
				<?php settings_fields( 'bookingly_theme_options_group' ); ?>
				<input type="hidden" name="bookingly_options_revision" value="<?php echo esc_attr( self::options_revision( $raw_options ) ); ?>">
				<div class="bookingly-options-shell">
					<nav class="bookingly-options-navigation" aria-label="<?php esc_attr_e( 'Theme configuration sections', 'bookingly' ); ?>">
						<ol>
							<?php $section_number = 0; ?>
							<?php foreach ( $sections as $slug => $section ) : ?>
								<?php ++$section_number; ?>
								<li>
									<a href="#bookingly-options-<?php echo esc_attr( $slug ); ?>" data-section-target="<?php echo esc_attr( $slug ); ?>" <?php echo $slug === $requested_section ? 'aria-current="step"' : ''; ?>>
										<span><?php echo esc_html( $section_number ); ?></span>
										<?php echo esc_html( $section['label'] ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					</nav>

					<div class="bookingly-options-panels">
						<?php $section_index = 0; ?>
						<?php foreach ( $sections as $slug => $section ) : ?>
							<section id="bookingly-options-<?php echo esc_attr( $slug ); ?>" class="bookingly-options-panel" data-section="<?php echo esc_attr( $slug ); ?>" aria-label="<?php echo esc_attr( $section['label'] ); ?>">
								<p class="bookingly-panel-intro"><?php echo esc_html( $section['description'] ); ?></p>
								<?php call_user_func( array( __CLASS__, $section['callback'] ), $options ); ?>
								<div class="bookingly-options-panel-actions">
									<?php if ( $section_index > 0 ) : ?>
										<button type="button" class="button bookingly-options-previous"><?php esc_html_e( 'Previous section', 'bookingly' ); ?></button>
									<?php endif; ?>
									<?php if ( $section_index < count( $sections ) - 1 ) : ?>
										<button type="button" class="button button-primary bookingly-options-next"><?php esc_html_e( 'Next section', 'bookingly' ); ?></button>
									<?php endif; ?>
								</div>
							</section>
							<?php ++$section_index; ?>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="bookingly-options-savebar">
					<span class="bookingly-options-save-status" aria-live="polite"></span>
					<?php submit_button( __( 'Save Theme Options', 'bookingly' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
			<form id="bookingly-build-mode-form" method="post" action="" hidden>
				<?php wp_nonce_field( 'bookingly_build_mode' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a text field row.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param string $value Current value.
	 * @param string $type  Input type.
	 * @param string $desc  Optional description.
	 */
	private static function field_text( $label, $name, $value, $type = 'text', $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( 'textarea' === $type ) : ?>
					<textarea class="large-text" rows="4" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
				<?php else : ?>
					<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endif; ?>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a bounded whole-number field.
	 *
	 * The same bounds are enforced again on save, so the browser constraints are
	 * a convenience rather than the validation.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param int    $value Current value.
	 * @param int    $min   Lowest accepted value.
	 * @param int    $max   Highest accepted value.
	 * @param string $desc  Optional description.
	 */
	private static function field_number( $label, $name, $value, $min, $max, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" class="small-text" step="1"
					min="<?php echo esc_attr( (string) $min ); ?>"
					max="<?php echo esc_attr( (string) $max ); ?>"
					id="<?php echo esc_attr( $name ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) absint( $value ) ); ?>">
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a write-only secret with explicit clearing.
	 *
	 * @param string $label         Field label.
	 * @param string $name          Secret input name.
	 * @param string $clear_name    Clear-checkbox name.
	 * @param bool   $has_key       Whether a secret is configured.
	 * @param bool   $from_constant Whether wp-config.php owns the secret.
	 */
	private static function field_secret( $label, $name, $clear_name, $has_key, $from_constant ) {
		$field_id = self::field_id( $name );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="password" class="regular-text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo $has_key ? esc_attr__( 'Configured — enter a new key to replace it', 'bookingly' ) : ''; ?>">
				<p class="description">
					<?php
					echo $from_constant
						? esc_html__( 'Configured through BOOKINGLY_BREVO_API_KEY in wp-config.php. That value takes precedence and is never displayed here.', 'bookingly' )
						: ( $has_key ? esc_html__( 'A saved key is configured and is never displayed. Leave this field blank to keep it.', 'bookingly' ) : esc_html__( 'Paste a Brevo transactional API key. It will be stored but never displayed again.', 'bookingly' ) );
					?>
				</p>
				<?php if ( $has_key && ! $from_constant ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $clear_name ); ?>" value="0">
					<label><input type="checkbox" name="<?php echo esc_attr( $clear_name ); ?>" value="1"> <?php esc_html_e( 'Clear the saved Brevo API key', 'bookingly' ); ?></label>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param bool   $value Checked state.
	 */
	private static function field_checkbox( $label, $name, $value ) {
		$field_id = self::field_id( $name );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
				<label for="<?php echo esc_attr( $field_id ); ?>"><input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value, 1 ); ?>> <?php esc_html_e( 'Enabled', 'bookingly' ); ?></label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a select field row.
	 *
	 * @param string               $label   Field label.
	 * @param string               $name    Input name.
	 * @param string               $value   Current value.
	 * @param array<string,string> $choices Allowed values and labels.
	 * @param string               $desc    Optional description.
	 */
	private static function field_select( $label, $name, $value, $choices, $desc = '' ) {
		$field_id = self::field_id( $name );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $choices as $choice => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( (string) $value, (string) $choice ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render media upload field.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param int    $id    Attachment ID.
	 */
	private static function field_media( $label, $name, $id ) {
		$url      = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		$field_id = self::field_id( $name );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<div class="bookingly-media-field" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
					<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $id ); ?>">
					<div class="bookingly-media-preview" aria-live="polite"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php else : ?><span><?php esc_html_e( 'No image selected', 'bookingly' ); ?></span><?php endif; ?></div>
					<button type="button" class="button bookingly-upload" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: image field label. */ __( 'Select image for %s', 'bookingly' ), $label ) ); ?>"><?php esc_html_e( 'Select Image', 'bookingly' ); ?></button>
					<button type="button" class="button bookingly-remove-media" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: image field label. */ __( 'Remove image from %s', 'bookingly' ), $label ) ); ?>" <?php echo $id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'bookingly' ); ?></button>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Return a compact, stable HTML id for a nested option name.
	 *
	 * @param string $name Option field name.
	 * @return string
	 */
	private static function field_id( $name ) {
		return 'bookingly-field-' . substr( md5( $name ), 0, 12 );
	}

	/**
	 * Layout tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_layout_tab( $options ) {
		$layout = $options['layout'];
		$key    = self::OPTION_KEY . '[layout]';
		?>
		<h2><?php esc_html_e( 'Layout & Motion', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Content Width (px)', 'bookingly' ), $key . '[content_width]', $layout['content_width'], 'number', __( 'Allowed range: 960–1440 pixels.', 'bookingly' ) );
			self::field_select(
				__( 'Section Spacing', 'bookingly' ),
				$key . '[section_spacing]',
				$layout['section_spacing'],
				array(
					'compact'     => __( 'Compact', 'bookingly' ),
					'comfortable' => __( 'Comfortable', 'bookingly' ),
					'spacious'    => __( 'Spacious', 'bookingly' ),
				)
			);
			self::field_select(
				__( 'Corner Style', 'bookingly' ),
				$key . '[corner_style]',
				$layout['corner_style'],
				array(
					'soft'    => __( 'Soft', 'bookingly' ),
					'rounded' => __( 'Rounded', 'bookingly' ),
					'square'  => __( 'Square', 'bookingly' ),
				)
			);
			self::field_select(
				__( 'Motion', 'bookingly' ),
				$key . '[motion]',
				$layout['motion'],
				array(
					'system'  => __( 'Follow visitor preference', 'bookingly' ),
					'reduced' => __( 'Always reduce motion', 'bookingly' ),
				),
				__( 'Reduced motion disables non-essential theme transitions and animation.', 'bookingly' )
			);
			?>
		</table>
		<?php
	}

	/**
	 * Brand tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_brand_tab( $options ) {
		$b = $options['brand'];
		?>
		<h2><?php esc_html_e( 'Brand & Logo', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_media( __( 'Header Logo', 'bookingly' ), self::OPTION_KEY . '[brand][logo_id]', (int) $b['logo_id'] );
			self::field_media( __( 'Footer Logo', 'bookingly' ), self::OPTION_KEY . '[brand][footer_logo_id]', (int) $b['footer_logo_id'] );
			self::field_text( __( 'Footer Icon Class', 'bookingly' ), self::OPTION_KEY . '[brand][footer_icon]', $b['footer_icon'], 'text', __( 'Tabler icon class used when no footer logo is set. Example: ti-spa', 'bookingly' ) );
			self::field_text( __( 'Display Name Override', 'bookingly' ), self::OPTION_KEY . '[brand][site_name]', $b['site_name'], 'text', __( 'Leave empty to use the WordPress site title.', 'bookingly' ) );
			self::field_checkbox( __( 'Show Site Name Beside Logo', 'bookingly' ), self::OPTION_KEY . '[brand][show_site_name]', ! empty( $b['show_site_name'] ) );
			?>
		</table>
		<?php
	}

	/**
	 * Header tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_header_tab( $options ) {
		$h = $options['header'];
		?>
		<h2><?php esc_html_e( 'Header Settings', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show Phone Number', 'bookingly' ), self::OPTION_KEY . '[header][show_phone]', ! empty( $h['show_phone'] ) );
			self::field_checkbox( __( 'Sticky Header', 'bookingly' ), self::OPTION_KEY . '[header][sticky]', ! empty( $h['sticky'] ) );
			self::field_checkbox( __( 'Show Booking Button', 'bookingly' ), self::OPTION_KEY . '[header][show_cta]', ! empty( $h['show_cta'] ) );
			self::field_checkbox( __( 'Show Booking Button on Mobile', 'bookingly' ), self::OPTION_KEY . '[header][show_mobile_cta]', ! empty( $h['show_mobile_cta'] ) );
			self::field_text( __( 'Phone Number', 'bookingly' ), self::OPTION_KEY . '[header][phone]', $h['phone'] );
			self::field_text( __( 'Book Now Button Label', 'bookingly' ), self::OPTION_KEY . '[header][book_now_label]', $h['book_now_label'] );
			self::field_text( __( 'Book Now Custom URL', 'bookingly' ), self::OPTION_KEY . '[header][book_now_url]', $h['book_now_url'], 'url', __( 'Leave empty to link to the first published service.', 'bookingly' ) );
			?>
		</table>
		<?php
	}

	/**
	 * Footer tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_footer_tab( $options ) {
		$f = $options['footer'];
		?>
		<h2><?php esc_html_e( 'Footer Settings', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Footer Description', 'bookingly' ), self::OPTION_KEY . '[footer][description]', $f['description'], 'textarea' );
			self::field_text( __( 'Copyright Text', 'bookingly' ), self::OPTION_KEY . '[footer][copyright]', $f['copyright'], 'text', __( 'Leave empty to auto-generate from site name.', 'bookingly' ) );
			self::field_text( __( 'Privacy Policy URL', 'bookingly' ), self::OPTION_KEY . '[footer][privacy_url]', $f['privacy_url'], 'url' );
			self::field_text( __( 'Terms of Service URL', 'bookingly' ), self::OPTION_KEY . '[footer][terms_url]', $f['terms_url'], 'url' );
			self::field_text( __( 'Explore Column Heading', 'bookingly' ), self::OPTION_KEY . '[footer][explore_heading]', $f['explore_heading'] );
			self::field_text( __( 'Services Column Heading', 'bookingly' ), self::OPTION_KEY . '[footer][services_heading]', $f['services_heading'] );
			self::field_text( __( 'Contact Column Heading', 'bookingly' ), self::OPTION_KEY . '[footer][contact_heading]', $f['contact_heading'] );
			self::field_text( __( 'Services List Limit', 'bookingly' ), self::OPTION_KEY . '[footer][services_limit]', $f['services_limit'], 'number' );
			self::field_checkbox( __( 'Show Social Icons', 'bookingly' ), self::OPTION_KEY . '[footer][show_social]', ! empty( $f['show_social'] ) );
			self::field_checkbox( __( 'Show Service Links', 'bookingly' ), self::OPTION_KEY . '[footer][show_service_links]', ! empty( $f['show_service_links'] ) );
			self::field_checkbox( __( 'Show Contact Details', 'bookingly' ), self::OPTION_KEY . '[footer][show_contact]', ! empty( $f['show_contact'] ) );
			?>
		</table>
		<?php
	}

	/**
	 * Contact tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_contact_tab( $options ) {
		$c = $options['contact'];
		?>
		<h2><?php esc_html_e( 'Contact Information', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Public Email', 'bookingly' ), self::OPTION_KEY . '[contact][email]', $c['email'], 'email' );
			self::field_text( __( 'Address', 'bookingly' ), self::OPTION_KEY . '[contact][address]', $c['address'] );
			self::field_select(
				__( 'Google Map Loading', 'bookingly' ),
				self::OPTION_KEY . '[contact][map_loading]',
				$c['map_loading'],
				array(
					'immediate' => __( 'Load the map immediately', 'bookingly' ),
					'consent'   => __( 'Require the visitor to click Load map', 'bookingly' ),
				),
				__( 'Immediate loading shows the map directly. Consent mode avoids contacting Google until the visitor chooses.', 'bookingly' )
			);
			self::field_text( __( 'Phone Availability Note', 'bookingly' ), self::OPTION_KEY . '[contact][phone_hours]', $c['phone_hours'] );
			self::field_text( __( 'Email Response Note', 'bookingly' ), self::OPTION_KEY . '[contact][email_note]', $c['email_note'] );
			?>
		</table>

		<h2><?php esc_html_e( 'Opening Hours', 'bookingly' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'One row per day, so any working week fits — including Sunday to Thursday. Write “Closed” for a non-working day. Days sharing the same hours are grouped automatically on the front end, and the order follows the Week Starts On setting in Settings → General.', 'bookingly' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php
			foreach ( bookingly_business_hours() as $row ) {
				self::field_text(
					$row['label'],
					self::OPTION_KEY . '[contact][hours_' . $row['key'] . ']',
					$row['hours']
				);
			}
			?>
		</table>

		<h2><?php esc_html_e( 'Support Portal', 'bookingly' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Adds a support card to the contact page. Leave the URL empty to hide the card entirely.', 'bookingly' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php
			self::field_text(
				__( 'Support Portal URL', 'bookingly' ),
				self::OPTION_KEY . '[contact][support_url]',
				$c['support_url'],
				'url',
				__( 'Where customers open a ticket. External links open in a new tab automatically.', 'bookingly' )
			);
			self::field_text( __( 'Support Link Label', 'bookingly' ), self::OPTION_KEY . '[contact][support_label]', $c['support_label'] );
			self::field_text( __( 'Support Note', 'bookingly' ), self::OPTION_KEY . '[contact][support_note]', $c['support_note'] );
			?>
		</table>
		<?php
	}

	/**
	 * Email delivery tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_email_tab( $options ) {
		$email    = $options['email'];
		$contact  = $options['contact'];
		$config   = Bookingly_Email_Delivery::configuration();
		$constant = ! empty( $config['api_key_from_constant'] );
		$has_key  = $constant || ! empty( $email['brevo_api_key'] );
		?>
		<h2><?php esc_html_e( 'Contact Form Email Delivery', 'bookingly' ); ?></h2>
		<?php if ( 'brevo' === $email['provider'] && ! $has_key ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Brevo is selected but no API key is configured. Bookingly will use WordPress email until a key is saved.', 'bookingly' ); ?></p></div>
		<?php elseif ( 'brevo' === $email['provider'] ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Brevo is configured. WordPress email remains available as an automatic fallback.', 'bookingly' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'WordPress email is active. Select Brevo below when its sender and API key are ready.', 'bookingly' ); ?></p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<?php
			self::field_select(
				__( 'Delivery Method', 'bookingly' ),
				self::OPTION_KEY . '[email][provider]',
				$email['provider'],
				array(
					'wordpress' => __( 'WordPress email', 'bookingly' ),
					'brevo'     => __( 'Brevo with WordPress fallback', 'bookingly' ),
				),
				__( 'A timeout can rarely cause a duplicate during fallback; transport acceptance does not guarantee inbox delivery.', 'bookingly' )
			);
			self::field_text( __( 'Administrator Recipient', 'bookingly' ), self::OPTION_KEY . '[contact][contact_form_to]', $contact['contact_form_to'], 'email', __( 'Leave empty to use the public email, then the WordPress administrator email.', 'bookingly' ) );
			self::field_text( __( 'Sender Name', 'bookingly' ), self::OPTION_KEY . '[email][sender_name]', $email['sender_name'], 'text', __( 'Leave empty to use the site name.', 'bookingly' ) );
			self::field_text( __( 'Verified Sender Email', 'bookingly' ), self::OPTION_KEY . '[email][sender_email]', $email['sender_email'], 'email', __( 'Brevo requires this sender address or domain to be verified.', 'bookingly' ) );
			self::field_secret(
				__( 'Brevo API Key', 'bookingly' ),
				self::OPTION_KEY . '[email][brevo_api_key]',
				self::OPTION_KEY . '[email][clear_brevo_api_key]',
				$has_key,
				$constant
			);
			self::field_checkbox( __( 'Send Customer Acknowledgement', 'bookingly' ), self::OPTION_KEY . '[email][customer_confirmation]', ! empty( $email['customer_confirmation'] ) );
			self::field_text( __( 'Administrator Subject', 'bookingly' ), self::OPTION_KEY . '[email][admin_subject]', $email['admin_subject'], 'text', __( 'Available placeholders: {name}, {email}, {phone}, {topic}, {message}, {site_name}.', 'bookingly' ) );
			self::field_text( __( 'Customer Subject', 'bookingly' ), self::OPTION_KEY . '[email][customer_subject]', $email['customer_subject'], 'text', __( 'Available placeholders: {name}, {email}, {phone}, {topic}, {message}, {site_name}.', 'bookingly' ) );
			self::field_text( __( 'Customer Message', 'bookingly' ), self::OPTION_KEY . '[email][customer_message]', $email['customer_message'], 'textarea', __( 'Plain text only. The same placeholders are supported.', 'bookingly' ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Spam Throttle', 'bookingly' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Limits how often the contact form can be submitted. A visitor who exceeds either limit is asked to wait until the window passes. Lower values block spam harder; higher values suit busy sites and testing.', 'bookingly' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php
			self::field_number(
				__( 'Submissions per visitor', 'bookingly' ),
				self::OPTION_KEY . '[email][rate_limit_client]',
				$email['rate_limit_client'],
				1,
				50,
				__( 'Counted per IP address. Visitors sharing an office or mobile network share this count.', 'bookingly' )
			);
			self::field_number(
				__( 'Submissions per email address', 'bookingly' ),
				self::OPTION_KEY . '[email][rate_limit_recipient]',
				$email['rate_limit_recipient'],
				1,
				50,
				__( 'Counted per address typed into the form, so one address cannot be flooded from many networks.', 'bookingly' )
			);
			self::field_number(
				__( 'Throttle window (minutes)', 'bookingly' ),
				self::OPTION_KEY . '[email][rate_limit_window]',
				$email['rate_limit_window'],
				1,
				1440,
				__( 'How long both counts are remembered before they reset.', 'bookingly' )
			);
			?>
		</table>
		<?php
	}

	/**
	 * SEO tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_seo_tab( $options ) {
		$seo     = $options['seo'];
		$key     = self::OPTION_KEY . '[seo]';
		$plugin  = Bookingly_Seo::plugin_owns_meta();
		$address = trim( (string) ( $options['contact']['address'] ?? '' ) );
		$local   = '' !== $address && false === stripos( $address, '.example' );
		?>
		<h2><?php esc_html_e( 'Search Engine Optimisation', 'bookingly' ); ?></h2>

		<?php if ( $plugin ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'A dedicated SEO plugin is active, so Bookingly has stood down its titles, descriptions, social cards and schema to avoid duplicate tags. Breadcrumbs and the image speed hints stay active.', 'bookingly' ); ?>
			</p></div>
		<?php elseif ( empty( $seo['enable'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'Bookingly SEO output is switched off. Pages will have no meta description, social cards or structured data unless a plugin provides them.', 'bookingly' ); ?>
			</p></div>
		<?php else : ?>
			<div class="notice notice-success inline"><p>
				<?php
				echo esc_html(
					$local
						? __( 'Bookingly is generating meta descriptions, social cards and LocalBusiness structured data for every page.', 'bookingly' )
						: __( 'Bookingly is generating meta descriptions, social cards and Organization structured data. Add a street address on the Contact tab to upgrade to LocalBusiness and become eligible for map and rich results.', 'bookingly' )
				);
				?>
			</p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Enable Bookingly SEO', 'bookingly' ), $key . '[enable]', ! empty( $seo['enable'] ) );
			self::field_checkbox( __( 'Output Structured Data', 'bookingly' ), $key . '[output_schema]', ! empty( $seo['output_schema'] ) );
			self::field_checkbox( __( 'Hide Search Results From Search Engines', 'bookingly' ), $key . '[noindex_search]', ! empty( $seo['noindex_search'] ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Home Page', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text(
				__( 'Home SEO Title', 'bookingly' ),
				$key . '[home_title]',
				$seo['home_title'],
				'text',
				__( 'Around 70 characters. Leave empty to use the site name and tagline.', 'bookingly' )
			);
			self::field_text(
				__( 'Home Meta Description', 'bookingly' ),
				$key . '[home_description]',
				$seo['home_description'],
				'textarea',
				__( 'Around 160 characters. Leave empty to use the tagline, then the front page content.', 'bookingly' )
			);
			self::field_text(
				__( 'Title Separator', 'bookingly' ),
				$key . '[title_separator]',
				$seo['title_separator'],
				'text',
				__( 'The character between the page title and the site name. Leave empty for the WordPress default.', 'bookingly' )
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Social Sharing', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_media( __( 'Default Sharing Image', 'bookingly' ), $key . '[share_image_id]', (int) $seo['share_image_id'] );
			self::field_text(
				__( 'X (Twitter) Username', 'bookingly' ),
				$key . '[twitter_site]',
				$seo['twitter_site'],
				'text',
				__( 'Used for the Twitter card byline. The @ is optional.', 'bookingly' )
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Business Details', 'bookingly' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Structured data reuses the address, phone and opening hours from the Contact tab. Only the extras below live here.', 'bookingly' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<?php
			self::field_text(
				__( 'Price Range', 'bookingly' ),
				$key . '[price_range]',
				$seo['price_range'],
				'text',
				__( 'A short indicator such as $$ or £20–£60. Optional, but Google shows it for local businesses.', 'bookingly' )
			);
			?>
		</table>
		<?php
	}

	/**
	 * Social tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_social_tab( $options ) {
		$s = $options['social'];
		?>
		<h2><?php esc_html_e( 'Social Profiles', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Facebook URL', 'bookingly' ), self::OPTION_KEY . '[social][facebook]', $s['facebook'], 'url' );
			self::field_text( __( 'Instagram URL', 'bookingly' ), self::OPTION_KEY . '[social][instagram]', $s['instagram'], 'url' );
			self::field_text( __( 'X / Twitter URL', 'bookingly' ), self::OPTION_KEY . '[social][x]', $s['x'], 'url' );
			self::field_text( __( 'LinkedIn URL', 'bookingly' ), self::OPTION_KEY . '[social][linkedin]', $s['linkedin'], 'url' );
			?>
		</table>
		<?php
	}

	/**
	 * Homepage tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_homepage_tab( $options ) {
		$hp = $options['homepage'];
		$key = self::OPTION_KEY . '[homepage]';
		?>
		<h2><?php esc_html_e( 'Homepage — Layout', 'bookingly' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use "Page builder content" to edit the homepage with Elementor, Divi, or the Block Editor instead of the built-in Bookingly sections.', 'bookingly' ); ?></p>
		<table class="form-table" role="presentation">
		<tr>
				<th scope="row"><label for="bookingly-homepage-source"><?php esc_html_e( 'Homepage Source', 'bookingly' ); ?></label></th>
				<td>
					<select id="bookingly-homepage-source" name="<?php echo esc_attr( $key ); ?>[source]">
						<option value="theme" <?php selected( $hp['source'] ?? 'theme', 'theme' ); ?>><?php esc_html_e( 'Bookingly theme sections (default)', 'bookingly' ); ?></option>
						<option value="page_content" <?php selected( $hp['source'] ?? 'theme', 'page_content' ); ?>><?php esc_html_e( 'Front page content (Elementor / Divi / Blocks)', 'bookingly' ); ?></option>
					</select>
				</td>
			</tr>
			<?php
			self::field_checkbox( __( 'Show Trust Strip', 'bookingly' ), $key . '[show_trust_strip]', ! empty( $hp['show_trust_strip'] ) );
			self::field_checkbox( __( 'Show Services', 'bookingly' ), $key . '[show_services]', ! empty( $hp['show_services'] ) );
			self::field_checkbox( __( 'Show How It Works', 'bookingly' ), $key . '[show_how]', ! empty( $hp['show_how'] ) );
			self::field_checkbox( __( 'Show About Preview', 'bookingly' ), $key . '[show_about]', ! empty( $hp['show_about'] ) );
			self::field_checkbox( __( 'Show Final Booking Prompt', 'bookingly' ), $key . '[show_cta]', ! empty( $hp['show_cta'] ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Homepage — Hero', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Eyebrow', 'bookingly' ), $key . '[hero_eyebrow]', $hp['hero_eyebrow'] );
			self::field_text( __( 'Title (before emphasis)', 'bookingly' ), $key . '[hero_title_before]', $hp['hero_title_before'] );
			self::field_text( __( 'Title (emphasis word)', 'bookingly' ), $key . '[hero_title_emphasis]', $hp['hero_title_emphasis'] );
			self::field_text( __( 'Title (after emphasis)', 'bookingly' ), $key . '[hero_title_after]', $hp['hero_title_after'] );
			self::field_text( __( 'Lead Paragraph', 'bookingly' ), $key . '[hero_lead]', $hp['hero_lead'], 'textarea' );
			self::field_text( __( 'Primary Button', 'bookingly' ), $key . '[hero_cta_primary]', $hp['hero_cta_primary'] );
			self::field_text( __( 'Secondary Button', 'bookingly' ), $key . '[hero_cta_secondary]', $hp['hero_cta_secondary'] );
			self::field_media( __( 'Fallback Hero Image', 'bookingly' ), $key . '[hero_image_id]', (int) $hp['hero_image_id'] );
			self::field_text( __( 'Hero Card Badge', 'bookingly' ), $key . '[hero_badge_text]', $hp['hero_badge_text'] );
			self::field_text(
				__( 'Homepage Service Limit', 'bookingly' ),
				$key . '[hero_services_limit]',
				$hp['hero_services_limit'],
				'number',
				__( 'Controls both the five-second hero rotation and the services grid (1–12).', 'bookingly' )
			);
			self::field_text( __( 'Trust Strip Items', 'bookingly' ), $key . '[strip_items]', $hp['strip_items'], 'textarea', __( 'One item per line.', 'bookingly' ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Homepage — Hero Stats', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $hp['stats'] as $i => $stat ) : ?>
				<tr><th colspan="2"><strong><?php /* translators: %d: statistic number. */ printf( esc_html__( 'Stat %d', 'bookingly' ), $i + 1 ); ?></strong></th></tr>
				<?php
				self::field_text( __( 'Value', 'bookingly' ), $key . '[stats][' . $i . '][value]', $stat['value'] );
				self::field_text( __( 'Label', 'bookingly' ), $key . '[stats][' . $i . '][label]', $stat['label'] );
				self::field_checkbox( __( 'Show Star Icon', 'bookingly' ), $key . '[stats][' . $i . '][show_star]', ! empty( $stat['show_star'] ) );
				?>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Homepage — Sections', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Services Eyebrow', 'bookingly' ), $key . '[services_eyebrow]', $hp['services_eyebrow'] );
			self::field_text( __( 'Services Title', 'bookingly' ), $key . '[services_title]', $hp['services_title'] );
			self::field_text( __( 'Services Description', 'bookingly' ), $key . '[services_description]', $hp['services_description'], 'textarea' );
			self::field_text( __( 'Services Button', 'bookingly' ), $key . '[services_button]', $hp['services_button'] );
			self::field_text( __( 'How It Works Eyebrow', 'bookingly' ), $key . '[how_eyebrow]', $hp['how_eyebrow'] );
			self::field_text( __( 'How It Works Title', 'bookingly' ), $key . '[how_title]', $hp['how_title'] );
			?>
		</table>

		<h3><?php esc_html_e( 'How It Works Steps', 'bookingly' ); ?></h3>
		<table class="form-table" role="presentation">
			<?php foreach ( $hp['how_steps'] as $i => $step ) : ?>
				<tr><th colspan="2"><strong><?php /* translators: %d: step number. */ printf( esc_html__( 'Step %d', 'bookingly' ), $i + 1 ); ?></strong></th></tr>
				<?php
				self::field_text( __( 'Number', 'bookingly' ), $key . '[how_steps][' . $i . '][number]', $step['number'] );
				self::field_text( __( 'Title', 'bookingly' ), $key . '[how_steps][' . $i . '][title]', $step['title'] );
				self::field_text( __( 'Description', 'bookingly' ), $key . '[how_steps][' . $i . '][text]', $step['text'], 'textarea' );
				?>
			<?php endforeach; ?>
		</table>

		<h2><?php esc_html_e( 'Homepage — About Preview', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Eyebrow', 'bookingly' ), $key . '[about_eyebrow]', $hp['about_eyebrow'] );
			self::field_text( __( 'Title', 'bookingly' ), $key . '[about_title]', $hp['about_title'] );
			self::field_text( __( 'Text', 'bookingly' ), $key . '[about_text]', $hp['about_text'], 'textarea' );
			self::field_media( __( 'About Image', 'bookingly' ), $key . '[about_image_id]', (int) $hp['about_image_id'] );
			self::field_text( __( 'Pill Value', 'bookingly' ), $key . '[about_pill_value]', $hp['about_pill_value'] );
			self::field_text( __( 'Pill Text', 'bookingly' ), $key . '[about_pill_text]', $hp['about_pill_text'] );
			self::field_text( __( 'Feature Points', 'bookingly' ), $key . '[about_points]', $hp['about_points'], 'textarea', __( 'One point per line.', 'bookingly' ) );
			self::field_text( __( 'Button Label', 'bookingly' ), $key . '[about_button]', $hp['about_button'] );
			?>
		</table>

		<h2><?php esc_html_e( 'Homepage — Testimonials & CTA', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show Testimonials', 'bookingly' ), $key . '[show_testimonials]', ! empty( $hp['show_testimonials'] ) );
			self::field_text( __( 'Testimonials Eyebrow', 'bookingly' ), $key . '[testimonials_eyebrow]', $hp['testimonials_eyebrow'] );
			self::field_text( __( 'Testimonials Title', 'bookingly' ), $key . '[testimonials_title]', $hp['testimonials_title'] );
			self::field_text( __( 'Testimonials Description', 'bookingly' ), $key . '[testimonials_desc]', $hp['testimonials_desc'], 'textarea' );
			self::field_text( __( 'CTA Eyebrow', 'bookingly' ), $key . '[cta_eyebrow]', $hp['cta_eyebrow'] );
			self::field_text( __( 'CTA Title', 'bookingly' ), $key . '[cta_title]', $hp['cta_title'] );
			self::field_text( __( 'CTA Button', 'bookingly' ), $key . '[cta_button]', $hp['cta_button'] );
			?>
		</table>

		<h3><?php esc_html_e( 'Testimonial Cards', 'bookingly' ); ?></h3>
		<table class="form-table" role="presentation">
			<?php foreach ( $hp['testimonials'] as $i => $item ) : ?>
				<tr><th colspan="2"><strong><?php /* translators: %d: testimonial number. */ printf( esc_html__( 'Testimonial %d', 'bookingly' ), $i + 1 ); ?></strong></th></tr>
				<?php
				self::field_text( __( 'Quote', 'bookingly' ), $key . '[testimonials][' . $i . '][quote]', $item['quote'], 'textarea' );
				self::field_text( __( 'Name', 'bookingly' ), $key . '[testimonials][' . $i . '][name]', $item['name'] );
				self::field_text( __( 'Role / Service', 'bookingly' ), $key . '[testimonials][' . $i . '][role]', $item['role'] );
				self::field_media( __( 'Avatar', 'bookingly' ), $key . '[testimonials][' . $i . '][avatar_id]', (int) $item['avatar_id'] );
				self::field_text( __( 'Star Rating (1-5)', 'bookingly' ), $key . '[testimonials][' . $i . '][stars]', $item['stars'], 'number' );
				?>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Services tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_services_tab( $options ) {
		$services = $options['services'];
		$key      = self::OPTION_KEY . '[services]';
		?>
		<h2><?php esc_html_e( 'Service Presentation', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show Service Images', 'bookingly' ), $key . '[show_images]', ! empty( $services['show_images'] ) );
			self::field_checkbox( __( 'Show Service Prices', 'bookingly' ), $key . '[show_prices]', ! empty( $services['show_prices'] ) );
			self::field_checkbox( __( 'Show Category Filters', 'bookingly' ), $key . '[show_filters]', ! empty( $services['show_filters'] ) );
			self::field_text( __( 'Archive Service Limit', 'bookingly' ), $key . '[archive_limit]', $services['archive_limit'], 'number', __( 'Use -1 to show every published service. Otherwise use 1–48.', 'bookingly' ) );
			self::field_checkbox( __( 'Show Archive Booking Prompt', 'bookingly' ), $key . '[show_archive_cta]', ! empty( $services['show_archive_cta'] ) );
			?>
		</table>
		<?php
	}

	/**
	 * Shared page presentation tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_pages_tab( $options ) {
		$pages = $options['pages'];
		?>
		<h2><?php esc_html_e( 'Page Presentation', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php self::field_checkbox( __( 'Show Breadcrumbs', 'bookingly' ), self::OPTION_KEY . '[pages][show_breadcrumbs]', ! empty( $pages['show_breadcrumbs'] ) ); ?>
		</table>
		<?php
	}

	/**
	 * Colors tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_colors_tab( $options ) {
		$c = $options['colors'];
		$key = self::OPTION_KEY . '[colors]';
		?>
		<h2><?php esc_html_e( 'Theme Colors', 'bookingly' ); ?></h2>
		<p class="description"><?php esc_html_e( 'These colors update CSS variables across the entire theme.', 'bookingly' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Primary Color', 'bookingly' ), $key . '[primary]', $c['primary'], 'color' );
			self::field_text( __( 'Primary Dark', 'bookingly' ), $key . '[primary_dark]', $c['primary_dark'], 'color' );
			self::field_text( __( 'Accent Color', 'bookingly' ), $key . '[accent]', $c['accent'], 'color' );
			self::field_text( __( 'Background Color', 'bookingly' ), $key . '[background]', $c['background'], 'color' );
			?>
		</table>
		<?php
	}

	/**
	 * Blog tab.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_blog_tab( $options ) {
		$b = $options['blog'];
		$key = self::OPTION_KEY . '[blog]';
		?>
		<h2><?php esc_html_e( 'Blog Page', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show Featured Image on Single Posts', 'bookingly' ), $key . '[show_featured_image]', ! empty( $b['show_featured_image'] ) );
			self::field_text( __( 'Eyebrow', 'bookingly' ), $key . '[eyebrow]', $b['eyebrow'] );
			self::field_text( __( 'Title', 'bookingly' ), $key . '[title]', $b['title'] );
			self::field_text( __( 'Description', 'bookingly' ), $key . '[description]', $b['description'], 'textarea' );
			self::field_text( __( 'RSS Prompt Eyebrow', 'bookingly' ), $key . '[newsletter_eyebrow]', $b['newsletter_eyebrow'] );
			self::field_text( __( 'RSS Prompt Title', 'bookingly' ), $key . '[newsletter_title]', $b['newsletter_title'] );
			self::field_text( __( 'RSS Prompt Text', 'bookingly' ), $key . '[newsletter_text]', $b['newsletter_text'], 'textarea' );
			?>
		</table>
		<?php
	}

	/**
	 * About page panel.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_about_tab( $options ) {
		$a   = $options['about'];
		$key = self::OPTION_KEY . '[about]';
		?>
		<h2><?php esc_html_e( 'Story', 'bookingly' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Leave the paragraph empty on the About page itself and the page content is used instead.', 'bookingly' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Small label', 'bookingly' ), $key . '[story_eyebrow]', $a['story_eyebrow'] );
			self::field_text( __( 'Title', 'bookingly' ), $key . '[story_title]', $a['story_title'] );
			self::field_text( __( 'Story Paragraphs', 'bookingly' ), $key . '[story_text]', $a['story_text'], 'textarea', __( 'Leave empty to use the About page content instead. Blank lines start a new paragraph.', 'bookingly' ) );
			self::field_media( __( 'Large image', 'bookingly' ), $key . '[story_image_1]', (int) $a['story_image_1'] );
			self::field_media( __( 'Small image, top', 'bookingly' ), $key . '[story_image_2]', (int) $a['story_image_2'] );
			self::field_media( __( 'Small image, bottom', 'bookingly' ), $key . '[story_image_3]', (int) $a['story_image_3'] );
			self::field_text( __( 'Badge number', 'bookingly' ), $key . '[story_pill_value]', $a['story_pill_value'] );
			self::field_text( __( 'Badge caption', 'bookingly' ), $key . '[story_pill_text]', $a['story_pill_text'] );
			?>
		</table>

		<h2><?php esc_html_e( 'Values', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_text( __( 'Small label', 'bookingly' ), $key . '[values_eyebrow]', $a['values_eyebrow'] );
			self::field_text( __( 'Title', 'bookingly' ), $key . '[values_title]', $a['values_title'] );

			foreach ( $a['values'] as $i => $value ) {
				printf(
					'<tr><th colspan="2"><strong>%s</strong></th></tr>',
					esc_html( sprintf( /* translators: %d: card number */ __( 'Card %d', 'bookingly' ), $i + 1 ) )
				);
				self::field_icon( __( 'Icon', 'bookingly' ), $key . '[values][' . $i . '][icon]', $value['icon'] ?? '' );
				self::field_text( __( 'Title', 'bookingly' ), $key . '[values][' . $i . '][title]', $value['title'] ?? '' );
				self::field_text( __( 'Text', 'bookingly' ), $key . '[values][' . $i . '][text]', $value['text'] ?? '', 'textarea' );
			}
			?>
		</table>

		<h2><?php esc_html_e( 'Statistics', 'bookingly' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Publish only figures you can stand behind. Clear a value to drop that statistic.', 'bookingly' ); ?></p>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show the statistics band', 'bookingly' ), $key . '[show_stats]', ! empty( $a['show_stats'] ) );

			foreach ( $a['stats'] as $i => $stat ) {
				printf(
					'<tr><th colspan="2"><strong>%s</strong></th></tr>',
					esc_html( sprintf( /* translators: %d: statistic number */ __( 'Statistic %d', 'bookingly' ), $i + 1 ) )
				);
				self::field_text( __( 'Value', 'bookingly' ), $key . '[stats][' . $i . '][value]', $stat['value'] ?? '' );
				self::field_text( __( 'Label', 'bookingly' ), $key . '[stats][' . $i . '][label]', $stat['label'] ?? '' );
			}
			?>
		</table>

		<h2><?php esc_html_e( 'Team', 'bookingly' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			self::field_checkbox( __( 'Show the team grid', 'bookingly' ), $key . '[show_team]', ! empty( $a['show_team'] ) );
			self::field_text( __( 'Small label', 'bookingly' ), $key . '[team_eyebrow]', $a['team_eyebrow'] );
			self::field_text( __( 'Title', 'bookingly' ), $key . '[team_title]', $a['team_title'] );

			foreach ( $a['team'] as $i => $member ) {
				printf(
					'<tr><th colspan="2"><strong>%s</strong></th></tr>',
					esc_html( sprintf( /* translators: %d: team member number */ __( 'Member %d', 'bookingly' ), $i + 1 ) )
				);
				self::field_text( __( 'Name', 'bookingly' ), $key . '[team][' . $i . '][name]', $member['name'] ?? '' );
				self::field_text( __( 'Role', 'bookingly' ), $key . '[team][' . $i . '][role]', $member['role'] ?? '' );
				self::field_media( __( 'Photo', 'bookingly' ), $key . '[team][' . $i . '][photo_id]', (int) ( $member['photo_id'] ?? 0 ) );
			}
			?>
		</table>
		<?php
	}

	/**
	 * Page builder reference panel.
	 *
	 * @param array<string,mixed> $options Options.
	 */
	private static function render_builders_tab( $options ) {
		unset( $options );

		$elementor = did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
		$divi      = function_exists( 'bookingly_has_divi' ) && bookingly_has_divi();
		?>
		<h2><?php esc_html_e( 'Where Bookingly sections are available', 'bookingly' ); ?></h2>
		<ul class="bookingly-builder-status">
			<li><span class="is-ready"></span><?php esc_html_e( 'Block editor — under the “Bookingly Sections” category.', 'bookingly' ); ?></li>
			<li><span class="is-ready"></span><?php esc_html_e( 'Shortcodes — usable in any builder, widget, or content field.', 'bookingly' ); ?></li>
			<li>
				<span class="<?php echo $elementor ? 'is-ready' : 'is-idle'; ?>"></span>
				<?php
				echo $elementor
					? esc_html__( 'Elementor — active. Widgets appear in the “Bookingly” panel category.', 'bookingly' )
					: esc_html__( 'Elementor — not installed. Widgets register automatically once it is active.', 'bookingly' );
				?>
			</li>
			<li>
				<span class="<?php echo $divi ? 'is-ready' : 'is-idle'; ?>"></span>
				<?php
				echo $divi
					? esc_html__( 'Divi Builder — active. Modules are prefixed “Bookingly”.', 'bookingly' )
					: esc_html__( 'Divi Builder — not installed. Modules register automatically once it is active.', 'bookingly' );
				?>
			</li>
		</ul>

		<h2><?php esc_html_e( 'How pages are built', 'bookingly' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Pick one editor for the whole site. Elementor, the block editor and the theme templates all store layouts differently and none can read another, so mixing them page by page leaves editors opening a page and finding a different editor than they expect.', 'bookingly' ); ?>
			<br>
			<?php esc_html_e( 'Switching is reversible for unchanged Bookingly-generated layouts. Pages with authored content or editor changes are protected and skipped.', 'bookingly' ); ?>
		</p>

		<?php
		$current_mode = bookingly_get_build_mode();
		$modes        = bookingly_build_modes();
		?>

		<div class="bookingly-build-mode">
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Page building mode', 'bookingly' ); ?></legend>
				<?php foreach ( $modes as $key => $mode ) : ?>
					<?php $unavailable = ( 'elementor' === $key && ! $elementor ); ?>
					<label class="bookingly-build-mode__option<?php echo $current_mode === $key ? ' is-current' : ''; ?>">
						<input type="radio" name="bookingly_build_mode" value="<?php echo esc_attr( $key ); ?>" form="bookingly-build-mode-form" <?php checked( $current_mode, $key ); ?> <?php disabled( $unavailable ); ?>>
						<span>
							<strong>
								<?php echo esc_html( $mode['label'] ); ?>
								<?php if ( $current_mode === $key ) : ?>
									<em><?php esc_html_e( '— in use', 'bookingly' ); ?></em>
								<?php endif; ?>
							</strong>
							<span class="description">
								<?php echo esc_html( $mode['description'] ); ?>
								<?php if ( $unavailable ) : ?>
									<br><?php esc_html_e( 'Install and activate Elementor to use this mode.', 'bookingly' ); ?>
								<?php endif; ?>
							</span>
						</span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<p>
				<button type="submit" name="bookingly_build_mode_submit" value="1" form="bookingly-build-mode-form" class="button button-primary">
					<?php esc_html_e( 'Apply to all Bookingly pages', 'bookingly' ); ?>
				</button>
			</p>
		</div>

		<h3><?php esc_html_e( 'Pages', 'bookingly' ); ?></h3>
		<table class="widefat striped bookingly-layout-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Page', 'bookingly' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rendered by', 'bookingly' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Edit', 'bookingly' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( bookingly_managed_pages() as $layout => $page_id ) : ?>
					<?php
					if ( ! get_post( $page_id ) ) {
						continue;
					}

					$is_elementor = bookingly_is_elementor_page( $page_id );
					$has_blocks   = bookingly_has_bookingly_blocks( $page_id );
					?>
					<tr>
						<td><strong><?php echo esc_html( get_the_title( $page_id ) ); ?></strong></td>
						<td>
							<?php
							if ( $is_elementor ) {
								esc_html_e( 'Elementor', 'bookingly' );
							} elseif ( $has_blocks ) {
								esc_html_e( 'Block editor', 'bookingly' );
							} else {
								esc_html_e( 'Theme template', 'bookingly' );
							}
							?>
						</td>
						<td>
							<?php if ( $is_elementor ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $page_id . '&action=elementor' ) ); ?>"><?php esc_html_e( 'Edit in Elementor', 'bookingly' ); ?></a>
							<?php elseif ( $has_blocks ) : ?>
								<a class="button" href="<?php echo esc_url( get_edit_post_link( $page_id, '' ) ); ?>"><?php esc_html_e( 'Edit blocks', 'bookingly' ); ?></a>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Edited in Theme Options', 'bookingly' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Shortcode reference', 'bookingly' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Every section falls back to the values on these settings pages, so a shortcode with no attributes still renders your configured content.', 'bookingly' ); ?></p>
		<table class="widefat striped bookingly-shortcode-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Section', 'bookingly' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'bookingly' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Attributes', 'bookingly' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( bookingly_sections() as $id => $section ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $section['label'] ); ?></strong></td>
						<td><code>[bookingly_<?php echo esc_html( $id ); ?>]</code></td>
						<td>
							<?php
							$keys = array_keys( $section['fields'] );
							echo $keys
								? '<code>' . esc_html( implode( '</code> <code>', $keys ) ) . '</code>'
								: '<em>' . esc_html__( 'None — content comes from Theme Options.', 'bookingly' ) . '</em>';
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Icon picker limited to the icons bundled in the sprite.
	 *
	 * @param string $label Field label.
	 * @param string $name  Input name.
	 * @param string $value Current icon.
	 */
	private static function field_icon( $label, $name, $value ) {
		$id      = self::field_id( $name );
		$current = bookingly_normalize_icon( $value );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( bookingly_icon_names() as $icon ) : ?>
						<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $current, $icon ); ?>>
							<?php echo esc_html( $icon ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Recursively merge submitted values onto existing options.
	 *
	 * @param array<string,mixed> $base     Existing options.
	 * @param array<string,mixed> $incoming Submitted options.
	 * @return array<string,mixed>
	 */
	private static function deep_merge_options( $base, $incoming ) {
		foreach ( $incoming as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::deep_merge_options( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Revision token for stale-form protection.
	 *
	 * @param array<string,mixed> $raw Raw saved options.
	 * @return string
	 */
	private static function options_revision( $raw ) {
		return hash( 'sha256', maybe_serialize( $raw ) );
	}

	/**
	 * Merge sanitized known values while preserving untouched raw values/types.
	 *
	 * @param array<string,mixed> $raw      Raw saved values, including unknown keys.
	 * @param array<string,mixed> $clean    Complete sanitized known values.
	 * @param array<string,mixed> $defaults Complete known defaults.
	 * @param string              $path     Dot path used for typed comparison.
	 * @return array<string,mixed>
	 */
	private static function merge_clean_preserving_raw( $raw, $clean, $defaults, $path = '' ) {
		$result = is_array( $raw ) ? $raw : array();

		foreach ( $clean as $key => $value ) {
			$current_path = '' === $path ? (string) $key : $path . '.' . $key;
			$has_raw      = array_key_exists( $key, $result );
			$has_default  = is_array( $defaults ) && array_key_exists( $key, $defaults );
			$default      = $has_default ? $defaults[ $key ] : null;

			if ( is_array( $value ) ) {
				$raw_child = $has_raw && is_array( $result[ $key ] ) ? $result[ $key ] : array();
				$merged    = self::merge_clean_preserving_raw( $raw_child, $value, is_array( $default ) ? $default : array(), $current_path );
				if ( $has_raw || ! empty( $merged ) ) {
					$result[ $key ] = $merged;
				}
				continue;
			}

			if ( $has_raw ) {
				if ( ! self::typed_values_equivalent( $current_path, $result[ $key ], $value ) ) {
					$result[ $key ] = $value;
				}
			} elseif ( ! $has_default || ! self::typed_values_equivalent( $current_path, $default, $value ) ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Compare stored and submitted values using the declared option type.
	 *
	 * @param string $path   Dot path.
	 * @param mixed  $stored Stored value.
	 * @param mixed  $clean  Sanitized submitted value.
	 * @return bool
	 */
	private static function typed_values_equivalent( $path, $stored, $clean ) {
		$key = (string) substr( strrchr( $path, '.' ) ?: '.' . $path, 1 );
		$boolean_keys = array(
			'show_site_name', 'show_phone', 'sticky', 'show_cta', 'show_mobile_cta',
			'show_social', 'show_service_links', 'show_contact', 'show_trust_strip',
			'show_services', 'show_how', 'show_about', 'show_testimonials', 'show_star',
			'show_stats', 'show_team', 'show_images', 'show_prices', 'show_filters',
			'show_archive_cta', 'show_breadcrumbs', 'show_featured_image', 'customer_confirmation',
			'enable', 'output_schema', 'noindex_search',
		);
		$integer_keys = array(
			'content_width', 'logo_id', 'footer_logo_id', 'services_limit', 'hero_services_limit',
			'hero_image_id', 'about_image_id', 'avatar_id', 'story_image_1', 'story_image_2',
			'story_image_3', 'photo_id', 'stars', 'archive_limit', 'share_image_id',
			'rate_limit_client', 'rate_limit_recipient', 'rate_limit_window',
		);

		if ( in_array( $key, $boolean_keys, true ) ) {
			return (int) ! empty( $stored ) === (int) ! empty( $clean );
		}
		if ( in_array( $key, $integer_keys, true ) ) {
			return (int) $stored === (int) $clean;
		}

		return (string) $stored === (string) $clean;
	}

	/**
	 * Convert hex color to rgba CSS string.
	 *
	 * @param string $hex   Hex color.
	 * @param string $alpha Alpha 0-1.
	 * @return string
	 */
	private static function hex_to_rgba_string( $hex, $alpha ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
	}
}

Bookingly_Theme_Options::init();
