<?php
/**
 * Bookingly setup wizard.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reliable, accessible onboarding for the Bookingly service-booking stack.
 */
final class Bookingly_Setup_Wizard {

	const PAGE_SLUG    = 'bookingly-setup';
	const COMPLETE_KEY = 'bookingly_setup_complete';
	const REDIRECT_KEY = 'bookingly_activation_redirect';
	const LOCK_PREFIX  = 'bookingly_setup_plugin_';
	const IMPORT_LOCK  = 'bookingly_setup_import_lock';
	const ELEMENTOR_ACTIVATION_MARKER_PREFIX = 'bookingly_elementor_activation_';

	/** @var array<int,array<string,mixed>>|null Request-local plugin catalog. */
	private static $plugin_catalog = null;

	/** @var array<string,mixed>|null Request-local readiness result. */
	private static $readiness = null;

	/** @var string Exact WordPress hook suffix for Guided Setup. */
	private static $page_hook = '';

	/**
	 * Server-owned plugin definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function plugin_definitions() {
		$payment_settings = get_option( 'mpwpb_payment_method_settings', array() );
		$payment_mode     = is_array( $payment_settings ) ? (string) ( $payment_settings['payment_method_type'] ?? '' ) : '';
		$pro_active       = self::plugin_is_active( 'service-booking-manager-pro/MPWPB_Plugin_Pro.php' );

		return array(
			'service_booking'    => array(
				'label'       => __( 'Service Booking Manager', 'bookingly' ),
				'description' => __( 'The booking engine used by Bookingly services and appointment pages.', 'bookingly' ),
				'basename'    => 'service-booking-manager/MPWPB_Plugin.php',
				'wporg_slug'  => 'service-booking-manager',
				'source'      => 'wporg',
				'required'    => true,
			),
			'woocommerce'       => array(
				'label'       => __( 'WooCommerce', 'bookingly' ),
				'description' => 'woocommerce' === $payment_mode
					? __( 'Required because WPBookingly is currently configured to use WooCommerce checkout.', 'bookingly' )
					: __( 'Optional for sites using WPBookingly’s native checkout; useful for WooCommerce payments and orders.', 'bookingly' ),
				'basename'    => 'woocommerce/woocommerce.php',
				'wporg_slug'  => 'woocommerce',
				'source'      => 'wporg',
				'required'    => 'woocommerce' === $payment_mode,
			),
			'service_booking_pro' => array(
				'label'       => __( 'Service Booking Manager PRO', 'bookingly' ),
				'description' => __( 'Optional premium booking features. Bookingly never downloads a licensed package.', 'bookingly' ),
				'basename'    => 'service-booking-manager-pro/MPWPB_Plugin_Pro.php',
				'wporg_slug'  => '',
				'source'      => 'upload',
				'required'    => false,
			),
			'pdf_support'        => array(
				'label'       => __( 'MagePeople PDF Support', 'bookingly' ),
				'description' => $pro_active
					? __( 'Required by the active PRO add-on for PDF tickets and invoices.', 'bookingly' )
					: __( 'Optional companion used by PRO for PDF tickets and invoices.', 'bookingly' ),
				'basename'    => 'magepeople-pdf-support-master/mage-pdf.php',
				'wporg_slug'  => '',
				'source'      => 'pdf_setup',
				'required'    => $pro_active,
			),
			'elementor'          => array(
				'label'       => __( 'Elementor', 'bookingly' ),
				'description' => __( 'Optional visual editor. Activating it does not convert or replace any page.', 'bookingly' ),
				'basename'    => 'elementor/elementor.php',
				'wporg_slug'  => 'elementor',
				'source'      => 'wporg',
				'required'    => false,
			),
		);
	}

	/**
	 * Load plugin helpers and check site/network activation.
	 *
	 * @param string $basename Plugin basename.
	 * @return bool
	 */
	private static function plugin_is_active( $basename ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $basename )
			|| ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $basename ) );
	}

	/**
	 * Return the complete current plugin catalog.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_setup_plugins() {
		if ( null !== self::$plugin_catalog ) {
			return self::$plugin_catalog;
		}

		$plugins = array();

		foreach ( self::plugin_definitions() as $key => $definition ) {
			$basename  = $definition['basename'];
			$installed = file_exists( WP_PLUGIN_DIR . '/' . $basename );
			$active    = $installed && self::plugin_is_active( $basename );
			$state     = $active ? 'active' : ( $installed ? 'installed' : 'missing' );
			$action    = 'none';
			$action_url = '';
			$action_label = '';

			if ( 'active' !== $state ) {
				if ( 'installed' === $state ) {
					if ( current_user_can( 'activate_plugins' ) ) {
						$action       = 'activate';
						$action_label = __( 'Activate', 'bookingly' );
					} else {
						$action       = 'link';
						$action_url   = self_admin_url( 'plugins.php' );
						$action_label = __( 'Open Plugins', 'bookingly' );
					}
				} elseif ( 'wporg' === $definition['source'] ) {
					if ( current_user_can( 'install_plugins' ) ) {
						$action       = 'install';
						$action_label = __( 'Install', 'bookingly' );
					} else {
						$action       = 'link';
						$action_url   = self::core_install_url( $definition['wporg_slug'] );
						$action_label = __( 'Ask a network administrator', 'bookingly' );
					}
				} elseif ( 'pdf_setup' === $definition['source'] && self::plugin_is_active( 'service-booking-manager-pro/MPWPB_Plugin_Pro.php' ) ) {
					$action       = 'link';
					$action_url   = admin_url( 'edit.php?post_type=mpwpb_item&page=mpwpb_status_page' );
					$action_label = __( 'Open PDF setup', 'bookingly' );
				} else {
					$action       = 'link';
					$action_url   = self::plugin_upload_url();
					$action_label = current_user_can( 'upload_plugins' ) ? __( 'Upload plugin', 'bookingly' ) : __( 'Ask a network administrator', 'bookingly' );
				}
			}

			$plugins[] = array_merge(
				$definition,
				array(
					'key'          => $key,
					'installed'    => $installed,
					'active'       => $active,
					'state'        => $state,
					'operation'    => 'idle',
					'status_label' => self::status_label( $state ),
					'action'       => $action,
					'action_url'   => $action_url,
					'action_label' => $action_label,
				)
			);
		}

		self::$plugin_catalog = $plugins;
		return self::$plugin_catalog;
	}

	/**
	 * Core plugin installer search URL.
	 *
	 * @param string $slug WordPress.org slug.
	 * @return string
	 */
	private static function core_install_url( $slug ) {
		$base = is_multisite() ? network_admin_url( 'plugin-install.php' ) : admin_url( 'plugin-install.php' );
		return add_query_arg( array( 'tab' => 'search', 'type' => 'term', 's' => $slug ), $base );
	}

	/**
	 * Core plugin upload URL.
	 *
	 * @return string
	 */
	private static function plugin_upload_url() {
		$base = is_multisite() ? network_admin_url( 'plugin-install.php' ) : admin_url( 'plugin-install.php' );
		return add_query_arg( 'tab', 'upload', $base );
	}

	/**
	 * Find a catalog item by its server-owned key.
	 *
	 * @param string $key Catalog key.
	 * @return array<string,mixed>|null
	 */
	private static function plugin_by_key( $key ) {
		foreach ( self::get_setup_plugins() as $plugin ) {
			if ( $key === $plugin['key'] ) {
				return $plugin;
			}
		}

		return null;
	}

	/**
	 * Determine whether the saved setup is still fully ready.
	 *
	 * @return bool
	 */
	public static function is_setup_complete() {
		return (bool) get_option( self::COMPLETE_KEY ) && self::get_readiness()['ready'];
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'after_switch_theme', array( __CLASS__, 'flag_activation_redirect' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_suppress_elementor_onboarding_redirect' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 10 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_bookingly_setup_install_plugin', array( __CLASS__, 'ajax_install_plugin' ) );
		add_action( 'wp_ajax_bookingly_setup_activate_plugin', array( __CLASS__, 'ajax_activate_plugin' ) );
		add_action( 'wp_ajax_bookingly_setup_create_pages', array( __CLASS__, 'ajax_create_pages' ) );
		add_action( 'wp_ajax_bookingly_setup_import_demo', array( __CLASS__, 'ajax_import_demo_step' ) );
		add_action( 'wp_ajax_bookingly_setup_finish', array( __CLASS__, 'ajax_finish_setup' ) );
	}

	/** Queue a one-time redirect after activation. */
	public static function flag_activation_redirect() {
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only activation context.
			return;
		}
		set_transient( self::REDIRECT_KEY, 1, 30 );
	}

	/** Redirect an administrator to setup once after theme activation. */
	public static function maybe_redirect_to_wizard() {
		if ( ! get_transient( self::REDIRECT_KEY ) ) {
			return;
		}
		delete_transient( self::REDIRECT_KEY );
		if ( ! current_user_can( 'manage_options' ) || self::is_setup_complete() ) {
			return;
		}
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Recover an interrupted Bookingly-driven Elementor activation.
	 *
	 * Elementor redirects on admin_init priority 10. This priority-0 guard runs
	 * only for the exact Bookingly setup route and only when the current user has
	 * a short-lived marker created by Bookingly immediately before activation.
	 */
	public static function maybe_suppress_elementor_onboarding_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route guard.
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}

		$marker = self::elementor_activation_marker_key();
		if ( ! get_transient( $marker ) || ! self::plugin_is_active( 'elementor/elementor.php' ) ) {
			return;
		}

		delete_transient( 'elementor_activation_redirect' );
		delete_transient( $marker );
	}

	/** Register Guided Setup beneath the dedicated Bookingly parent. */
	public static function register_page() {
		self::$page_hook = add_submenu_page(
			Bookingly_Theme_Options::PAGE_SLUG,
			__( 'Bookingly Guided Setup', 'bookingly' ),
			__( 'Guided Setup', 'bookingly' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** @return string Exact registered page hook. */
	public static function page_hook() {
		return self::$page_hook;
	}

	/** @return string Dedicated Guided Setup URL. */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Enqueue page-scoped assets.
	 *
	 * @param string $hook Admin hook name.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! self::$page_hook || self::$page_hook !== $hook ) {
			return;
		}

		wp_enqueue_style( 'bookingly-setup-wizard', BOOKINGLY_URI . '/assets/admin/setup-wizard.css', array(), bookingly_asset_version( '/assets/admin/setup-wizard.css' ) );
		wp_enqueue_script( 'bookingly-setup-wizard', BOOKINGLY_URI . '/assets/admin/setup-wizard.js', array( 'jquery', 'wp-a11y' ), bookingly_asset_version( '/assets/admin/setup-wizard.js' ), true );

		$readiness = self::get_readiness();
		wp_localize_script(
			'bookingly-setup-wizard',
			'bookinglySetup',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'bookingly_setup_wizard' ),
				'importNonce'  => wp_create_nonce( 'mpwpb_import_dummy' ),
				'plugins'      => self::get_setup_plugins(),
				'complete'     => (bool) get_option( self::COMPLETE_KEY ) && $readiness['ready'],
				'pagesReady'   => $readiness['pages_ready'],
				'themeOptions' => Bookingly_Theme_Options::page_url(),
				'i18n'         => array(
					'working'        => __( 'Working…', 'bookingly' ),
					'installing'     => __( 'Installing…', 'bookingly' ),
					'activating'     => __( 'Activating…', 'bookingly' ),
					'pluginsReady'   => __( 'All required tools are active.', 'bookingly' ),
					'pluginsBlocked' => __( 'A required tool still needs attention.', 'bookingly' ),
					'pagesReady'     => __( 'Starter pages are ready. Existing assignments were preserved.', 'bookingly' ),
					'importing'      => __( 'Importing demo services…', 'bookingly' ),
					'importComplete' => __( 'Demo services imported.', 'bookingly' ),
					'importSkipped'  => __( 'Demo import skipped. You can add services later.', 'bookingly' ),
					'importLimit'    => __( 'The importer stopped safely. Review the service list before deliberately retrying because partial demo content may exist.', 'bookingly' ),
					'error'          => __( 'Something went wrong. Review the message and retry.', 'bookingly' ),
					'retry'          => __( 'Retry deliberately', 'bookingly' ),
					'finishing'      => __( 'Verifying setup…', 'bookingly' ),
					'finishBlocked'  => __( 'Setup is not ready yet. Complete the required items above.', 'bookingly' ),
					'required'       => __( 'Required', 'bookingly' ),
					'optional'       => __( 'Optional', 'bookingly' ),
				),
			)
		);
	}

	/**
	 * Install one allowlisted WordPress.org plugin.
	 */
	public static function ajax_install_plugin() {
		self::verify_ajax();
		if ( ! current_user_can( 'install_plugins' ) ) {
			self::send_plugin_error( __( 'You do not have permission to install plugins.', 'bookingly' ), 403 );
		}

		$key    = self::requested_plugin_key();
		$plugin = self::plugin_by_key( $key );
		if ( ! $plugin || 'wporg' !== $plugin['source'] || empty( $plugin['wporg_slug'] ) ) {
			self::send_plugin_error( __( 'That plugin cannot be installed by Bookingly.', 'bookingly' ), 400 );
		}
		if ( $plugin['installed'] ) {
			self::send_plugin_success( __( 'The plugin is already installed.', 'bookingly' ) );
		}
		if ( ! self::acquire_plugin_lock( $key ) ) {
			self::send_plugin_error( __( 'Another operation is already running for this plugin.', 'bookingly' ), 409 );
		}

		register_shutdown_function( static function () use ( $key ) { delete_option( self::LOCK_PREFIX . $key ); } );

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api( 'plugin_information', array( 'slug' => $plugin['wporg_slug'], 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			$message = is_wp_error( $api ) ? $api->get_error_message() : __( 'WordPress.org did not provide a download package.', 'bookingly' );
			self::send_plugin_error( $message, 502, self::core_install_url( $plugin['wporg_slug'] ) );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) || false === $result ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'WordPress could not install the plugin.', 'bookingly' );
			self::send_plugin_error( $message, 500, self::core_install_url( $plugin['wporg_slug'] ) );
		}

		self::clear_request_cache();
		$refreshed = self::plugin_by_key( $key );
		if ( ! $refreshed || ! $refreshed['installed'] ) {
			self::send_plugin_error( __( 'Installation finished but the expected plugin files were not found.', 'bookingly' ), 500, self::core_install_url( $plugin['wporg_slug'] ) );
		}

		self::send_plugin_success( __( 'Installed successfully. You can activate it now.', 'bookingly' ) );
	}

	/**
	 * Activate one installed allowlisted plugin.
	 */
	public static function ajax_activate_plugin() {
		self::verify_ajax();
		if ( ! current_user_can( 'activate_plugins' ) ) {
			self::send_plugin_error( __( 'You do not have permission to activate plugins.', 'bookingly' ), 403 );
		}

		$key    = self::requested_plugin_key();
		$plugin = self::plugin_by_key( $key );
		if ( ! $plugin || ! $plugin['installed'] ) {
			self::send_plugin_error( __( 'Install the plugin before activating it.', 'bookingly' ), 409 );
		}
		if ( $plugin['active'] ) {
			self::send_plugin_success( __( 'The plugin is already active.', 'bookingly' ) );
		}
		if ( ! self::acquire_plugin_lock( $key ) ) {
			self::send_plugin_error( __( 'Another operation is already running for this plugin.', 'bookingly' ), 409 );
		}

		register_shutdown_function( static function () use ( $key ) { delete_option( self::LOCK_PREFIX . $key ); } );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$network_wide = false;
		if ( is_multisite() && function_exists( 'is_network_only_plugin' ) && is_network_only_plugin( $plugin['basename'] ) ) {
			if ( ! is_network_admin() || ! current_user_can( 'manage_network_plugins' ) ) {
				self::send_plugin_error( __( 'This plugin must be activated by a network administrator.', 'bookingly' ), 403, network_admin_url( 'plugins.php' ) );
			}
			$network_wide = true;
		}

		add_action( 'activate_plugin', array( __CLASS__, 'suppress_service_booking_redirect' ), 1, 2 );
		if ( 'elementor' === $key ) {
			set_transient( self::elementor_activation_marker_key(), 1, 2 * MINUTE_IN_SECONDS );
		}
		ob_start();
		$result = activate_plugin( $plugin['basename'], '', $network_wide, false );
		ob_end_clean();
		remove_action( 'activate_plugin', array( __CLASS__, 'suppress_service_booking_redirect' ), 1 );

		if ( 'elementor' === $key ) {
			self::clear_elementor_activation_redirect();
		}

		if ( is_wp_error( $result ) ) {
			self::send_plugin_error( $result->get_error_message(), 500 );
		}

		if ( ! self::plugin_is_active( $plugin['basename'] ) ) {
			self::send_plugin_error( __( 'WordPress did not report the plugin as active.', 'bookingly' ), 500 );
		}

		if ( 'service_booking' === $key ) {
			flush_rewrite_rules( false );
		}
		self::send_plugin_success( __( 'Activated successfully.', 'bookingly' ) );
	}

	/**
	 * Remove only Service Booking Manager's AJAX-breaking redirect callback.
	 *
	 * @param string $plugin Plugin basename.
	 * @param bool   $network_wide Whether activation is network-wide.
	 */
	public static function suppress_service_booking_redirect( $plugin, $network_wide ) {
		unset( $network_wide );
		if ( 'service-booking-manager/MPWPB_Plugin.php' !== $plugin ) {
			return;
		}

		global $wp_filter;
		if ( empty( $wp_filter['activated_plugin'] ) || ! $wp_filter['activated_plugin'] instanceof WP_Hook ) {
			return;
		}

		foreach ( $wp_filter['activated_plugin']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;
				if ( is_array( $function ) && is_object( $function[0] ) && $function[0] instanceof MPWPB_Plugin && 'activation_redirect' === $function[1] ) {
					remove_action( 'activated_plugin', $function, $priority );
				}
			}
		}
	}

	/** Create starter pages without overwriting existing content. */
	public static function ajax_create_pages() {
		self::verify_ajax();
		$result = bookingly_create_starter_pages();
		if ( empty( $result['success'] ) ) {
			wp_send_json_error( $result, 500 );
		}
		wp_send_json_success( $result );
	}

	/** Proxy one bounded import step to Service Booking Manager. */
	public static function ajax_import_demo_step() {
		check_ajax_referer( 'mpwpb_import_dummy', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bookingly' ) ), 403 );
		}
		if ( ! self::acquire_lock( self::IMPORT_LOCK ) ) {
			wp_send_json_error( array( 'message' => __( 'A demo import request is already running. Wait for it to finish before trying again.', 'bookingly' ) ), 409 );
		}
		if ( ! class_exists( 'MPWPB_Dummy_Import' ) ) {
			delete_option( self::IMPORT_LOCK );
			wp_send_json_error( array( 'message' => __( 'Service Booking Manager is not active.', 'bookingly' ) ), 409 );
		}

		register_shutdown_function( static function () { delete_option( self::IMPORT_LOCK ); } );
		$importer = new MPWPB_Dummy_Import();
		$importer->ajax_import_dummy_step();
	}

	/** Mark setup complete only after server-side readiness verification. */
	public static function ajax_finish_setup() {
		self::verify_ajax();
		$readiness = self::get_readiness();
		if ( ! $readiness['ready'] ) {
			wp_send_json_error( $readiness, 409 );
		}
		update_option( self::COMPLETE_KEY, 1, false );
		flush_rewrite_rules( false );
		wp_send_json_success( array( 'redirect' => Bookingly_Theme_Options::page_url(), 'readiness' => $readiness ) );
	}

	/** Verify a standard setup request. */
	private static function verify_ajax() {
		if ( ! check_ajax_referer( 'bookingly_setup_wizard', 'nonce', false ) ) {
			self::send_plugin_error( __( 'This setup request expired. Refresh the page and try again.', 'bookingly' ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::send_plugin_error( __( 'Permission denied.', 'bookingly' ), 403 );
		}
	}

	/** @return string */
	private static function requested_plugin_key() {
		return isset( $_POST['plugin'] ) ? sanitize_key( wp_unslash( $_POST['plugin'] ) ) : '';
	}

	/** @param string $key Catalog key. @return bool */
	private static function acquire_plugin_lock( $key ) {
		return self::acquire_lock( self::LOCK_PREFIX . sanitize_key( $key ) );
	}

	/**
	 * Return the current administrator's short-lived Elementor marker key.
	 *
	 * @return string
	 */
	private static function elementor_activation_marker_key() {
		return self::ELEMENTOR_ACTIVATION_MARKER_PREFIX . get_current_user_id();
	}

	/** Clear only Elementor's one-time redirect and Bookingly's current marker. */
	private static function clear_elementor_activation_redirect() {
		delete_transient( 'elementor_activation_redirect' );
		delete_transient( self::elementor_activation_marker_key() );
	}

	/**
	 * Atomically acquire an expiring request lock using the unique option key.
	 *
	 * @param string $lock Lock option name.
	 * @return bool
	 */
	private static function acquire_lock( $lock ) {
		$expires = (int) get_option( $lock, 0 );
		if ( $expires && $expires < time() ) {
			delete_option( $lock );
		}

		return add_option( $lock, time() + ( 2 * MINUTE_IN_SECONDS ), '', false );
	}

	/** Send a refreshed successful plugin result. */
	private static function send_plugin_success( $message ) {
		self::clear_request_cache();
		$readiness = self::get_readiness();
		wp_send_json_success( array( 'message' => $message, 'plugins' => self::get_setup_plugins(), 'required_ready' => $readiness['plugins_ready'] ) );
	}

	/** Send a refreshed failed plugin result. */
	private static function send_plugin_error( $message, $status = 400, $action_url = '' ) {
		self::clear_request_cache();
		$readiness = self::get_readiness();
		wp_send_json_error(
			array(
				'message'        => $message,
				'action_url'     => $action_url,
				'plugins'        => self::get_setup_plugins(),
				'required_ready' => $readiness['plugins_ready'],
			),
			$status
		);
	}

	/** Return current readiness, used both for rendering and completion. */
	private static function get_readiness() {
		if ( null !== self::$readiness ) {
			return self::$readiness;
		}

		$plugin_issues = array();
		foreach ( self::get_setup_plugins() as $plugin ) {
			if ( $plugin['required'] && ! $plugin['active'] ) {
				$plugin_issues[] = $plugin['label'];
			}
		}

		$front_page  = (int) get_option( 'page_on_front' );
		$posts_page  = (int) get_option( 'page_for_posts' );
		$pages_ready = 'page' === get_option( 'show_on_front' )
			&& $front_page > 0 && 'publish' === get_post_status( $front_page )
			&& $posts_page > 0 && 'publish' === get_post_status( $posts_page );

		self::$readiness = array(
			'ready'          => empty( $plugin_issues ) && $pages_ready,
			'plugins_ready'  => empty( $plugin_issues ),
			'plugin_issues'  => $plugin_issues,
			'pages_ready'    => $pages_ready,
			'front_page'     => $front_page,
			'posts_page'     => $posts_page,
		);

		return self::$readiness;
	}

	/** Clear request-local state after an install or activation may change it. */
	private static function clear_request_cache() {
		self::$plugin_catalog = null;
		self::$readiness      = null;
	}

	/** @param string $state State key. @return string */
	private static function status_label( $state ) {
		$labels = array(
			'active'      => __( 'Active', 'bookingly' ),
			'installed'   => __( 'Installed', 'bookingly' ),
			'missing'     => __( 'Not installed', 'bookingly' ),
			'unavailable' => __( 'Unavailable', 'bookingly' ),
		);
		return $labels[ $state ] ?? __( 'Needs attention', 'bookingly' );
	}

	/** Render the five-step interface. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugins         = self::get_setup_plugins();
		$stored_complete = (bool) get_option( self::COMPLETE_KEY );
		$ready           = self::get_readiness();
		$complete        = $stored_complete && $ready['ready'];
		$start           = $complete ? 4 : ( $stored_complete ? 1 : 0 );
		$steps           = array( __( 'Welcome', 'bookingly' ), __( 'Plugins', 'bookingly' ), __( 'Pages', 'bookingly' ), __( 'Demo content', 'bookingly' ), __( 'Finish', 'bookingly' ) );
		?>
		<div class="wrap bookingly-setup-wrap">
			<header class="bookingly-setup-header">
				<p class="bookingly-kicker"><?php esc_html_e( 'Bookingly onboarding', 'bookingly' ); ?></p>
				<h1><?php esc_html_e( 'Build your service-booking site', 'bookingly' ); ?></h1>
				<p><?php esc_html_e( 'Install or activate each tool individually, prepare missing site pages, and choose whether to add demo services.', 'bookingly' ); ?></p>
			</header>

			<nav class="bookingly-setup-progress-nav" aria-label="<?php esc_attr_e( 'Setup progress', 'bookingly' ); ?>">
				<ol class="bookingly-setup-stepper">
					<?php foreach ( $steps as $index => $label ) : ?>
						<li class="<?php echo $index < $start ? 'is-complete' : ''; ?>">
							<button type="button" data-step-target="<?php echo esc_attr( $index ); ?>" <?php echo $index === $start ? 'aria-current="step"' : ''; ?> <?php disabled( ! $complete && 0 !== $index ); ?>>
								<span class="bookingly-step-number"><?php echo esc_html( $index + 1 ); ?></span><span><?php echo esc_html( $label ); ?></span>
							</button>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>

			<div class="bookingly-setup-card" data-start-step="<?php echo esc_attr( $start ); ?>" data-complete="<?php echo $complete ? '1' : '0'; ?>">
				<section class="bookingly-setup-panel is-active" data-step="0" aria-labelledby="bookingly-step-heading-0">
					<p class="bookingly-step-count"><?php esc_html_e( 'Step 1 of 5', 'bookingly' ); ?></p>
					<h2 id="bookingly-step-heading-0" tabindex="-1"><?php esc_html_e( 'Welcome to Bookingly', 'bookingly' ); ?></h2>
					<p><?php esc_html_e( 'Nothing is installed or activated automatically. Existing pages, reading assignments, saved settings, and builder layouts are protected.', 'bookingly' ); ?></p>
					<div class="bookingly-setup-actions"><button type="button" class="button button-primary bookingly-setup-next"><?php esc_html_e( 'Start setup', 'bookingly' ); ?></button></div>
				</section>

				<section class="bookingly-setup-panel" data-step="1" aria-labelledby="bookingly-step-heading-1" hidden>
					<p class="bookingly-step-count"><?php esc_html_e( 'Step 2 of 5', 'bookingly' ); ?></p>
					<h2 id="bookingly-step-heading-1" tabindex="-1"><?php esc_html_e( 'Choose your tools', 'bookingly' ); ?></h2>
					<p><?php esc_html_e( 'The booking engine is required. WooCommerce follows the payment mode already selected in WPBookingly. PRO and Elementor remain optional.', 'bookingly' ); ?></p>
					<ul class="bookingly-plugin-grid" id="bookingly-plugin-results">
						<?php foreach ( $plugins as $plugin ) : ?>
							<li class="bookingly-plugin-card" data-plugin-key="<?php echo esc_attr( $plugin['key'] ); ?>" data-state="<?php echo esc_attr( $plugin['state'] ); ?>" data-required="<?php echo $plugin['required'] ? '1' : '0'; ?>" aria-busy="false">
								<span class="bookingly-status-icon" aria-hidden="true"></span>
								<div class="bookingly-plugin-copy">
									<div><strong><?php echo esc_html( $plugin['label'] ); ?></strong> <span class="<?php echo $plugin['required'] ? 'bookingly-required' : 'bookingly-optional'; ?>"><?php echo $plugin['required'] ? esc_html__( 'Required', 'bookingly' ) : esc_html__( 'Optional', 'bookingly' ); ?></span></div>
									<p><?php echo esc_html( $plugin['description'] ); ?></p>
									<p class="bookingly-plugin-status" id="bookingly-plugin-status-<?php echo esc_attr( $plugin['key'] ); ?>" tabindex="-1" aria-live="polite"><?php echo esc_html( $plugin['status_label'] ); ?></p>
								</div>
								<div class="bookingly-plugin-action">
									<?php if ( in_array( $plugin['action'], array( 'install', 'activate' ), true ) ) : ?>
										<button type="button" class="button <?php echo $plugin['required'] ? 'button-primary' : ''; ?> bookingly-plugin-button" data-plugin-action="<?php echo esc_attr( $plugin['action'] ); ?>" data-plugin-key="<?php echo esc_attr( $plugin['key'] ); ?>" aria-describedby="bookingly-plugin-status-<?php echo esc_attr( $plugin['key'] ); ?>"><?php echo esc_html( $plugin['action_label'] . ' ' . $plugin['label'] ); ?></button>
									<?php elseif ( 'link' === $plugin['action'] && $plugin['action_url'] ) : ?>
										<a class="button" href="<?php echo esc_url( $plugin['action_url'] ); ?>"><?php echo esc_html( $plugin['action_label'] ); ?></a>
									<?php else : ?>
										<span class="bookingly-plugin-ready"><?php esc_html_e( 'Ready', 'bookingly' ); ?></span>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="bookingly-setup-log <?php echo $ready['plugins_ready'] ? 'is-success' : ''; ?>" aria-live="polite"><?php echo $ready['plugins_ready'] ? esc_html__( 'All required tools are active.', 'bookingly' ) : esc_html__( 'Complete the required tool shown above.', 'bookingly' ); ?></p>
					<div class="bookingly-setup-actions">
						<button type="button" class="button bookingly-setup-back"><?php esc_html_e( 'Back', 'bookingly' ); ?></button>
						<button type="button" class="button bookingly-setup-next <?php echo $ready['plugins_ready'] ? 'button-primary' : ''; ?>" <?php disabled( ! $ready['plugins_ready'] ); ?>><?php esc_html_e( 'Continue', 'bookingly' ); ?></button>
					</div>
				</section>

				<section class="bookingly-setup-panel" data-step="2" aria-labelledby="bookingly-step-heading-2" hidden>
					<p class="bookingly-step-count"><?php esc_html_e( 'Step 3 of 5', 'bookingly' ); ?></p>
					<h2 id="bookingly-step-heading-2" tabindex="-1"><?php esc_html_e( 'Prepare site pages', 'bookingly' ); ?></h2>
					<p><?php esc_html_e( 'Bookingly creates only missing Home, About, Services, Contact, and Blog pages. Valid existing front and posts page assignments are kept.', 'bookingly' ); ?></p>
					<ul class="bookingly-result-list" id="bookingly-page-results" aria-live="polite"></ul>
					<p class="bookingly-empty-state" id="bookingly-pages-empty"><?php echo $ready['pages_ready'] ? esc_html__( 'The front page and posts page are already configured.', 'bookingly' ) : esc_html__( 'No page changes have been run in this session.', 'bookingly' ); ?></p>
					<p class="bookingly-setup-log <?php echo $ready['pages_ready'] ? 'is-success' : ''; ?>" aria-live="polite"></p>
					<div class="bookingly-setup-actions">
						<button type="button" class="button bookingly-setup-back"><?php esc_html_e( 'Back', 'bookingly' ); ?></button>
						<button type="button" class="button button-primary bookingly-setup-action" id="bookingly-create-pages" data-done-label="<?php esc_attr_e( 'Pages ready', 'bookingly' ); ?>"><?php esc_html_e( 'Prepare pages', 'bookingly' ); ?></button>
						<button type="button" class="button bookingly-setup-next" <?php disabled( ! $ready['pages_ready'] ); ?>><?php esc_html_e( 'Continue', 'bookingly' ); ?></button>
					</div>
				</section>

				<section class="bookingly-setup-panel" data-step="3" aria-labelledby="bookingly-step-heading-3" hidden>
					<p class="bookingly-step-count"><?php esc_html_e( 'Step 4 of 5', 'bookingly' ); ?></p>
					<h2 id="bookingly-step-heading-3" tabindex="-1"><?php esc_html_e( 'Choose demo content', 'bookingly' ); ?></h2>
					<p><?php esc_html_e( 'Optional: publish sample services to preview the theme. Skip this on an existing site. If an import fails, inspect the service list before retrying because partial content may exist.', 'bookingly' ); ?></p>
					<div class="bookingly-setup-progress" hidden><div class="bookingly-setup-progress-track" role="progressbar" aria-label="<?php esc_attr_e( 'Demo import progress', 'bookingly' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div><p class="bookingly-setup-progress-label"></p></div>
					<p class="bookingly-setup-log" aria-live="polite"></p>
					<div class="bookingly-setup-actions">
						<button type="button" class="button bookingly-setup-back"><?php esc_html_e( 'Back', 'bookingly' ); ?></button>
						<button type="button" class="button button-primary bookingly-setup-action" id="bookingly-import-demo"><?php esc_html_e( 'Import demo services', 'bookingly' ); ?></button>
						<button type="button" class="button bookingly-setup-action" id="bookingly-skip-demo"><?php esc_html_e( 'Skip demo import', 'bookingly' ); ?></button>
						<button type="button" class="button bookingly-setup-next" disabled><?php esc_html_e( 'Continue', 'bookingly' ); ?></button>
					</div>
				</section>

				<section class="bookingly-setup-panel" data-step="4" aria-labelledby="bookingly-step-heading-4" hidden>
					<p class="bookingly-step-count"><?php esc_html_e( 'Step 5 of 5', 'bookingly' ); ?></p>
					<h2 id="bookingly-step-heading-4" tabindex="-1"><?php esc_html_e( 'Verify and finish', 'bookingly' ); ?></h2>
					<p><?php esc_html_e( 'Bookingly verifies required tools and page assignments before marking setup complete.', 'bookingly' ); ?></p>
					<ul class="bookingly-readiness-list">
						<li data-check="plugins" class="<?php echo $ready['plugins_ready'] ? 'is-ready' : 'is-pending'; ?>"><span aria-hidden="true"></span><?php esc_html_e( 'Required tools active', 'bookingly' ); ?></li>
						<li data-check="pages" class="<?php echo $ready['pages_ready'] ? 'is-ready' : 'is-pending'; ?>"><span aria-hidden="true"></span><?php esc_html_e( 'Front page and blog page assigned', 'bookingly' ); ?></li>
						<li data-check="demo" class="is-ready"><span aria-hidden="true"></span><?php esc_html_e( 'Demo content choice recorded', 'bookingly' ); ?></li>
					</ul>
					<p class="bookingly-setup-log" aria-live="polite"></p>
					<div class="bookingly-setup-actions"><button type="button" class="button bookingly-setup-back"><?php esc_html_e( 'Back', 'bookingly' ); ?></button><button type="button" class="button button-primary" id="bookingly-finish-setup"><?php echo $complete ? esc_html__( 'Open Theme Options', 'bookingly' ) : esc_html__( 'Verify and finish', 'bookingly' ); ?></button></div>
				</section>
			</div>
		</div>
		<?php
	}
}

Bookingly_Setup_Wizard::init();
