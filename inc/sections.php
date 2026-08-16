<?php
/**
 * Bookingly section registry.
 *
 * One definition per section, consumed by five different surfaces:
 * theme templates, shortcodes, block editor blocks, Elementor widgets and Divi
 * modules. Markup lives in exactly one place (the render callback) so a fix to
 * a section is a fix everywhere, and a builder never holds a copy of the HTML.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field factory — keeps the schema below readable.
 *
 * @param string              $type  Control type.
 * @param string              $label Human label.
 * @param array<string,mixed> $args  Extra keys (option, default, choices, desc, min, max).
 * @return array<string,mixed>
 */
function bookingly_field( $type, $label, $args = array() ) {
	return wp_parse_args(
		$args,
		array(
			'type'    => $type,
			'label'   => $label,
			'option'  => '',
			'default' => '',
			'choices' => array(),
			'desc'    => '',
			'min'     => 0,
			'max'     => 100,
		)
	);
}

/**
 * Repeating-group field.
 *
 * Each row is a small record described by `fields`. Every builder edits rows
 * independently, so two copies of a section on one page can hold completely
 * different content. When a builder leaves the repeater untouched the value
 * falls back to the list in Theme Options, so an unconfigured block still
 * renders the site's configured content.
 *
 * @param string                           $label     Human label.
 * @param array<string,array<string,mixed>> $subfields Row schema.
 * @param array<string,mixed>              $args      Extra keys (option, max).
 * @return array<string,mixed>
 */
function bookingly_repeater( $label, $subfields, $args = array() ) {
	return wp_parse_args(
		$args,
		array(
			'type'    => 'repeater',
			'label'   => $label,
			'fields'  => $subfields,
			'option'  => '',
			'default' => array(),
			'choices' => array(),
			'desc'    => '',
			'min'     => 0,
			'max'     => 12,
		)
	);
}

/**
 * Icon choices for select controls, keyed by sprite name.
 *
 * @return array<string,string>
 */
function bookingly_icon_choices() {
	$choices = array();

	foreach ( bookingly_icon_names() as $icon ) {
		$choices[ $icon ] = ucwords( str_replace( '-', ' ', $icon ) );
	}

	return $choices;
}

/**
 * All registered sections.
 *
 * Every piece of content a section draws is reachable from the builder that
 * placed it — including repeating rows. Leaving a control empty falls back to
 * Theme Options, so the same block can be "use the site defaults" in one place
 * and fully bespoke in another.
 *
 * @return array<string,array<string,mixed>>
 */
function bookingly_sections() {
	static $sections = null;

	if ( null !== $sections ) {
		return $sections;
	}

	$sections = array(
		'hero'         => array(
			'label'       => __( 'Hero', 'bookingly' ),
			'description' => __( 'Headline, lead paragraph, call-to-action buttons and the featured booking card.', 'bookingly' ),
			'icon'        => 'eicon-banner',
			'fields'      => array(
				'eyebrow'         => bookingly_field( 'text', __( 'Small label above the title', 'bookingly' ), array( 'option' => 'homepage.hero_eyebrow' ) ),
				'title_before'    => bookingly_field( 'text', __( 'Title — first part', 'bookingly' ), array( 'option' => 'homepage.hero_title_before' ) ),
				'title_emphasis'  => bookingly_field( 'text', __( 'Title — highlighted word', 'bookingly' ), array( 'option' => 'homepage.hero_title_emphasis' ) ),
				'title_after'     => bookingly_field( 'text', __( 'Title — last part', 'bookingly' ), array( 'option' => 'homepage.hero_title_after' ) ),
				'lead'            => bookingly_field( 'textarea', __( 'Lead paragraph', 'bookingly' ), array( 'option' => 'homepage.hero_lead' ) ),
				'cta_label'       => bookingly_field( 'text', __( 'Main button label', 'bookingly' ), array( 'option' => 'homepage.hero_cta_primary' ) ),
				'cta_url'         => bookingly_field( 'url', __( 'Main button link', 'bookingly' ), array( 'desc' => __( 'Leave empty to use the Book Now link.', 'bookingly' ) ) ),
				'cta2_label'      => bookingly_field( 'text', __( 'Second button label', 'bookingly' ), array( 'option' => 'homepage.hero_cta_secondary' ) ),
				'cta2_url'        => bookingly_field( 'url', __( 'Second button link', 'bookingly' ), array( 'default' => '#how' ) ),
				'image'           => bookingly_field( 'media', __( 'Fallback image', 'bookingly' ), array( 'option' => 'homepage.hero_image_id' ) ),
				'badge_text'      => bookingly_field( 'text', __( 'Card badge text', 'bookingly' ), array( 'option' => 'homepage.hero_badge_text' ) ),
				'slide_limit'     => bookingly_field( 'number', __( 'Services in hero rotation', 'bookingly' ), array( 'option' => 'homepage.hero_services_limit', 'default' => 6, 'min' => 1, 'max' => 12 ) ),
				'show_stats'      => bookingly_field( 'toggle', __( 'Show trust statistics', 'bookingly' ), array( 'default' => 1 ) ),
				'stats'           => bookingly_repeater(
					__( 'Trust statistics', 'bookingly' ),
					array(
						'value'     => bookingly_field( 'text', __( 'Value', 'bookingly' ) ),
						'label'     => bookingly_field( 'text', __( 'Label', 'bookingly' ) ),
						'show_star' => bookingly_field( 'toggle', __( 'Star icon', 'bookingly' ) ),
					),
					array( 'option' => 'homepage.stats', 'max' => 6 )
				),
				'show_card'       => bookingly_field( 'toggle', __( 'Show booking card', 'bookingly' ), array( 'default' => 1 ) ),
			),
		),
		'trust_strip'  => array(
			'label'       => __( 'Trust Strip', 'bookingly' ),
			'description' => __( 'A single row of short reassurance statements.', 'bookingly' ),
			'icon'        => 'eicon-menu-bar',
			'fields'      => array(
				'items' => bookingly_field( 'textarea', __( 'Items — one per line', 'bookingly' ), array( 'option' => 'homepage.strip_items' ) ),
			),
		),
		'services'     => array(
			'label'       => __( 'Services Grid', 'bookingly' ),
			'description' => __( 'Bookable services pulled live from Service Booking Manager.', 'bookingly' ),
			'icon'        => 'eicon-gallery-grid',
			'fields'      => array(
				'show_head'    => bookingly_field( 'toggle', __( 'Show section heading', 'bookingly' ), array( 'default' => 1 ) ),
				'eyebrow'      => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'homepage.services_eyebrow' ) ),
				'title'        => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'homepage.services_title' ) ),
				'description'  => bookingly_field( 'textarea', __( 'Description', 'bookingly' ), array( 'option' => 'homepage.services_description' ) ),
				'limit'        => bookingly_field( 'number', __( 'How many to show', 'bookingly' ), array( 'option' => 'homepage.hero_services_limit', 'min' => 1, 'max' => 24, 'desc' => __( 'Use -1 to show every service.', 'bookingly' ) ) ),
				'columns'      => bookingly_field( 'select', __( 'Columns', 'bookingly' ), array( 'default' => '3', 'choices' => array( '2' => '2', '3' => '3', '4' => '4' ) ) ),
				'show_filter'  => bookingly_field( 'toggle', __( 'Show category filter', 'bookingly' ), array( 'default' => 0 ) ),
				'show_images'  => bookingly_field( 'toggle', __( 'Show service images', 'bookingly' ), array( 'option' => 'services.show_images', 'default' => 1 ) ),
				'show_prices'  => bookingly_field( 'toggle', __( 'Show service prices', 'bookingly' ), array( 'option' => 'services.show_prices', 'default' => 1 ) ),
				'show_button'  => bookingly_field( 'toggle', __( 'Show "view all" button', 'bookingly' ), array( 'default' => 1 ) ),
				'button_label' => bookingly_field( 'text', __( 'Button label', 'bookingly' ), array( 'option' => 'homepage.services_button' ) ),
				'button_url'   => bookingly_field( 'url', __( 'Button link', 'bookingly' ), array( 'desc' => __( 'Leave empty to link to the Services page.', 'bookingly' ) ) ),
			),
		),
		'how_it_works' => array(
			'label'       => __( 'How It Works', 'bookingly' ),
			'description' => __( 'The three numbered steps. Step content is edited in Theme Options.', 'bookingly' ),
			'icon'        => 'eicon-number-field',
			'fields'      => array(
				'eyebrow' => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'homepage.how_eyebrow' ) ),
				'title'   => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'homepage.how_title' ) ),
				'tint'    => bookingly_field( 'toggle', __( 'Tinted background', 'bookingly' ), array( 'default' => 1 ) ),
				'steps'   => bookingly_repeater(
					__( 'Steps', 'bookingly' ),
					array(
						'number' => bookingly_field( 'text', __( 'Number', 'bookingly' ) ),
						'title'  => bookingly_field( 'text', __( 'Title', 'bookingly' ) ),
						'text'   => bookingly_field( 'textarea', __( 'Description', 'bookingly' ) ),
					),
					array( 'option' => 'homepage.how_steps', 'max' => 6 )
				),
			),
		),
		'about'        => array(
			'label'       => __( 'About Preview', 'bookingly' ),
			'description' => __( 'Image with a badge beside a short story and feature points.', 'bookingly' ),
			'icon'        => 'eicon-image-box',
			'fields'      => array(
				'eyebrow'      => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'homepage.about_eyebrow' ) ),
				'title'        => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'homepage.about_title' ) ),
				'text'         => bookingly_field( 'textarea', __( 'Paragraph', 'bookingly' ), array( 'option' => 'homepage.about_text' ) ),
				'image'        => bookingly_field( 'media', __( 'Image', 'bookingly' ), array( 'option' => 'homepage.about_image_id' ) ),
				'pill_value'   => bookingly_field( 'text', __( 'Badge number', 'bookingly' ), array( 'option' => 'homepage.about_pill_value' ) ),
				'pill_text'    => bookingly_field( 'text', __( 'Badge caption', 'bookingly' ), array( 'option' => 'homepage.about_pill_text' ) ),
				'points'       => bookingly_field( 'textarea', __( 'Feature points — one per line', 'bookingly' ), array( 'option' => 'homepage.about_points' ) ),
				'button_label' => bookingly_field( 'text', __( 'Button label', 'bookingly' ), array( 'option' => 'homepage.about_button' ) ),
				'button_url'   => bookingly_field( 'url', __( 'Button link', 'bookingly' ), array( 'desc' => __( 'Leave empty to link to the About page.', 'bookingly' ) ) ),
			),
		),
		'testimonials' => array(
			'label'       => __( 'Testimonials', 'bookingly' ),
			'description' => __( 'Customer quotes on a dark band. Quotes are edited in Theme Options.', 'bookingly' ),
			'icon'        => 'eicon-testimonial',
			'fields'      => array(
				'eyebrow'     => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'homepage.testimonials_eyebrow' ) ),
				'title'       => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'homepage.testimonials_title' ) ),
				'description' => bookingly_field( 'textarea', __( 'Description', 'bookingly' ), array( 'option' => 'homepage.testimonials_desc' ) ),
				'items'       => bookingly_repeater(
					__( 'Testimonials', 'bookingly' ),
					array(
						'quote'     => bookingly_field( 'textarea', __( 'Quote', 'bookingly' ) ),
						'name'      => bookingly_field( 'text', __( 'Name', 'bookingly' ) ),
						'role'      => bookingly_field( 'text', __( 'Role', 'bookingly' ) ),
						'avatar_id' => bookingly_field( 'media', __( 'Photo', 'bookingly' ) ),
						'stars'     => bookingly_field( 'number', __( 'Stars', 'bookingly' ), array( 'min' => 1, 'max' => 5, 'default' => 5 ) ),
					),
					array( 'option' => 'homepage.testimonials', 'max' => 12 )
				),
			),
		),
		'cta_band'     => array(
			'label'       => __( 'Call To Action Band', 'bookingly' ),
			'description' => __( 'A wide highlighted band with one button.', 'bookingly' ),
			'icon'        => 'eicon-call-to-action',
			'fields'      => array(
				'eyebrow'      => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'homepage.cta_eyebrow' ) ),
				'title'        => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'homepage.cta_title' ) ),
				'button_label' => bookingly_field( 'text', __( 'Button label', 'bookingly' ), array( 'option' => 'homepage.cta_button' ) ),
				'button_url'   => bookingly_field( 'url', __( 'Button link', 'bookingly' ), array( 'desc' => __( 'Leave empty to use the Book Now link.', 'bookingly' ) ) ),
			),
		),
		'stats'        => array(
			'label'       => __( 'Statistics Band', 'bookingly' ),
			'description' => __( 'Large numbers on a dark band.', 'bookingly' ),
			'icon'        => 'eicon-counter',
			'fields'      => array(
				'items' => bookingly_repeater(
					__( 'Statistics', 'bookingly' ),
					array(
						'value' => bookingly_field( 'text', __( 'Value', 'bookingly' ) ),
						'label' => bookingly_field( 'text', __( 'Label', 'bookingly' ) ),
					),
					array( 'option' => 'about.stats', 'max' => 8 )
				),
			),
		),
		'value_props'  => array(
			'label'       => __( 'Value Cards', 'bookingly' ),
			'description' => __( 'Four icon cards describing what the business stands for.', 'bookingly' ),
			'icon'        => 'eicon-icon-box',
			'fields'      => array(
				'eyebrow' => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'about.values_eyebrow' ) ),
				'title'   => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'about.values_title' ) ),
				'tint'    => bookingly_field( 'toggle', __( 'Tinted background', 'bookingly' ), array( 'default' => 1 ) ),
				'items'   => bookingly_repeater(
					__( 'Cards', 'bookingly' ),
					array(
						'icon'  => bookingly_field( 'select', __( 'Icon', 'bookingly' ), array( 'choices' => bookingly_icon_choices(), 'default' => 'shield-check' ) ),
						'title' => bookingly_field( 'text', __( 'Title', 'bookingly' ) ),
						'text'  => bookingly_field( 'textarea', __( 'Text', 'bookingly' ) ),
					),
					array( 'option' => 'about.values', 'max' => 8 )
				),
			),
		),
		'team'         => array(
			'label'       => __( 'Team Grid', 'bookingly' ),
			'description' => __( 'Staff photos and roles. Members are edited in Theme Options.', 'bookingly' ),
			'icon'        => 'eicon-person',
			'fields'      => array(
				'eyebrow' => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'about.team_eyebrow' ) ),
				'title'   => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'about.team_title' ) ),
				'items'   => bookingly_repeater(
					__( 'Members', 'bookingly' ),
					array(
						'name'     => bookingly_field( 'text', __( 'Name', 'bookingly' ) ),
						'role'     => bookingly_field( 'text', __( 'Role', 'bookingly' ) ),
						'photo_id' => bookingly_field( 'media', __( 'Photo', 'bookingly' ) ),
					),
					array( 'option' => 'about.team', 'max' => 12 )
				),
			),
		),
		'story'        => array(
			'label'       => __( 'Story', 'bookingly' ),
			'description' => __( 'Three stacked images beside a longer story. Uses the page content when present.', 'bookingly' ),
			'icon'        => 'eicon-image-before-after',
			'fields'      => array(
				'eyebrow'    => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'about.story_eyebrow' ) ),
				'title'      => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'about.story_title' ) ),
				'text'       => bookingly_field( 'textarea', __( 'Paragraph', 'bookingly' ), array( 'option' => 'about.story_text', 'desc' => __( 'Leave empty to use the page content. Blank lines start a new paragraph.', 'bookingly' ) ) ),
				'image_1'    => bookingly_field( 'media', __( 'Large image', 'bookingly' ), array( 'option' => 'about.story_image_1' ) ),
				'image_2'    => bookingly_field( 'media', __( 'Small image, top', 'bookingly' ), array( 'option' => 'about.story_image_2' ) ),
				'image_3'    => bookingly_field( 'media', __( 'Small image, bottom', 'bookingly' ), array( 'option' => 'about.story_image_3' ) ),
				'pill_value' => bookingly_field( 'text', __( 'Badge number', 'bookingly' ), array( 'option' => 'about.story_pill_value' ) ),
				'pill_text'  => bookingly_field( 'text', __( 'Badge caption', 'bookingly' ), array( 'option' => 'about.story_pill_text' ) ),
			),
		),
		'page_hero'    => array(
			'label'       => __( 'Page Hero', 'bookingly' ),
			'description' => __( 'Centred page title with an optional breadcrumb.', 'bookingly' ),
			'icon'        => 'eicon-post-title',
			'fields'      => array(
				'eyebrow'    => bookingly_field( 'text', __( 'Small label', 'bookingly' ) ),
				'title'      => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'desc' => __( 'Leave empty to use the page title.', 'bookingly' ) ) ),
				'text'       => bookingly_field( 'textarea', __( 'Intro text', 'bookingly' ), array( 'desc' => __( 'Leave empty to use the page excerpt.', 'bookingly' ) ) ),
				'show_crumb' => bookingly_field( 'toggle', __( 'Show breadcrumb', 'bookingly' ), array( 'option' => 'pages.show_breadcrumbs', 'default' => 1 ) ),
			),
		),
		'contact_cards' => array(
			'label'       => __( 'Contact Cards', 'bookingly' ),
			'description' => __( 'Phone, email, address and support-portal cards. Leave a field empty to use the Theme Options value.', 'bookingly' ),
			'icon'        => 'eicon-icon-box',
			'fields'      => array(
				'phone'         => bookingly_field( 'text', __( 'Phone', 'bookingly' ), array( 'option' => 'header.phone' ) ),
				'phone_note'    => bookingly_field( 'text', __( 'Phone note', 'bookingly' ), array( 'option' => 'contact.phone_hours' ) ),
				'email'         => bookingly_field( 'text', __( 'Email', 'bookingly' ), array( 'option' => 'contact.email' ) ),
				'email_note'    => bookingly_field( 'text', __( 'Email note', 'bookingly' ), array( 'option' => 'contact.email_note' ) ),
				'address'       => bookingly_field( 'text', __( 'Address', 'bookingly' ), array( 'option' => 'contact.address' ) ),
				'support_url'   => bookingly_field( 'text', __( 'Support portal URL', 'bookingly' ), array( 'option' => 'contact.support_url' ) ),
				'support_label' => bookingly_field( 'text', __( 'Support link label', 'bookingly' ), array( 'option' => 'contact.support_label' ) ),
				'support_note'  => bookingly_field( 'text', __( 'Support note', 'bookingly' ), array( 'option' => 'contact.support_note' ) ),
			),
		),
		'contact_form' => array(
			'label'       => __( 'Contact Form', 'bookingly' ),
			'description' => __( 'Accessible enquiry form that emails the address set in Theme Options.', 'bookingly' ),
			'icon'        => 'eicon-form-horizontal',
			'fields'      => array(
				'title'    => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'default' => 'Send us a message' ) ),
				'subtitle' => bookingly_field( 'text', __( 'Subtitle', 'bookingly' ), array( 'default' => 'Fill out the form and our team will get back to you shortly.' ) ),
				'topics'   => bookingly_field( 'textarea', __( 'Topic choices — one per line', 'bookingly' ), array( 'default' => "General Inquiry\nBooking Support\nBecome a Provider\nFeedback" ) ),
			),
		),
		'business_hours' => array(
			'label'       => __( 'Business Hours', 'bookingly' ),
			'description' => __( 'Opening hours from Theme Options.', 'bookingly' ),
			'icon'        => 'eicon-clock-o',
			'fields'      => array(
				'title' => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'default' => 'Business Hours' ) ),
			),
		),
		'map'          => array(
			'label'       => __( 'Location Map', 'bookingly' ),
			'description' => __( 'Google Maps embed with an optional visitor privacy prompt.', 'bookingly' ),
			'icon'        => 'eicon-google-maps',
			'fields'      => array(
				'address' => bookingly_field( 'text', __( 'Address', 'bookingly' ), array( 'option' => 'contact.address' ) ),
				'consent' => bookingly_field(
					'inherit_select',
					__( 'Map loading', 'bookingly' ),
					array(
						'default' => 'inherit',
						'choices' => array(
							'inherit'   => __( 'Use Theme Options', 'bookingly' ),
							'consent'   => __( 'Require visitor click', 'bookingly' ),
							'immediate' => __( 'Load immediately', 'bookingly' ),
						),
						'desc'    => __( 'Google receives visitor data when the map loads.', 'bookingly' ),
					)
				),
			),
		),
		'blog_posts'   => array(
			'label'       => __( 'Blog Posts', 'bookingly' ),
			'description' => __( 'Latest posts as cards, with an optional large featured post.', 'bookingly' ),
			'icon'        => 'eicon-posts-grid',
			'fields'      => array(
				'eyebrow'       => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'blog.eyebrow' ) ),
				'title'         => bookingly_field( 'text', __( 'Title', 'bookingly' ) ),
				'limit'         => bookingly_field( 'number', __( 'How many posts', 'bookingly' ), array( 'default' => 6, 'min' => 1, 'max' => 24 ) ),
				'columns'       => bookingly_field( 'select', __( 'Columns', 'bookingly' ), array( 'default' => '3', 'choices' => array( '2' => '2', '3' => '3', '4' => '4' ) ) ),
				'show_featured' => bookingly_field( 'toggle', __( 'Highlight the newest post', 'bookingly' ), array( 'default' => 0 ) ),
			),
		),
		'newsletter'   => array(
			'label'       => __( 'Follow Band', 'bookingly' ),
			'description' => __( 'Invites readers to subscribe to the site feed.', 'bookingly' ),
			'icon'        => 'eicon-email-field',
			'fields'      => array(
				'eyebrow' => bookingly_field( 'text', __( 'Small label', 'bookingly' ), array( 'option' => 'blog.newsletter_eyebrow' ) ),
				'title'   => bookingly_field( 'text', __( 'Title', 'bookingly' ), array( 'option' => 'blog.newsletter_title' ) ),
				'text'    => bookingly_field( 'textarea', __( 'Description', 'bookingly' ), array( 'option' => 'blog.newsletter_text' ) ),
				'label'   => bookingly_field( 'text', __( 'Button label', 'bookingly' ), array( 'default' => 'Subscribe via RSS' ) ),
			),
		),
	);

	/**
	 * Filter the registered Bookingly sections.
	 *
	 * @param array<string,array<string,mixed>> $sections Section definitions.
	 */
	$sections = apply_filters( 'bookingly_sections', $sections );

	return $sections;
}

/**
 * Fetch one section definition.
 *
 * @param string $id Section id.
 * @return array<string,mixed>|null
 */
function bookingly_get_section( $id ) {
	$sections = bookingly_sections();
	return isset( $sections[ $id ] ) ? $sections[ $id ] : null;
}

/**
 * Resolve a section's attributes: supplied value -> theme option -> default.
 *
 * An empty string from a builder means "not set", so it falls through to the
 * theme option. That is what lets an editor drop a widget with blank controls
 * and still get the site's configured content.
 *
 * @param string              $id   Section id.
 * @param array<string,mixed> $atts Supplied attributes.
 * @return array<string,mixed>
 */
function bookingly_section_atts( $id, $atts = array() ) {
	$section = bookingly_get_section( $id );
	if ( ! $section ) {
		return array();
	}

	$resolved = array();

	foreach ( $section['fields'] as $key => $field ) {
		$supplied = isset( $atts[ $key ] ) ? $atts[ $key ] : null;

		if ( 'repeater' === $field['type'] ) {
			$resolved[ $key ] = bookingly_resolve_repeater( $field, $supplied );
			continue;
		}

		if ( 'toggle' === $field['type'] ) {
			// A toggle must distinguish "off" from "absent".
			$resolved[ $key ] = ( null === $supplied || '' === $supplied )
				? (int) ( $field['option'] ? bookingly_option( $field['option'], $field['default'] ) : $field['default'] )
				: (int) ( 'false' !== $supplied && '0' !== (string) $supplied && $supplied );
			continue;
		}

		if ( null !== $supplied && '' !== $supplied ) {
			$resolved[ $key ] = $supplied;
			continue;
		}

		$resolved[ $key ] = $field['option']
			? bookingly_option( $field['option'], $field['default'] )
			: $field['default'];
	}

	// Pass through wrapper controls that every section understands.
	foreach ( array( 'class', 'anchor', 'spacing' ) as $passthrough ) {
		if ( ! empty( $atts[ $passthrough ] ) ) {
			$resolved[ $passthrough ] = $atts[ $passthrough ];
		}
	}

	/**
	 * Filter a section's resolved attributes.
	 *
	 * @param array<string,mixed> $resolved Resolved attributes.
	 * @param string              $id       Section id.
	 * @param array<string,mixed> $atts     Raw attributes.
	 */
	return apply_filters( 'bookingly_section_atts', $resolved, $id, $atts );
}

/**
 * Resolve a repeater value from whatever a builder supplied.
 *
 * Blocks and Elementor hand over a real array of rows; a shortcode can only
 * carry a JSON string. Anything empty falls back to the Theme Options list so
 * an untouched section still renders the site's content.
 *
 * @param array<string,mixed> $field    Field definition.
 * @param mixed               $supplied Supplied value.
 * @return array<int,array<string,mixed>>
 */
function bookingly_resolve_repeater( $field, $supplied ) {
	if ( is_string( $supplied ) && '' !== trim( $supplied ) ) {
		$decoded  = json_decode( $supplied, true );
		$supplied = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : null;
	}

	if ( ! is_array( $supplied ) || empty( $supplied ) ) {
		$supplied = $field['option']
			? bookingly_option( $field['option'], $field['default'] )
			: $field['default'];
	}

	if ( ! is_array( $supplied ) ) {
		return array();
	}

	$rows = array();

	foreach ( $supplied as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$clean = array();

		foreach ( $field['fields'] as $sub_key => $sub ) {
			$value = $row[ $sub_key ] ?? null;

			switch ( $sub['type'] ) {
				case 'media':
					// Elementor sends { id, url }; blocks send a bare id.
					$clean[ $sub_key ] = is_array( $value )
						? absint( $value['id'] ?? 0 )
						: absint( $value );
					break;

				case 'number':
					$clean[ $sub_key ] = ( null === $value || '' === $value )
						? (int) $sub['default']
						: (int) $value;
					break;

				case 'toggle':
					$clean[ $sub_key ] = ( 'yes' === $value || 'on' === $value || ( ! empty( $value ) && 'false' !== $value && '0' !== (string) $value ) ) ? 1 : 0;
					break;

				default:
					$clean[ $sub_key ] = ( null === $value ) ? '' : $value;
					break;
			}
		}

		$rows[] = $clean;
	}

	if ( $field['max'] ) {
		$rows = array_slice( $rows, 0, (int) $field['max'] );
	}

	return $rows;
}

/**
 * Render a section to a string.
 *
 * @param string              $id   Section id.
 * @param array<string,mixed> $atts Attributes.
 * @return string
 */
function bookingly_get_section_html( $id, $atts = array() ) {
	$section  = bookingly_get_section( $id );
	$callback = 'bookingly_section_' . str_replace( '-', '_', $id );

	if ( ! $section || ! function_exists( $callback ) ) {
		return '';
	}

	$resolved = bookingly_section_atts( $id, $atts );

	ob_start();
	call_user_func( $callback, $resolved );
	$html = ob_get_clean();

	/**
	 * Filter a rendered section's HTML.
	 *
	 * @param string              $html     Markup.
	 * @param string              $id       Section id.
	 * @param array<string,mixed> $resolved Resolved attributes.
	 */
	return apply_filters( 'bookingly_section_html', $html, $id, $resolved );
}

/**
 * Echo a section.
 *
 * @param string              $id   Section id.
 * @param array<string,mixed> $atts Attributes.
 */
function bookingly_section( $id, $atts = array() ) {
	echo bookingly_get_section_html( $id, $atts ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped inside the renderer.
}

/**
 * Build the attribute string for a section wrapper.
 *
 * @param array<string,mixed> $atts    Resolved attributes.
 * @param string              $classes Base classes.
 * @return string
 */
function bookingly_section_wrapper_attrs( $atts, $classes ) {
	$classes = trim( $classes . ' ' . ( isset( $atts['class'] ) ? $atts['class'] : '' ) );
	$out     = ' class="' . esc_attr( $classes ) . '"';

	if ( ! empty( $atts['anchor'] ) ) {
		$out .= ' id="' . esc_attr( sanitize_title( $atts['anchor'] ) ) . '"';
	}

	return $out;
}
