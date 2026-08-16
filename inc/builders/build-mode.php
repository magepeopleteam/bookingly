<?php
/**
 * Safe site-wide page building mode.
 *
 * Bookingly never assumes that recognizable markup belongs to the theme. Only a
 * layout with an ownership marker and an unchanged generated fingerprint may
 * be replaced or restored automatically.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BOOKINGLY_BUILD_MODE_OPTION       = 'bookingly_build_mode';
const BOOKINGLY_CONTENT_BACKUP          = '_bookingly_content_backup'; // Legacy key: preserved, never overwritten.
const BOOKINGLY_LAYOUT_OWNER_META       = '_bookingly_layout_owner_v1';
const BOOKINGLY_LAYOUT_BACKUP_META      = '_bookingly_layout_backup_v1';
const BOOKINGLY_LAYOUT_FINGERPRINT_META = '_bookingly_layout_fingerprint_v1';

/**
 * Available building modes.
 *
 * @return array<string,array<string,string>>
 */
function bookingly_build_modes() {
	return array(
		'theme'     => array(
			'label'       => __( 'Theme templates', 'bookingly' ),
			'description' => __( 'Bookingly renders each page in PHP. Fastest, and content is edited in Theme Options. Pages have no editable canvas.', 'bookingly' ),
		),
		'blocks'    => array(
			'label'       => __( 'Block editor', 'bookingly' ),
			'description' => __( 'Each page holds its sections as blocks. Edit them in the WordPress editor, section by section.', 'bookingly' ),
		),
		'elementor' => array(
			'label'       => __( 'Elementor', 'bookingly' ),
			'description' => __( 'Each page is handed to Elementor with its sections as widgets, inside containers you can restyle and reorder.', 'bookingly' ),
		),
	);
}

/**
 * Current building mode.
 *
 * @return string
 */
function bookingly_get_build_mode() {
	$front = (int) get_option( 'page_on_front' );

	if ( $front ) {
		if ( bookingly_is_elementor_page( $front ) ) {
			return 'elementor';
		}

		if ( bookingly_has_bookingly_blocks( $front ) ) {
			return 'blocks';
		}

		return 'theme';
	}

	$mode  = get_option( BOOKINGLY_BUILD_MODE_OPTION, '' );
	$modes = bookingly_build_modes();

	return isset( $modes[ $mode ] ) ? $mode : 'theme';
}

/**
 * The pages Bookingly manages, keyed by layout.
 *
 * @return array<string,int>
 */
function bookingly_managed_pages() {
	$about    = bookingly_get_page_by_template( 'page-templates/template-about.php' );
	$services = bookingly_get_page_by_template( 'page-templates/template-services.php' );
	$contact  = bookingly_get_page_by_template( 'page-templates/template-contact.php' );

	return array_filter(
		array(
			'homepage' => (int) get_option( 'page_on_front' ),
			'about'    => $about ? (int) $about->ID : 0,
			'services' => $services ? (int) $services->ID : 0,
			'contact'  => $contact ? (int) $contact->ID : 0,
			'blog'     => (int) get_option( 'page_for_posts' ),
		)
	);
}

/**
 * Metadata that Bookingly may change while switching builders.
 *
 * @return string[]
 */
function bookingly_layout_managed_meta_keys() {
	return array(
		'_elementor_data',
		'_elementor_edit_mode',
		'_elementor_version',
		'_elementor_template_type',
		'_et_pb_use_builder',
		'_et_pb_old_content',
		'_et_pb_built_for_post_type',
	);
}

/**
 * Capture the exact content and managed builder metadata for a page.
 *
 * @param int $post_id Page ID.
 * @return array<string,mixed>
 */
function bookingly_layout_snapshot( $post_id ) {
	$snapshot = array(
		'post_content' => (string) get_post_field( 'post_content', $post_id ),
		'meta'         => array(),
	);

	foreach ( bookingly_layout_managed_meta_keys() as $key ) {
		$snapshot['meta'][ $key ] = array(
			'exists' => metadata_exists( 'post', $post_id, $key ),
			'value'  => get_post_meta( $post_id, $key, true ),
		);
	}

	return $snapshot;
}

/**
 * Stable fingerprint of the content and builder metadata Bookingly last wrote.
 *
 * @param int $post_id Page ID.
 * @return string
 */
function bookingly_layout_fingerprint( $post_id ) {
	return hash( 'sha256', wp_json_encode( bookingly_layout_snapshot( $post_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/**
 * Whether any managed builder metadata exists on a page.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function bookingly_layout_has_builder_meta( $post_id ) {
	foreach ( bookingly_layout_managed_meta_keys() as $key ) {
		if ( metadata_exists( 'post', $post_id, $key ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Classify whether automatic layout writes are safe.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function bookingly_layout_is_user_owned( $post_id ) {
	$owner = get_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META, true );

	if ( ! is_array( $owner ) || empty( $owner['mode'] ) ) {
		return '' !== trim( (string) get_post_field( 'post_content', $post_id ) ) || bookingly_layout_has_builder_meta( $post_id );
	}

	$stored = (string) get_post_meta( $post_id, BOOKINGLY_LAYOUT_FINGERPRINT_META, true );
	return '' === $stored || ! hash_equals( $stored, bookingly_layout_fingerprint( $post_id ) );
}

/**
 * Save the original page state once, before Bookingly first owns its layout.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function bookingly_layout_ensure_backup( $post_id ) {
	if ( metadata_exists( 'post', $post_id, BOOKINGLY_LAYOUT_BACKUP_META ) ) {
		return true;
	}

	return false !== update_post_meta( $post_id, BOOKINGLY_LAYOUT_BACKUP_META, bookingly_layout_snapshot( $post_id ) );
}

/**
 * Mark a verified generated layout as owned by Bookingly.
 *
 * @param int    $post_id Page ID.
 * @param string $mode    Builder mode.
 * @param string $layout  Bookingly layout key.
 * @return bool
 */
function bookingly_layout_mark_generated( $post_id, $mode, $layout ) {
	$owner = array(
		'mode'    => sanitize_key( $mode ),
		'layout'  => sanitize_key( $layout ),
		'version' => defined( 'BOOKINGLY_VERSION' ) ? BOOKINGLY_VERSION : '1',
	);

	update_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META, $owner );
	return false !== update_post_meta( $post_id, BOOKINGLY_LAYOUT_FINGERPRINT_META, bookingly_layout_fingerprint( $post_id ) );
}

/**
 * Write page content and verify the read-back value.
 *
 * @param int    $post_id Page ID.
 * @param string $content New content.
 * @return true|WP_Error
 */
function bookingly_layout_write_content( $post_id, $content ) {
	$result = wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $content ),
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( (string) get_post_field( 'post_content', $post_id ) !== (string) $content ) {
		return new WP_Error( 'bookingly_content_verify_failed', __( 'WordPress did not preserve the generated page content.', 'bookingly' ) );
	}

	return true;
}

/**
 * Delete Elementor metadata only from an unchanged Bookingly-owned layout.
 *
 * @param int $post_id Page ID.
 * @return true|WP_Error
 */
function bookingly_clear_elementor_layout( $post_id ) {
	$post_id = (int) $post_id;
	$owner   = get_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META, true );

	if ( ! $post_id || ! is_array( $owner ) || bookingly_layout_is_user_owned( $post_id ) ) {
		return new WP_Error( 'bookingly_layout_not_owned', __( 'This Elementor layout is not an unchanged Bookingly-generated layout.', 'bookingly' ) );
	}

	foreach ( array( '_elementor_data', '_elementor_edit_mode', '_elementor_version', '_elementor_template_type' ) as $key ) {
		delete_post_meta( $post_id, $key );
	}

	return true;
}

/**
 * Restore the exact pre-Bookingly page state.
 *
 * @param int $post_id Page ID.
 * @return true|WP_Error
 */
function bookingly_restore_layout_backup( $post_id ) {
	$owner  = get_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META, true );
	$backup = get_post_meta( $post_id, BOOKINGLY_LAYOUT_BACKUP_META, true );

	if ( ! is_array( $owner ) || ! is_array( $backup ) || bookingly_layout_is_user_owned( $post_id ) ) {
		return new WP_Error( 'bookingly_restore_not_safe', __( 'This page has changes that Bookingly will not overwrite.', 'bookingly' ) );
	}

	$result = bookingly_layout_write_content( $post_id, (string) ( $backup['post_content'] ?? '' ) );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	foreach ( bookingly_layout_managed_meta_keys() as $key ) {
		$item = $backup['meta'][ $key ] ?? array( 'exists' => false, 'value' => '' );
		if ( ! empty( $item['exists'] ) ) {
			$value = $item['value'] ?? '';
			update_post_meta( $post_id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}

	$current = bookingly_layout_snapshot( $post_id );
	if ( $current !== $backup ) {
		return new WP_Error( 'bookingly_restore_verify_failed', __( 'The original page layout could not be restored exactly.', 'bookingly' ) );
	}

	delete_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META );
	delete_post_meta( $post_id, BOOKINGLY_LAYOUT_FINGERPRINT_META );
	delete_post_meta( $post_id, BOOKINGLY_LAYOUT_BACKUP_META );

	return true;
}

/**
 * Switch every safe managed page to one building mode.
 *
 * @param string $mode Mode key.
 * @return array<string,mixed> Result summary.
 */
function bookingly_switch_build_mode( $mode ) {
	$modes = bookingly_build_modes();
	if ( ! isset( $modes[ $mode ] ) ) {
		return array( 'success' => false, 'message' => __( 'Unknown building mode.', 'bookingly' ) );
	}

	if ( 'elementor' === $mode && ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
		return array( 'success' => false, 'message' => __( 'Elementor is not active, so pages cannot be switched to it.', 'bookingly' ) );
	}

	$changed = array();
	$skipped = array();
	$errors  = array();

	foreach ( bookingly_managed_pages() as $layout => $post_id ) {
		if ( ! get_post( $post_id ) ) {
			continue;
		}

		$owner = get_post_meta( $post_id, BOOKINGLY_LAYOUT_OWNER_META, true );
		if ( bookingly_layout_is_user_owned( $post_id ) ) {
			$skipped[ $post_id ] = get_the_title( $post_id );
			continue;
		}

		if ( 'theme' === $mode ) {
			if ( is_array( $owner ) ) {
				$result = bookingly_restore_layout_backup( $post_id );
				if ( is_wp_error( $result ) ) {
					$errors[ $post_id ] = $result->get_error_message();
					continue;
				}
			}
			$changed[] = $post_id;
			continue;
		}

		if ( ! bookingly_layout_ensure_backup( $post_id ) ) {
			$errors[ $post_id ] = __( 'The original page state could not be backed up.', 'bookingly' );
			continue;
		}

		if ( 'elementor' === $mode ) {
			$result = bookingly_apply_elementor_layout( $post_id, $layout );
		} else {
			if ( is_array( $owner ) && 'elementor' === ( $owner['mode'] ?? '' ) ) {
				$result = bookingly_clear_elementor_layout( $post_id );
				if ( is_wp_error( $result ) ) {
					$errors[ $post_id ] = $result->get_error_message();
					continue;
				}
			}

			$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'bookingly/' . $layout );
			$result  = $pattern
				? bookingly_layout_write_content( $post_id, $pattern['content'] )
				: new WP_Error( 'bookingly_pattern_missing', __( 'The requested block layout is unavailable.', 'bookingly' ) );

			if ( ! is_wp_error( $result ) ) {
				bookingly_layout_mark_generated( $post_id, 'blocks', $layout );
			}
		}

		if ( is_wp_error( $result ) ) {
			$errors[ $post_id ] = $result->get_error_message();
		} else {
			$changed[] = $post_id;
		}
	}

	if ( empty( $errors ) ) {
		update_option( BOOKINGLY_BUILD_MODE_OPTION, $mode, false );
	}

	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}

	return array(
		'success' => empty( $errors ),
		'mode'    => $mode,
		'changed' => count( $changed ),
		'skipped' => $skipped,
		'errors'  => $errors,
		'mixed'   => ! empty( $skipped ),
	);
}

/**
 * Handle the build-mode form submission.
 */
function bookingly_handle_build_mode_switch() {
	if ( empty( $_POST['bookingly_build_mode_submit'] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change how pages are built.', 'bookingly' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'bookingly_build_mode' );

	$mode   = isset( $_POST['bookingly_build_mode'] ) ? sanitize_key( wp_unslash( $_POST['bookingly_build_mode'] ) ) : '';
	$result = bookingly_switch_build_mode( $mode );

	wp_safe_redirect(
		add_query_arg(
			array(
				'bookingly_mode'    => $result['success'] ? $mode : 'error',
				'bookingly_count'   => $result['changed'] ?? 0,
				'bookingly_skipped' => isset( $result['skipped'] ) ? count( $result['skipped'] ) : 0,
			),
			Bookingly_Theme_Options::page_url( 'builders' )
		)
	);
	exit;
}
add_action( 'admin_init', 'bookingly_handle_build_mode_switch' );

/**
 * Report the outcome of a mode switch.
 */
function bookingly_build_mode_notice() {
	if ( empty( $_GET['bookingly_mode'] ) || 'bookingly-theme-options' !== ( $_GET['page'] ?? '' ) ) {
		return;
	}

	$mode    = sanitize_key( wp_unslash( $_GET['bookingly_mode'] ) );
	$count   = isset( $_GET['bookingly_count'] ) ? (int) $_GET['bookingly_count'] : 0;
	$skipped = isset( $_GET['bookingly_skipped'] ) ? (int) $_GET['bookingly_skipped'] : 0;
	$modes   = bookingly_build_modes();

	if ( 'error' === $mode || ! isset( $modes[ $mode ] ) ) {
		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html__( 'Pages could not be switched. No user-authored layout was overwritten.', 'bookingly' )
		);
		return;
	}

	$message = sprintf(
		/* translators: 1: number of pages, 2: mode name */
		_n( '%1$d page switched to %2$s.', '%1$d pages switched to %2$s.', $count, 'bookingly' ),
		$count,
		$modes[ $mode ]['label']
	);

	if ( $skipped ) {
		$message .= ' ' . sprintf(
			/* translators: %d: number of protected pages */
			_n( '%d authored page was protected and skipped.', '%d authored pages were protected and skipped.', $skipped, 'bookingly' ),
			$skipped
		);
	}

	printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
}
add_action( 'admin_notices', 'bookingly_build_mode_notice' );
