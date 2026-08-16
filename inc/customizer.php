<?php
/**
 * Bookingly Customizer — quick links to Theme Options.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register a Customizer panel that points admins to Bookingly Options.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function bookingly_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'bookingly_panel',
		array(
			'title'       => __( 'Bookingly Theme', 'bookingly' ),
			'description' => __( 'Logo, footer, homepage, contact, and colors are managed in Bookingly Theme Options for a cleaner admin experience.', 'bookingly' ),
			'priority'    => 30,
		)
	);

	$wp_customize->add_section(
		'bookingly_options_link',
		array(
			'title'    => __( 'Theme Options', 'bookingly' ),
			'panel'    => 'bookingly_panel',
			'priority' => 1,
		)
	);

	$wp_customize->add_setting(
		'bookingly_options_notice',
		array(
			'sanitize_callback' => '__return_empty_string',
		)
	);

	if ( class_exists( 'WP_Customize_Control' ) ) {
		/**
		 * Custom control linking to the options page.
		 */
		class Bookingly_Options_Link_Control extends WP_Customize_Control {
			public function render_content() {
				$url = Bookingly_Theme_Options::page_url();
				?>
				<p><?php esc_html_e( 'Manage logos, header, footer, homepage sections, testimonials, contact details, social links, and brand colors.', 'bookingly' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open Bookingly Theme Options', 'bookingly' ); ?></a></p>
				<?php
			}
		}

		$wp_customize->add_control(
			new Bookingly_Options_Link_Control(
				$wp_customize,
				'bookingly_options_notice',
				array(
					'section' => 'bookingly_options_link',
					'label'   => __( 'Full Theme Settings', 'bookingly' ),
				)
			)
		);
	}
}
add_action( 'customize_register', 'bookingly_customize_register' );
