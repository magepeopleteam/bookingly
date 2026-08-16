<?php
/**
 * One-time migration from the previous Havenly theme identity.
 *
 * The theme was renamed to Bookingly. Everything a site had configured lives
 * under the old option, theme-mod and meta keys, so this copies it across once
 * and records that it ran. The old rows are left in place: they cost nothing,
 * and keeping them means a rollback to the previous theme still finds its data.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BOOKINGLY_MIGRATION_FLAG = 'bookingly_migrated_from_havenly';

/**
 * Copy legacy Havenly data onto the Bookingly keys.
 *
 * Safe to call on every request: it returns immediately once complete, and
 * never overwrites a value that already exists under the new key.
 */
function bookingly_migrate_legacy_data() {
	if ( get_option( BOOKINGLY_MIGRATION_FLAG ) ) {
		return;
	}

	// Theme options.
	$legacy_options = get_option( 'havenly_theme_options', null );
	if ( is_array( $legacy_options ) && false === get_option( 'bookingly_theme_options', false ) ) {
		update_option( 'bookingly_theme_options', $legacy_options );
	}

	// Simple flags.
	foreach ( array( 'setup_complete', 'options_migrated' ) as $flag ) {
		$value = get_option( 'havenly_' . $flag, null );
		if ( null !== $value && false === get_option( 'bookingly_' . $flag, false ) ) {
			update_option( 'bookingly_' . $flag, $value );
		}
	}

	bookingly_migrate_theme_mods();
	bookingly_migrate_post_meta();

	update_option( BOOKINGLY_MIGRATION_FLAG, 1 );
}
add_action( 'after_setup_theme', 'bookingly_migrate_legacy_data', 0 );

/**
 * Carry the custom logo and other customizer settings over.
 *
 * WordPress keys theme mods by stylesheet directory, so renaming the folder
 * would otherwise present a site with its logo and menu locations wiped.
 */
function bookingly_migrate_theme_mods() {
	$legacy = get_option( 'theme_mods_havenly', null );
	if ( ! is_array( $legacy ) ) {
		return;
	}

	$stylesheet = get_option( 'stylesheet' );
	if ( ! $stylesheet || 'havenly' === $stylesheet ) {
		return;
	}

	$current = get_option( 'theme_mods_' . $stylesheet, array() );
	$current = is_array( $current ) ? $current : array();

	// Anything already set on the new theme wins.
	update_option( 'theme_mods_' . $stylesheet, $current + $legacy );
}

/**
 * Reuse thumbnails that were generated under the old image-size names.
 *
 * Attachment metadata records sizes by name, so after the rename every existing
 * image would miss "bookingly-card" / "bookingly-hero" and WordPress would fall
 * back to serving the full-size original — a real performance regression on any
 * upgraded site. Mapping the old entries onto the new names keeps the already
 * generated files in use, with no regeneration required.
 *
 * @param mixed $data Attachment metadata.
 * @return mixed
 */
function bookingly_alias_legacy_image_sizes( $data ) {
	if ( ! is_array( $data ) || empty( $data['sizes'] ) || ! is_array( $data['sizes'] ) ) {
		return $data;
	}

	foreach ( array( 'card', 'hero' ) as $size ) {
		$new = 'bookingly-' . $size;
		$old = 'havenly-' . $size;

		if ( empty( $data['sizes'][ $new ] ) && ! empty( $data['sizes'][ $old ] ) ) {
			$data['sizes'][ $new ] = $data['sizes'][ $old ];
		}
	}

	return $data;
}
add_filter( 'wp_get_attachment_metadata', 'bookingly_alias_legacy_image_sizes' );

/**
 * Rename the per-entry SEO override meta keys.
 */
function bookingly_migrate_post_meta() {
	global $wpdb;

	$map = array(
		'_havenly_seo_title'       => '_bookingly_seo_title',
		'_havenly_seo_description' => '_bookingly_seo_description',
		'_havenly_seo_image'       => '_bookingly_seo_image',
		'_havenly_seo_noindex'     => '_bookingly_seo_noindex',
	);

	foreach ( $map as $old => $new ) {
		// Only move rows that would not collide with an existing new-key row.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} pm
				 SET pm.meta_key = %s
				 WHERE pm.meta_key = %s
				   AND NOT EXISTS (
					   SELECT 1 FROM ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ) existing
					   WHERE existing.post_id = pm.post_id
				   )",
				$new,
				$old,
				$new
			)
		);
	}

	wp_cache_flush();
}
