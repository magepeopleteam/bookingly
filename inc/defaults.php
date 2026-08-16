<?php
/**
 * Default theme option values.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the full default options array.
 *
 * @return array<string,mixed>
 */
function bookingly_default_options() {
	return array(
		'layout'   => array(
			'content_width'   => 1180,
			'section_spacing' => 'comfortable',
			'corner_style'    => 'rounded',
			'motion'          => 'system',
		),
		'brand'    => array(
			'logo_id'         => 0,
			'footer_logo_id'  => 0,
			'footer_icon'     => 'ti-spa',
			'show_site_name'  => true,
			'site_name'       => '',
		),
		'header'   => array(
			'show_phone'      => true,
			'sticky'          => true,
			'show_cta'        => true,
			'show_mobile_cta' => true,
			'phone'           => '+1 234 567 890',
			'book_now_label'  => 'Book Now',
			'book_now_url'    => '',
		),
		'footer'   => array(
			'description'       => 'Browse services, review available times, and book online.',
			'copyright'         => '',
			'privacy_url'       => '',
			'terms_url'         => '',
			'explore_heading'   => 'Explore',
			'services_heading'  => 'Services',
			'contact_heading'   => 'Contact',
			'show_social'       => true,
			'show_service_links' => true,
			'show_contact'       => true,
			'services_limit'    => 4,
		),
		'contact'  => array(
			'email'             => 'info@bookingly.example',
			'address'           => '425 6th Ave, New York, NY 10046',
			'map_loading'       => 'immediate',
			'phone_hours'       => 'Mon–Sat, 9am–7pm',
			'email_note'        => 'Replies within 24h',
			'contact_form_to'   => '',
			'support_url'       => '',
			'support_label'     => 'Open a Support Ticket',
			'support_note'      => 'Track your request in the support portal',
			// Per-day so any working week can be described, including Sun–Thu.
			// The legacy hours_monday / hours_tue_fri / hours_saturday /
			// hours_sunday keys are still read as a fallback.
			'hours_mon'         => '11:00 – 21:00',
			'hours_tue'         => '11:00 – 21:00',
			'hours_wed'         => '11:00 – 21:00',
			'hours_thu'         => '11:00 – 21:00',
			'hours_fri'         => '11:00 – 21:00',
			'hours_sat'         => 'Closed',
			'hours_sun'         => 'Closed',
		),
		'email'    => array(
			'provider'              => 'wordpress',
			'brevo_api_key'         => '',
			'sender_name'           => '',
			'sender_email'          => '',
			'customer_confirmation' => true,
			'rate_limit_client'     => 5,
			'rate_limit_recipient'  => 3,
			'rate_limit_window'     => 10,
			'admin_subject'         => 'New message from {name}: {topic}',
			'customer_subject'      => 'We received your message',
			'customer_message'      => "Hello {name},\n\nThank you for contacting {site_name}. We received your message about {topic} and will reply as soon as possible.\n\nKind regards,\n{site_name}",
		),
		'social'   => array(
			'facebook'  => '',
			'instagram' => '',
			'x'         => '',
			'linkedin'  => '',
		),
		'seo'      => array(
			'enable'           => true,
			'output_schema'    => true,
			'noindex_search'   => true,
			'home_title'       => '',
			'home_description' => '',
			'title_separator'  => '',
			'share_image_id'   => 0,
			'twitter_site'     => '',
			'price_range'      => '',
		),
		'homepage' => array(
			'source'                 => 'theme',
			'show_trust_strip'       => true,
			'show_services'          => true,
			'show_how'               => true,
			'show_about'             => true,
			'show_cta'               => true,
			'hero_eyebrow'           => 'Online Booking, Simplified',
			'hero_title_before'      => 'Book a service',
			'hero_title_emphasis'    => 'at a time',
			'hero_title_after'       => 'that works for you.',
			'hero_lead'              => 'Browse the services offered on this site, review the available booking details, and choose your preferred time.',
			'hero_cta_primary'       => 'Check Availability',
			'hero_cta_secondary'     => 'See how it works',
			'hero_image_id'          => 0,
			'hero_badge_text'        => 'Online booking',
			'hero_services_limit'    => 6,
			'strip_items'            => "Browse Services\nReview Details\nChoose a Time\nBook Online",
			'services_eyebrow'       => 'What We Offer',
			'services_title'         => 'Everyday services, booked properly.',
			'services_description'   => 'Browse the published services and open a service to review its current booking details.',
			'services_button'        => 'View all services',
			'how_eyebrow'            => 'Three Steps',
			'how_title'              => 'From search to confirmed slot.',
			'how_steps'              => array(
				array(
					'number' => '01',
					'title'  => 'Pick a service',
					'text'   => 'Review the information and pricing published for each service.',
				),
				array(
					'number' => '02',
					'title'  => 'Choose your slot',
					'text'   => 'Choose from the dates and times currently shown in the booking form.',
				),
				array(
					'number' => '03',
					'title'  => 'Get confirmed',
					'text'   => 'Submit the booking details and follow the confirmation shown by the site.',
				),
			),
			'about_eyebrow'          => 'Why Bookingly',
			'about_title'            => 'Tell visitors what makes your service different.',
			'about_text'             => 'Replace this starter text with a concise, factual introduction to your business, your services, and how your booking process works.',
			'about_image_id'         => 0,
			'about_pill_value'       => 'Your',
			'about_pill_text'        => 'business story',
			'about_points'           => "Clear service information\nPublished booking availability\nSimple online booking\nDirect contact details",
			'about_button'           => 'Meet the team',
			'stats'                  => array(
				array( 'value' => 'Browse', 'label' => 'Service details', 'show_star' => false ),
				array( 'value' => 'Choose', 'label' => 'Available times', 'show_star' => false ),
				array( 'value' => 'Book', 'label' => 'Online', 'show_star' => false ),
			),
			'show_testimonials'      => false,
			'testimonials_eyebrow'   => 'Customer Feedback',
			'testimonials_title'     => 'What customers say.',
			'testimonials_desc'      => 'Enable this section after replacing the empty cards with genuine customer feedback you have permission to publish.',
			'testimonials'           => array(
				array(
					'quote'     => '',
					'name'      => '',
					'role'      => '',
					'avatar_id' => 0,
					'stars'     => 5,
				),
				array(
					'quote'     => '',
					'name'      => '',
					'role'      => '',
					'avatar_id' => 0,
					'stars'     => 5,
				),
				array(
					'quote'     => '',
					'name'      => '',
					'role'      => '',
					'avatar_id' => 0,
					'stars'     => 4,
				),
			),
			'cta_eyebrow'            => 'Ready when you are',
			'cta_title'              => 'Your next appointment is a couple of taps away.',
			'cta_button'             => 'Book an Appointment',
		),
		'about'    => array(
			'story_eyebrow'    => 'Who We Are',
			'story_title'      => 'Founded on one too many missed callbacks.',
			// Used when the About page itself has no content. Blank lines start
			// a new paragraph.
			'story_text'       => '',
			'story_image_1'    => 0,
			'story_image_2'    => 0,
			'story_image_3'    => 0,
			'story_pill_value' => '10+',
			'story_pill_text'  => 'Years serving the neighborhood',
			'values_eyebrow'   => 'What We Stand For',
			'values_title'     => 'The principles behind every booking.',
			'values'           => array(
				array(
					'icon'  => 'shield-check',
					'title' => 'Trust First',
					'text'  => 'Every provider is background-checked before joining the platform.',
				),
				array(
					'icon'  => 'calendar-time',
					'title' => 'Real Availability',
					'text'  => 'What you see is what is actually open — no phantom slots.',
				),
				array(
					'icon'  => 'receipt',
					'title' => 'Honest Pricing',
					'text'  => 'Transparent rates upfront, with no surprise fees at checkout.',
				),
				array(
					'icon'  => 'heart-handshake',
					'title' => 'Real Support',
					'text'  => 'A human team behind the platform, not just a chatbot.',
				),
			),
			'show_stats'       => true,
			'stats'            => array(
				array( 'value' => '12k+', 'label' => 'Bookings Made' ),
				array( 'value' => '340+', 'label' => 'Verified Providers' ),
				array( 'value' => '4.9', 'label' => 'Average Rating' ),
				array( 'value' => '18', 'label' => 'Cities Served' ),
			),
			'team_eyebrow'     => 'Meet The Team',
			'team_title'       => 'The people behind Bookingly.',
			'show_team'        => true,
			'team'             => array(
				array( 'name' => 'Sharmila Rahman', 'role' => 'Founder & CEO', 'photo_id' => 0 ),
				array( 'name' => 'Daniel Osei', 'role' => 'Head of Operations', 'photo_id' => 0 ),
				array( 'name' => 'Emma Laurent', 'role' => 'Provider Success', 'photo_id' => 0 ),
				array( 'name' => 'Marcus Webb', 'role' => 'Customer Support', 'photo_id' => 0 ),
			),
		),
		'colors'   => array(
			'primary'      => '#2B6E58',
			'primary_dark' => '#1E4F3F',
			'accent'       => '#E7A33E',
			'background'   => '#F5F7F5',
		),
		'blog'     => array(
			'show_featured_image' => true,
			'eyebrow'           => 'The Journal',
			'title'             => 'Notes on booking better.',
			'description'       => 'Tips, provider spotlights, and behind-the-scenes on building a calmer way to book everyday services.',
			'newsletter_eyebrow' => 'Stay Updated',
			'newsletter_title'   => 'Follow our latest booking advice.',
			'newsletter_text'    => 'Use your preferred RSS reader to follow new articles as they are published.',
		),
		'services' => array(
			'show_images'      => true,
			'show_prices'      => true,
			'show_filters'     => true,
			'archive_limit'    => -1,
			'show_archive_cta' => true,
		),
		'pages'    => array(
			'show_breadcrumbs' => true,
		),
	);
}

/**
 * Deep-merge saved options with defaults.
 *
 * @param array<string,mixed> $saved Saved options.
 * @return array<string,mixed>
 */
function bookingly_merge_options( $saved ) {
	$defaults = bookingly_default_options();
	$merged   = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

	foreach ( $defaults as $section => $section_defaults ) {
		if ( ! is_array( $section_defaults ) ) {
			continue;
		}
		$merged[ $section ] = wp_parse_args(
			isset( $merged[ $section ] ) && is_array( $merged[ $section ] ) ? $merged[ $section ] : array(),
			$section_defaults
		);
	}

	return $merged;
}

/**
 * Get all theme options (cached per request).
 *
 * @return array<string,mixed>
 */
function bookingly_get_options() {
	static $options = null;

	if ( null === $options ) {
		$options = bookingly_merge_options( get_option( 'bookingly_theme_options', array() ) );
	}

	return $options;
}

/**
 * Get a nested theme option using dot notation.
 *
 * @param string $path    e.g. footer.description
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function bookingly_option( $path, $default = '' ) {
	$options = bookingly_get_options();
	$keys    = explode( '.', $path );
	$value   = $options;

	foreach ( $keys as $key ) {
		if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
			return $default;
		}
		$value = $value[ $key ];
	}

	if ( '' === $value || null === $value ) {
		return $default;
	}

	return $value;
}

/**
 * Backward-compatible accessor for legacy customizer keys.
 *
 * @param string $key     Legacy theme_mod key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function bookingly_mod( $key, $default = '' ) {
	$map = array(
		'bookingly_site_tagline_short' => 'homepage.hero_eyebrow',
		'bookingly_phone'              => 'header.phone',
		'bookingly_email'              => 'contact.email',
		'bookingly_address'            => 'contact.address',
		'bookingly_footer_desc'        => 'footer.description',
		'bookingly_facebook'           => 'social.facebook',
		'bookingly_instagram'          => 'social.instagram',
		'bookingly_x'                  => 'social.x',
	);

	if ( isset( $map[ $key ] ) ) {
		return bookingly_option( $map[ $key ], $default );
	}

	$legacy = get_theme_mod( $key, null );
	if ( null !== $legacy && '' !== $legacy ) {
		return $legacy;
	}

	return $default;
}

/**
 * Render image by attachment ID with fallback URL.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Image size.
 * @param array  $attr          Image attributes.
 * @param string $fallback_url  Fallback image URL.
 */
function bookingly_image( $attachment_id, $size, $attr = array(), $fallback_url = '' ) {
	if ( $attachment_id ) {
		echo wp_get_attachment_image( $attachment_id, $size, false, $attr );
		return;
	}

	if ( $fallback_url ) {
		$alt = isset( $attr['alt'] ) ? $attr['alt'] : '';
		printf(
			'<img src="%1$s" alt="%2$s"%3$s>',
			esc_url( $fallback_url ),
			esc_attr( $alt ),
			isset( $attr['class'] ) ? ' class="' . esc_attr( $attr['class'] ) . '"' : ''
		);
	}
}

/**
 * Convert multiline textarea to trimmed list items.
 *
 * @param string $text Raw textarea value.
 * @return string[]
 */
function bookingly_lines_to_list( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	$lines = array_map( 'trim', $lines );
	return array_values( array_filter( $lines ) );
}

/**
 * Build star string for testimonials.
 *
 * @param int $stars Star count 1-5.
 * @return string
 */
function bookingly_stars( $stars ) {
	$stars = max( 1, min( 5, (int) $stars ) );
	return str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars );
}

/**
 * Resolve book-now URL from options or first service.
 *
 * @return string
 */
function bookingly_get_book_now_url() {
	$custom = bookingly_option( 'header.book_now_url', '' );
	if ( $custom ) {
		return esc_url( $custom );
	}
	return bookingly_get_primary_booking_url();
}
