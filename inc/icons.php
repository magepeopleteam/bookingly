<?php
/**
 * Inline SVG icon system.
 *
 * Replaces the Tabler icon webfont (~300KB across CSS + woff2) with a single
 * inlined sprite containing only the icons this theme actually draws.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icons shipped in assets/icons/bookingly-icons.svg.
 *
 * Kept as an explicit list so bookingly_icon() can fail loudly-but-safely on a
 * typo instead of emitting a <use> that points at nothing.
 *
 * @return string[]
 */
function bookingly_icon_names() {
	static $names = null;

	if ( null === $names ) {
		$names = array(
			'arrow-right',
			'arrow-up-right',
			'brand-facebook',
			'brand-instagram',
			'brand-linkedin',
			'brand-x',
			'calendar',
			'calendar-event',
			'calendar-time',
			'car',
			'check',
			'circle-check-filled',
			'clock',
			'droplet',
			'engine',
			'heart-handshake',
			'lifebuoy',
			'lock',
			'mail',
			'map-pin',
			'menu-2',
			'music',
			'paw',
			'phone',
			'receipt',
			'refresh',
			'scissors',
			'send',
			'shield-check',
			'spa',
			'spray',
			'star-filled',
			'stethoscope',
			'tool',
			'yoga',
		);
	}

	return $names;
}

/**
 * Normalise an icon reference to a bare sprite name.
 *
 * Accepts legacy webfont classes ("ti ti-phone", "ti-phone") as well as plain
 * names ("phone") so saved options keep working after the webfont removal.
 *
 * @param string $icon Raw icon reference.
 * @return string Sprite name, or '' when unknown.
 */
function bookingly_normalize_icon( $icon ) {
	$icon = trim( (string) $icon );
	if ( '' === $icon ) {
		return '';
	}

	// "ti ti-phone" -> "ti-phone".
	$parts = preg_split( '/\s+/', $icon );
	foreach ( $parts as $part ) {
		if ( 'ti' === $part ) {
			continue;
		}
		$icon = $part;
		break;
	}

	$icon = preg_replace( '/^ti-/', '', $icon );
	$icon = sanitize_html_class( $icon );

	return in_array( $icon, bookingly_icon_names(), true ) ? $icon : '';
}

/**
 * Build an inline SVG icon.
 *
 * Icons are decorative by default (aria-hidden). Pass a label to expose one to
 * assistive technology as an image.
 *
 * @param string              $icon Icon name or legacy "ti-*" class.
 * @param array<string,mixed> $args {
 *     @type string $class Extra CSS classes.
 *     @type string $label Accessible name. Empty keeps the icon decorative.
 *     @type int    $size  Pixel size applied inline. 0 inherits from CSS.
 * }
 * @return string Escaped SVG markup, or '' when the icon is unknown.
 */
function bookingly_get_icon( $icon, $args = array() ) {
	$name = bookingly_normalize_icon( $icon );
	if ( '' === $name ) {
		return '';
	}

	$args = wp_parse_args(
		$args,
		array(
			'class' => '',
			'label' => '',
			'size'  => 0,
		)
	);

	$classes = trim( 'hv-icon hv-icon--' . $name . ' ' . $args['class'] );
	$style   = $args['size'] ? sprintf( ' style="width:%1$dpx;height:%1$dpx"', (int) $args['size'] ) : '';

	if ( $args['label'] ) {
		$a11y = sprintf( ' role="img" aria-label="%s"', esc_attr( $args['label'] ) );
	} else {
		$a11y = ' aria-hidden="true" focusable="false"';
	}

	return sprintf(
		'<svg class="%1$s"%2$s%3$s><use href="#hv-%4$s"></use></svg>',
		esc_attr( $classes ),
		$a11y,
		$style,
		esc_attr( $name )
	);
}

/**
 * Echo an inline SVG icon.
 *
 * @param string              $icon Icon name.
 * @param array<string,mixed> $args See bookingly_get_icon().
 */
function bookingly_icon( $icon, $args = array() ) {
	echo bookingly_get_icon( $icon, $args ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- built and escaped in bookingly_get_icon().
}

/**
 * Inline the sprite once per front-end request.
 *
 * Inlining beats an external <use href="file.svg#id"> here: it costs one
 * ~2.8KB gzipped block in the document and removes a render-path request,
 * plus it sidesteps Safari's historical cross-document <use> gaps.
 */
function bookingly_print_icon_sprite() {
	static $printed = false;

	if ( $printed || is_admin() ) {
		return;
	}
	$printed = true;

	$sprite = BOOKINGLY_DIR . '/assets/icons/bookingly-icons.svg';
	if ( ! is_readable( $sprite ) ) {
		return;
	}

	// Trusted theme-owned file containing only <svg>/<symbol>/<path> markup.
	echo file_get_contents( $sprite ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}
add_action( 'wp_body_open', 'bookingly_print_icon_sprite', 1 );
